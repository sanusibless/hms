<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientHealthRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'nurse_id',
        'visit_type',
        'icd_code',
        'chief_complaint',
        'diagnosis',
        'treatment',
        'treatment_plan',
        'clinical_notes',
        'notes',
        'medications',
        'allergies',
        'chronic_conditions',
        'version',
        'last_appointment',
        'next_appointment',
    ];

    protected $casts = [
        'last_appointment' => 'datetime',
        'next_appointment' => 'datetime',
        'version' => 'integer',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PatientRecordAttachment::class, 'health_record_id');
    }
}
