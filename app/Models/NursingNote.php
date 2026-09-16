<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NursingNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'nurse_id',
        'note_type',
        'note',
        'patient_status',
        'is_flagged_to_doctor',
    ];

    protected $casts = [
        'is_flagged_to_doctor' => 'boolean',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }
}
