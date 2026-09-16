<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationAdministrationRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_item_id',
        'patient_id',
        'nurse_id',
        'scheduled_time',
        'administered_at',
        'dose_administered',
        'status',
        'refusal_reason',
        'notes',
    ];

    protected $casts = [
        'scheduled_time' => 'datetime',
        'administered_at' => 'datetime',
    ];

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }
}
