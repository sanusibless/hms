<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_number',
        'patient_id',
        'doctor_id',
        'department',
        'scheduled_at',
        'status',
        'priority',
        'queue_number',
        'reason',
        'notes',
        'booked_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'queue_number' => 'integer',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public static function generateAppointmentNumber(): string
    {
        do {
            $num = 'APT-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('appointment_number', $num)->exists());

        return $num;
    }
}
