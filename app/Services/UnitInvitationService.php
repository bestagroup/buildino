<?php

namespace App\Services;

use App\Contracts\Notifications\SmsSender;
use App\Enums\InvitationChannel;
use App\Enums\InvitationStatus;
use App\Models\Unit;
use App\Models\UnitInvitation;
use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UnitInvitationService
{
    public function __construct(
        private readonly SmsSender $sms,
        private readonly OccupancyService $occupancies
    ) {
    }

    /**
     * @return array{
     *     invitation: UnitInvitation,
     *     raw_token: string
     * }
     */
    public function create(
        Unit $unit,
        User $actor,
        array $data
    ): array {
        $data = $this->normalizeContactData($data);

        $this->assertContactMatchesChannel($data);

        $this->assertNoActiveDuplicate(
            $unit,
            $data
        );

        $rawToken = $this->newRawToken();

        $invitation = UnitInvitation::query()->create([
            'unit_id' => $unit->getKey(),
            'invited_by' => $actor->getKey(),

            'mobile' => $data['mobile'] ?? null,
            'email' => $data['email'] ?? null,

            'relation_type' => $data['relation_type'],
            'channel' => $data['channel'],

            /*
             * Never store the bearer token itself.
             */
            'token' => $this->hashToken($rawToken),

            'status' => InvitationStatus::Pending,

            'expires_at' => now()->addHours(
                (int) ($data['expires_in_hours'] ?? 72)
            ),
        ]);

        $this->deliver(
            $invitation,
            $rawToken
        );

        return [
            'invitation' => $invitation->refresh(),
            'raw_token' => $rawToken,
        ];
    }

    /**
     * @return array{
     *     invitation: UnitInvitation,
     *     raw_token: string
     * }
     */
    public function resend(
        UnitInvitation $invitation
    ): array {
        $this->assertCanBeResent(
            $invitation
        );

        $rawToken = $this->newRawToken();

        /*
         * Rotate the token on each resend so all previous links
         * immediately become invalid.
         */
        $invitation->update([
            'token' => $this->hashToken($rawToken),
            'status' => InvitationStatus::Pending,
            'sent_at' => null,
        ]);

        $this->deliver(
            $invitation,
            $rawToken
        );

        return [
            'invitation' => $invitation->refresh(),
            'raw_token' => $rawToken,
        ];
    }

    public function cancel(
        UnitInvitation $invitation
    ): UnitInvitation {
        if ($invitation->status === InvitationStatus::Cancelled) {
            return $invitation->refresh();
        }

        if ($invitation->status === InvitationStatus::Accepted) {
            throw ValidationException::withMessages([
                'invitation' => [
                    'An accepted invitation cannot be cancelled.',
                ],
            ]);
        }

        if ($invitation->isExpired()) {
            $invitation->update([
                'status' => InvitationStatus::Expired,
            ]);

            return $invitation->refresh();
        }

        $invitation->update([
            'status' => InvitationStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        return $invitation->refresh();
    }

    public function accept(
        string $rawToken,
        User $user
    ): UnitInvitation {
        $tokenHash = $this->hashToken(
            $rawToken
        );

        $invitation = UnitInvitation::query()
            ->where('token', $tokenHash)
            ->firstOrFail();

        /*
         * Persist the expired state before throwing so expiration
         * is not rolled back by a DB transaction.
         */
        if (
            $invitation->status !== InvitationStatus::Accepted
            && $invitation->isExpired()
        ) {
            $invitation->update([
                'status' => InvitationStatus::Expired,
            ]);

            throw ValidationException::withMessages([
                'token' => [
                    'This invitation has expired.',
                ],
            ]);
        }

        $this->assertInvitationBelongsToUser(
            $invitation,
            $user
        );

        return DB::transaction(function () use (
            $tokenHash,
            $user
        ): UnitInvitation {
            $invitation = UnitInvitation::query()
                ->where('token', $tokenHash)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $invitation->status === InvitationStatus::Accepted
                && (int) $invitation->accepted_user_id === (int) $user->getKey()
            ) {
                return $invitation;
            }

            if (
                ! in_array(
                    $invitation->status,
                    [
                        InvitationStatus::Pending,
                        InvitationStatus::Sent,
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'token' => [
                        'This invitation is no longer active.',
                    ],
                ]);
            }

            if ($invitation->isExpired()) {
                throw ValidationException::withMessages([
                    'token' => [
                        'This invitation has expired.',
                    ],
                ]);
            }

            $this->assertInvitationBelongsToUser(
                $invitation,
                $user
            );

            /*
             * Invitation acceptance creates residency/occupancy.
             *
             * Legal ownership is deliberately NOT created here because
             * the invitation schema contains no ownership percentage
             * or legal ownership metadata.
             */
            $activeOccupancyExists = UnitOccupancy::query()
                ->where(
                    'unit_id',
                    $invitation->unit_id
                )
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query
                        ->whereNull('ends_at')
                        ->orWhere(
                            'ends_at',
                            '>=',
                            now()->toDateString()
                        );
                })
                ->exists();

            if (! $activeOccupancyExists) {
                $actor = $invitation->invitedBy
                    ?: $user;

                $this->occupancies->assign(
                    [
                        'unit_id' => $invitation->unit_id,
                        'user_id' => $user->getKey(),
                        'occupancy_type' => $invitation->relation_type->value,
                        'starts_at' => now()->toDateString(),
                        'ends_at' => null,
                        'is_primary' => false,
                        'is_active' => true,
                        'notes' => sprintf(
                            'Created from unit invitation #%d.',
                            $invitation->getKey()
                        ),
                    ],
                    $actor
                );
            }

            $invitation->update([
                'status' => InvitationStatus::Accepted,
                'accepted_at' => now(),
                'accepted_user_id' => $user->getKey(),
            ]);

            return $invitation->refresh();
        });
    }

    public function findForUserByToken(
        string $rawToken,
        User $user
    ): UnitInvitation {
        $invitation = UnitInvitation::query()
            ->where(
                'token',
                $this->hashToken($rawToken)
            )
            ->firstOrFail();

        if (
            $invitation->status !== InvitationStatus::Accepted
            && $invitation->isExpired()
        ) {
            $invitation->update([
                'status' => InvitationStatus::Expired,
            ]);
        }

        $this->assertInvitationBelongsToUser(
            $invitation,
            $user
        );

        return $invitation;
    }

    private function deliver(
        UnitInvitation $invitation,
        string $rawToken
    ): void {
        $link = $this->acceptanceLink(
            $rawToken
        );

        $message = sprintf(
            'You have been invited to unit %s. Accept invitation: %s',
            $invitation->unit?->unit_number ?? $invitation->unit_id,
            $link
        );

        if (
            $invitation->channel === InvitationChannel::Sms
        ) {
            $this->sms->send(
                (string) $invitation->mobile,
                $message
            );
        } else {
            Mail::raw(
                $message,
                fn ($mail) => $mail
                    ->to((string) $invitation->email)
                    ->subject('Unit invitation')
            );
        }

        $invitation->update([
            'status' => InvitationStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    private function assertCanBeResent(
        UnitInvitation $invitation
    ): void {
        if (
            in_array(
                $invitation->status,
                [
                    InvitationStatus::Accepted,
                    InvitationStatus::Cancelled,
                    InvitationStatus::Expired,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'invitation' => [
                    'This invitation cannot be resent.',
                ],
            ]);
        }

        if ($invitation->isExpired()) {
            $invitation->update([
                'status' => InvitationStatus::Expired,
            ]);

            throw ValidationException::withMessages([
                'invitation' => [
                    'This invitation has expired.',
                ],
            ]);
        }
    }

    private function assertInvitationBelongsToUser(
        UnitInvitation $invitation,
        User $user
    ): void {
        $mobileMatches = filled($invitation->mobile)
            && filled($user->mobile)
            && $this->normalizeMobile($invitation->mobile)
                === $this->normalizeMobile($user->mobile);

        $emailMatches = filled($invitation->email)
            && filled($user->email)
            && mb_strtolower(trim($invitation->email))
                === mb_strtolower(trim($user->email));

        if (! $mobileMatches && ! $emailMatches) {
            throw new AuthorizationException(
                'This invitation does not belong to the authenticated user.'
            );
        }
    }

    private function assertContactMatchesChannel(
        array $data
    ): void {
        if (
            $data['channel'] === InvitationChannel::Sms->value
            && blank($data['mobile'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'mobile' => [
                    'A mobile number is required for SMS invitations.',
                ],
            ]);
        }

        if (
            $data['channel'] === InvitationChannel::Email->value
            && blank($data['email'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'An email address is required for email invitations.',
                ],
            ]);
        }
    }

    private function assertNoActiveDuplicate(
        Unit $unit,
        array $data
    ): void {
        $query = UnitInvitation::query()
            ->where('unit_id', $unit->getKey())
            ->where(
                'relation_type',
                $data['relation_type']
            )
            ->whereIn(
                'status',
                [
                    InvitationStatus::Pending->value,
                    InvitationStatus::Sent->value,
                ]
            )
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere(
                        'expires_at',
                        '>',
                        now()
                    );
            });

        if (
            $data['channel'] === InvitationChannel::Sms->value
        ) {
            $query->where(
                'mobile',
                $data['mobile']
            );
        } else {
            $query->where(
                'email',
                $data['email']
            );
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'invitation' => [
                    'An active invitation already exists for this unit and recipient.',
                ],
            ]);
        }
    }

    private function normalizeContactData(
        array $data
    ): array {
        if (array_key_exists('mobile', $data)) {
            $data['mobile'] = $this->normalizeMobile(
                $data['mobile']
            );
        }

        if (array_key_exists('email', $data)) {
            $data['email'] = mb_strtolower(
                trim((string) $data['email'])
            );
        }

        return $data;
    }

    private function normalizeMobile(
        ?string $mobile
    ): string {
        return preg_replace(
            '/\s+/',
            '',
            trim((string) $mobile)
        ) ?? '';
    }

    private function acceptanceLink(
        string $rawToken
    ): string {
        $base = rtrim(
            (string) config('app.url'),
            '/'
        );

        return sprintf(
            '%s/invitations/accept?token=%s',
            $base,
            urlencode($rawToken)
        );
    }

    private function newRawToken(): string
    {
        return Str::random(64);
    }

    private function hashToken(
        string $rawToken
    ): string {
        return hash(
            'sha256',
            $rawToken
        );
    }
}
