<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrescriptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_id',
        'drug_id',
        'drug_name',
        'dosage',
        'frequency',
        'duration',
        'route',
        'instructions',
        'quantity',
        'unit_price',
        'is_dispensed',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'is_dispensed' => 'boolean',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function mars(): HasMany
    {
        return $this->hasMany(MedicationAdministrationRecord::class);
    }

    public function getTotalPriceAttribute(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }
}
