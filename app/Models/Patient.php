<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone_number',
        'email',
        'file_number',
        'date_of_birth',
        'gender',
        'address',
        'blood_group',
        'genotype',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'insurance_provider',
        'insurance_policy_number',
        'insurance_coverage_type',
        'allergies',
        'chronic_conditions',
        'admission_status',
        'current_ward_id',
        'current_bed_id',
        'admitted_at',
        'discharged_at',
        'created_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'admitted_at' => 'datetime',
        'discharged_at' => 'datetime',
    ];

    /**
     * Get the patient's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the wallet associated with the patient.
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get all transactions for this patient.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->latest();
    }

    /**
     * User who registered the patient.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Patient health records (EHR).
     */
    public function healthRecords(): HasMany
    {
        return $this->hasMany(PatientHealthRecord::class)->latest();
    }

    /**
     * Patient vital signs history.
     */
    public function vitals(): HasMany
    {
        return $this->hasMany(PatientVital::class)->latest('recorded_at');
    }

    /**
     * Patient appointments.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->latest('scheduled_at');
    }

    /**
     * Patient admissions (ADT).
     */
    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class)->latest('admission_date');
    }

    /**
     * Current Ward.
     */
    public function currentWard(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'current_ward_id');
    }

    /**
     * Current Bed.
     */
    public function currentBed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'current_bed_id');
    }

    /**
     * Lab test orders.
     */
    public function labOrders(): HasMany
    {
        return $this->hasMany(LabTestOrder::class)->latest();
    }

    /**
     * Prescriptions.
     */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class)->latest();
    }

    /**
     * Invoices.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest();
    }

    /**
     * Insurance claims.
     */
    public function insuranceClaims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class)->latest('submission_date');
    }

    /**
     * Attachments.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(PatientRecordAttachment::class)->latest();
    }

    /**
     * Auto-assign wallet upon patient creation if not already present.
     */
    protected static function booted(): void
    {
        static::created(function (Patient $patient) {
            if (! $patient->wallet()->exists()) {
                $patient->wallet()->create([
                    'account_number' => Wallet::generateAccountNumber(),
                    'balance' => 0.00,
                    'status' => 'active',
                ]);
            }
        });
    }
}
