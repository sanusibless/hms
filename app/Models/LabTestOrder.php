<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabTestOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'patient_id',
        'doctor_id',
        'service_id',
        'test_name',
        'clinical_notes',
        'priority',
        'status',
        'sample_id',
        'sample_type',
        'sample_collected_at',
        'sample_collected_by',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'sample_collected_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(HospitalService::class, 'service_id');
    }

    public function sampleCollector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sample_collected_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class);
    }

    public static function generateOrderNumber(): string
    {
        do {
            $num = 'LAB-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('order_number', $num)->exists());

        return $num;
    }

    public static function generateSampleBarcode(): string
    {
        return 'SMP-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }
}
