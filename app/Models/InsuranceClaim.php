<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'claim_number',
        'patient_id',
        'invoice_id',
        'provider_name',
        'policy_number',
        'claim_amount',
        'approved_amount',
        'status',
        'submission_date',
        'resolution_date',
        'notes',
        'handled_by',
    ];

    protected $casts = [
        'claim_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'submission_date' => 'date',
        'resolution_date' => 'date',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public static function generateClaimNumber(): string
    {
        do {
            $num = 'CLM-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('claim_number', $num)->exists());

        return $num;
    }
}
