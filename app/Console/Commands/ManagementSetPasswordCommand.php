<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ManagementSetPasswordCommand extends Command
{
    protected $signature =
        'management:set-password
        {login : Mobile number or email address}';

    protected $description =
        'Securely set a management user password interactively';

    public function handle(): int
    {
        $login = trim(
            (string) $this->argument(
                'login'
            )
        );

        $column = filter_var(
            $login,
            FILTER_VALIDATE_EMAIL
        )
            ? 'email'
            : 'mobile';

        $user = User::query()
            ->where(
                $column,
                $login
            )
            ->first();

        if (! $user) {
            $this->error(
                'User was not found.'
            );

            return self::FAILURE;
        }

        $password =
            (string) $this->secret(
                'رمز عبور جدید'
            );

        if (
            mb_strlen(
                $password
            ) < 8
        ) {
            $this->error(
                'رمز عبور باید حداقل ۸ کاراکتر باشد.'
            );

            return self::FAILURE;
        }

        $confirmation =
            (string) $this->secret(
                'تکرار رمز عبور جدید'
            );

        if (
            ! hash_equals(
                $password,
                $confirmation
            )
        ) {
            $this->error(
                'تکرار رمز عبور یکسان نیست.'
            );

            return self::FAILURE;
        }

        DB::transaction(
            function () use (
                $user,
                $password
            ): void {
                $user->forceFill([
                    'password' =>
                        Hash::make(
                            $password
                        ),

                    'remember_token' =>
                        Str::random(
                            60
                        ),
                ])->save();

                if (
                    Schema::hasTable(
                        'password_reset_tokens'
                    )
                    && $user->email
                ) {
                    DB::table(
                        'password_reset_tokens'
                    )
                        ->where(
                            'email',
                            $user->email
                        )
                        ->delete();
                }
            }
        );

        $this->newLine();

        $this->info(
            'Management password updated successfully.'
        );

        $this->line(
            'User: '
            . trim(
                "{$user->first_name} {$user->last_name}"
            )
        );

        $this->line(
            'Login: '
            . $login
        );

        return self::SUCCESS;
    }
}
