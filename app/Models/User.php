<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
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
        'status',
    ];

    /**
     * The attributes that should be hidden
     * for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'mobile_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'is_blocked' => 'boolean',
            'status' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Full name of user.
     */
    public function getFullNameAttribute(): string
    {
        return trim(
            $this->first_name . ' ' . $this->last_name
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Roles & Permissions
    |--------------------------------------------------------------------------
    */

    /**
     * User roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'user_roles',
            'user_id',
            'role_id'
        )->withTimestamps();
    }

    /**
     * Check whether user has a role.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()
            ->where('name', $role)
            ->exists();
    }

    /**
     * Check whether user has any of given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()
            ->whereIn('name', $roles)
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | User Activity
    |--------------------------------------------------------------------------
    */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(
            UserActivityLog::class
        );
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(
            UserLoginHistory::class
        );
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(
            UserAccessLog::class
        );
    }

    public function preference(): HasOne
    {
        return $this->hasOne(
            UserPreference::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Building / Residence
    |--------------------------------------------------------------------------
    */
    public function unitResidents(): HasMany
    {
        return $this->hasMany(
            UnitResident::class
        );
    }
    public function residentHistories(): HasMany
    {
        return $this->hasMany(
            ResidentHistory::class
        );
    }

    public function unitInvitations(): HasMany
    {
        return $this->hasMany(
            UnitInvitation::class
        );
    }

    public function guests(): HasMany
    {
        return $this->hasMany(
            UnitGuest::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Facility Reservations
    |--------------------------------------------------------------------------
    */

    public function facilityReservations(): HasMany
    {
        return $this->hasMany(
            FacilityReservation::class
        );
    }

    public function reservationApprovals(): HasMany
    {
        return $this->hasMany(
            FacilityReservation::class,
            'approved_by'
        );
    }

    public function reservationCancellations(): HasMany
    {
        return $this->hasMany(
            ReservationCancellation::class,
            'cancelled_by'
        );
    }

    public function reservationNotifications(): HasMany
    {
        return $this->hasMany(
            ReservationNotification::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Financial
    |--------------------------------------------------------------------------
    */

    public function payments(): HasMany
    {
        return $this->hasMany(
            Payment::class
        );
    }

    public function financialExpenses(): HasMany
    {
        return $this->hasMany(
            BuildingExpense::class,
            'created_by'
        );
    }

    public function financialIncomes(): HasMany
    {
        return $this->hasMany(
            BuildingIncome::class,
            'created_by'
        );
    }

    public function financialAdjustments(): HasMany
    {
        return $this->hasMany(
            FinancialAdjustment::class,
            'created_by'
        );
    }

    public function financialAuditLogs(): HasMany
    {
        return $this->hasMany(
            FinancialAuditLog::class
        );
    }

    public function invoicePaymentHistories(): HasMany
    {
        return $this->hasMany(
            InvoicePaymentHistory::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Loyalty
    |--------------------------------------------------------------------------
    */

    public function loyaltyAccount(): HasOne
    {
        return $this->hasOne(
            LoyaltyAccount::class
        );
    }

    public function loyaltyRewardClaims(): HasMany
    {
        return $this->hasMany(
            LoyaltyRewardClaim::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Support
    |--------------------------------------------------------------------------
    */

    public function supportTickets(): HasMany
    {
        return $this->hasMany(
            SupportTicket::class
        );
    }

    public function supportMessages(): HasMany
    {
        return $this->hasMany(
            SupportMessage::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    public function announcements(): BelongsToMany
    {
        return $this->belongsToMany(
            Announcement::class,
            'announcement_receipts',
            'user_id',
            'announcement_id'
        )
            ->withPivot('read_at')
            ->withTimestamps();
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(
            NotificationLog::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Files
    |--------------------------------------------------------------------------
    */

    public function files(): HasMany
    {
        return $this->hasMany(
            File::class,
            'uploaded_by'
        );
    }

    public function fileDownloads(): HasMany
    {
        return $this->hasMany(
            FileDownload::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    public function generatedReports(): HasMany
    {
        return $this->hasMany(
            GeneratedReport::class,
            'generated_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->is_active === true
            && $this->is_blocked === false;
    }

    public function isBlocked(): bool
    {
        return $this->is_blocked === true;
    }

    public function isMobileVerified(): bool
    {
        return $this->mobile_verified_at !== null;
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Role Helpers
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isExpert(): bool
    {
        return $this->hasRole('expert');
    }

    public function isOperator(): bool
    {
        return $this->hasRole('operator');
    }

    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    public function isTenant(): bool
    {
        return $this->hasRole('tenant');
    }

    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    public function isServiceStaff(): bool
    {
        return $this->hasRole('service');
    }
    public function createdResidentHistories(): HasMany
    {
        return $this->hasMany(
            ResidentHistory::class,
            'created_by'
        );
    }

    public function sentUnitInvitations(): HasMany
    {
        return $this->hasMany(
            UnitInvitation::class,
            'invited_by'
        );
    }

    public function acceptedUnitInvitations(): HasMany
    {
        return $this->hasMany(
            UnitInvitation::class,
            'accepted_user_id'
        );
    }

    public function registeredGuests(): HasMany
    {
        return $this->hasMany(
            UnitGuest::class,
            'registered_by'
        );
    }
}
