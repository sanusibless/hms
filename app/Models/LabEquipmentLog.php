<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabEquipmentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_name',
        'serial_number',
        'department',
        'status',
        'last_calibrated_at',
        'next_calibration_due',
        'notes',
        'logged_by',
    ];

    protected $casts = [
        'last_calibrated_at' => 'date',
        'next_calibration_due' => 'date',
    ];

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
