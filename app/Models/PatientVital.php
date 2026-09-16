<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientVital extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'recorded_by',
        'blood_pressure',
        'temperature',
        'pulse_rate',
        'respiratory_rate',
        'spo2',
        'weight',
        'height',
        'bmi',
        'blood_sugar',
        'status_flag',
        'notes',
        'recorded_at',
    ];

    protected $casts = [
        'temperature' => 'float',
        'pulse_rate' => 'integer',
        'respiratory_rate' => 'integer',
        'spo2' => 'integer',
        'weight' => 'float',
        'height' => 'float',
        'bmi' => 'float',
        'blood_sugar' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
