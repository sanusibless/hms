<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_number',
        'patient_id',
        'doctor_id',
        'diagnosis',
        'status',
        'notes',
        'dispensed_by',
        'dispensed_at',
    ];

    protected $casts = [
        'dispensed_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function dispenser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public static function generatePrescriptionNumber(): string
    {
        do {
            $num = 'RX-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('prescription_number', $num)->exists());

        return $num;
    }
}
