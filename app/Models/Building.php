<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Building extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'buildings';

    protected $fillable = [
        'complex_id',
        'code',
        'title',
        'building_number',
        'address',
        'postal_code',
        'latitude',
        'longitude',
        'timezone',
        'currency',
        'floors_count',
        'units_count',
        'parking_count',
        'storage_count',
        'construction_year',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'floors_count' => 'integer',
            'units_count' => 'integer',
            'parking_count' => 'integer',
            'storage_count' => 'integer',
            'construction_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function complex(): BelongsTo
    {
        return $this->belongsTo(Complex::class, 'complex_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'building_id');
    }

    public function parkingSpaces(): HasMany
    {
        return $this->hasMany(ParkingSpace::class, 'building_id');
    }

    public function storageUnits(): HasMany
    {
        return $this->hasMany(StorageUnit::class, 'building_id');
    }

    public function buildingEmergencyContacts(): HasMany
    {
        return $this->hasMany(BuildingEmergencyContact::class, 'building_id');
    }

    public function buildingRules(): HasMany
    {
        return $this->hasMany(BuildingRule::class, 'building_id');
    }

    public function buildingSubscriptions(): HasMany
    {
        return $this->hasMany(BuildingSubscription::class, 'building_id');
    }

    public function buildingFeatures(): HasMany
    {
        return $this->hasMany(BuildingFeature::class, 'building_id');
    }

    public function buildingFacilities(): HasMany
    {
        return $this->hasMany(BuildingFacility::class, 'building_id');
    }

    public function financialCategories(): HasMany
    {
        return $this->hasMany(FinancialCategory::class, 'building_id');
    }

    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class, 'building_id');
    }

    public function funds(): HasMany
    {
        return $this->hasMany(Fund::class, 'building_id');
    }

    public function chargeFormulas(): HasMany
    {
        return $this->hasMany(ChargeFormula::class, 'building_id');
    }

    public function chargePeriods(): HasMany
    {
        return $this->hasMany(ChargePeriod::class, 'building_id');
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'building_id');
    }

    public function unitInvoices(): HasMany
    {
        return $this->hasMany(UnitInvoice::class, 'building_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'building_id');
    }

    public function buildingExpenses(): HasMany
    {
        return $this->hasMany(BuildingExpense::class, 'building_id');
    }

    public function buildingIncomes(): HasMany
    {
        return $this->hasMany(BuildingIncome::class, 'building_id');
    }

    public function loyaltyRules(): HasMany
    {
        return $this->hasMany(LoyaltyRule::class, 'building_id');
    }

    public function loyaltyRewards(): HasMany
    {
        return $this->hasMany(LoyaltyReward::class, 'building_id');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'building_id');
    }

    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'building_id');
    }

    public function meetingMinutes(): HasMany
    {
        return $this->hasMany(MeetingMinute::class, 'building_id');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'building_id');
    }

    public function accountingPeriods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class, 'building_id');
    }

    public function financialReconciliations(): HasMany
    {
        return $this->hasMany(FinancialReconciliation::class, 'building_id');
    }

    public function fileRelations(): MorphMany
    {
        return $this->morphMany(FileRelation::class, 'related');
    }
}
