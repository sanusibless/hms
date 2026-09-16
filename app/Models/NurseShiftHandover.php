<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NurseShiftHandover extends Model
{
    use HasFactory;

    protected $fillable = [
        'outgoing_nurse_id',
        'incoming_nurse_id',
        'ward_id',
        'shift_type',
        'handover_date',
        'total_patients',
        'critical_patients_count',
        'summary_notes',
    ];

    protected $casts = [
        'handover_date' => 'date',
        'total_patients' => 'integer',
        'critical_patients_count' => 'integer',
    ];

    public function outgoingNurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'outgoing_nurse_id');
    }

    public function incomingNurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'incoming_nurse_id');
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }
}
