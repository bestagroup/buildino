<?php

namespace App\Models;

use App\Enums\UserGender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Concerns\HasNotificationRelations;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes , HasApiTokens , HasNotificationRelations;

    protected $table = 'users';

    protected $fillable = [
        'first_name',
        'last_name',
        'national_code',
        'mobile',
        'email',
        'mobile_verified_at',
        'email_verified_at',
        'password',
        'avatar',
        'is_active',
        'is_blocked',
        'last_login_at',
        'last_login_ip',
        'remember_token',
    ];

    protected $hidden = [
        'remember_token',
        'password',
    ];

    protected function casts(): array
    {
        return [
            'mobile_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'is_blocked' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'gender' => UserGender::class,
        ];
    }

    public function userProfiles(): HasMany
    {
        return $this->hasMany(UserProfile::class, 'user_id');
    }

    public function userDevices(): HasMany
    {
        return $this->hasMany(UserDevice::class, 'user_id');
    }

    public function otpCodes(): HasMany
    {
        return $this->hasMany(OtpCode::class, 'user_id');
    }

    public function userSessions(): HasMany
    {
        return $this->hasMany(UserSession::class, 'user_id');
    }

    public function userRoleAssignmentsAsUser(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class, 'user_id');
    }

    public function userRoleAssignmentsAsAssignedBy(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class, 'assigned_by');
    }

    public function unitOwnershipsAsUser(): HasMany
    {
        return $this->hasMany(UnitOwnership::class, 'user_id');
    }

    public function unitOwnershipsAsCreatedBy(): HasMany
    {
        return $this->hasMany(UnitOwnership::class, 'created_by');
    }

    public function unitOwnershipsAsEndedBy(): HasMany
    {
        return $this->hasMany(UnitOwnership::class, 'ended_by');
    }

    public function unitOccupanciesAsUser(): HasMany
    {
        return $this->hasMany(UnitOccupancy::class, 'user_id');
    }

    public function unitOccupanciesAsCreatedBy(): HasMany
    {
        return $this->hasMany(UnitOccupancy::class, 'created_by');
    }

    public function unitOccupanciesAsEndedBy(): HasMany
    {
        return $this->hasMany(UnitOccupancy::class, 'ended_by');
    }

    public function unitInvitationsAsInvitedBy(): HasMany
    {
        return $this->hasMany(UnitInvitation::class, 'invited_by');
    }

    public function unitInvitationsAsAcceptedUser(): HasMany
    {
        return $this->hasMany(UnitInvitation::class, 'accepted_user_id');
    }

    public function guestVisits(): HasMany
    {
        return $this->hasMany(GuestVisit::class, 'registered_by');
    }

    public function guestAccessLogs(): HasMany
    {
        return $this->hasMany(GuestAccessLog::class, 'verified_by');
    }

    public function buildingSubscriptions(): HasMany
    {
        return $this->hasMany(BuildingSubscription::class, 'created_by');
    }

    public function facilityBlackouts(): HasMany
    {
        return $this->hasMany(FacilityBlackout::class, 'created_by');
    }

    public function chargePeriods(): HasMany
    {
        return $this->hasMany(ChargePeriod::class, 'created_by');
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'created_by');
    }

    public function unitInvoices(): HasMany
    {
        return $this->hasMany(UnitInvoice::class, 'created_by');
    }

    public function financialAdjustmentsAsCreatedBy(): HasMany
    {
        return $this->hasMany(FinancialAdjustment::class, 'created_by');
    }

    public function financialAdjustmentsAsApprovedBy(): HasMany
    {
        return $this->hasMany(FinancialAdjustment::class, 'approved_by');
    }

    public function paymentsAsPayerUser(): HasMany
    {
        return $this->hasMany(Payment::class, 'payer_user_id');
    }

    public function paymentsAsVerifiedBy(): HasMany
    {
        return $this->hasMany(Payment::class, 'verified_by');
    }

    public function buildingExpensesAsCreatedBy(): HasMany
    {
        return $this->hasMany(BuildingExpense::class, 'created_by');
    }

    public function buildingExpensesAsApprovedBy(): HasMany
    {
        return $this->hasMany(BuildingExpense::class, 'approved_by');
    }

    public function buildingIncomesAsCreatedBy(): HasMany
    {
        return $this->hasMany(BuildingIncome::class, 'created_by');
    }

    public function buildingIncomesAsApprovedBy(): HasMany
    {
        return $this->hasMany(BuildingIncome::class, 'approved_by');
    }

    public function financialAuditLogs(): HasMany
    {
        return $this->hasMany(FinancialAuditLog::class, 'user_id');
    }

    public function loyaltyRewardClaimsAsUser(): HasMany
    {
        return $this->hasMany(LoyaltyRewardClaim::class, 'user_id');
    }

    public function loyaltyRewardClaimsAsProcessedBy(): HasMany
    {
        return $this->hasMany(LoyaltyRewardClaim::class, 'processed_by');
    }

    public function facilityReservationsAsUser(): HasMany
    {
        return $this->hasMany(FacilityReservation::class, 'user_id');
    }

    public function facilityReservationsAsApprovedBy(): HasMany
    {
        return $this->hasMany(FacilityReservation::class, 'approved_by');
    }

    public function reservationCancellations(): HasMany
    {
        return $this->hasMany(ReservationCancellation::class, 'cancelled_by');
    }

    public function supportTicketsAsUser(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'user_id');
    }

    public function supportTicketsAsAssignedTo(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }

    public function supportMessages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'user_id');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'created_by');
    }

    public function announcementReceipts(): HasMany
    {
        return $this->hasMany(AnnouncementReceipt::class, 'user_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    public function userLoginHistories(): HasMany
    {
        return $this->hasMany(UserLoginHistory::class, 'user_id');
    }

    public function userAccessLogs(): HasMany
    {
        return $this->hasMany(UserAccessLog::class, 'user_id');
    }

    public function userPreferences(): HasMany
    {
        return $this->hasMany(UserPreference::class, 'user_id');
    }

    public function userNotificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class, 'user_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'uploaded_by');
    }

    public function fileDownloads(): HasMany
    {
        return $this->hasMany(FileDownload::class, 'user_id');
    }

    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'generated_by');
    }

    public function userDashboardWidgets(): HasMany
    {
        return $this->hasMany(UserDashboardWidget::class, 'user_id');
    }

    public function documentRecords(): HasMany
    {
        return $this->hasMany(DocumentRecord::class, 'created_by');
    }

    public function meetingMinutes(): HasMany
    {
        return $this->hasMany(MeetingMinute::class, 'created_by');
    }

    public function serviceRequestsAsRequestedBy(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'requested_by');
    }

    public function serviceRequestsAsAssignedTo(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'assigned_to');
    }

    public function accountingPeriods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class, 'closed_by');
    }

    public function financialReconciliationsAsCreatedBy(): HasMany
    {
        return $this->hasMany(FinancialReconciliation::class, 'created_by');
    }

    public function financialReconciliationsAsApprovedBy(): HasMany
    {
        return $this->hasMany(FinancialReconciliation::class, 'approved_by');
    }

    public function dashboardWidgets(): BelongsToMany
    {
        return $this->belongsToMany(DashboardWidget::class, 'user_dashboard_widgets')
            ->withPivot(['position', 'configuration'])
            ->withTimestamps();
    }

    public function userRoleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'user_role_assignments',
            'user_id',
            'role_id'
        )
            ->withPivot([
                'scope_type',
                'scope_id',
                'starts_at',
                'ends_at',
                'is_active',
                'assigned_by',
            ])
            ->withTimestamps();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()
            ->where('roles.name', $role)
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('user_role_assignments.starts_at')
                    ->orWhere('user_role_assignments.starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('user_role_assignments.ends_at')
                    ->orWhere('user_role_assignments.ends_at', '>=', now());
            })
            ->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()
            ->whereIn('roles.name', $roles)
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('user_role_assignments.starts_at')
                    ->orWhere('user_role_assignments.starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('user_role_assignments.ends_at')
                    ->orWhere('user_role_assignments.ends_at', '>=', now());
            })
            ->exists();
    }

    public function hasAllRoles(array $roles): bool
    {
        $roles = array_values(array_unique($roles));

        return $this->roles()
                ->whereIn('roles.name', $roles)
                ->distinct()
                ->count('roles.id') === count($roles);
    }

}
