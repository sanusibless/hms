<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'patient_id',
        'wallet_id',
        'hospital_wallet_id',
        'service_id',
        'service_name',
        'type', // 'credit', 'debit'
        'amount',
        'patient_balance_after',
        'hospital_balance_after',
        'description',
        'reference',
        'status', // 'completed', 'failed', 'pending'
        'processed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'patient_balance_after' => 'decimal:2',
        'hospital_balance_after' => 'decimal:2',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function hospitalWallet(): BelongsTo
    {
        return $this->belongsTo(HospitalWallet::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(HospitalService::class, 'service_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Signed formatted amount e.g. +₦50,000.00 or -₦20,000.00.
     */
    public function getSignedAmountAttribute(): string
    {
        $sign = $this->type === 'credit' ? '+' : '-';

        return "{$sign}₦".number_format((float) $this->amount, 2);
    }

    /**
     * Formatted amount without sign.
     */
    public function getFormattedAmountAttribute(): string
    {
        return '₦'.number_format((float) $this->amount, 2);
    }

    /**
     * Formatted patient balance after transaction.
     */
    public function getFormattedPatientBalanceAttribute(): ?string
    {
        return $this->patient_balance_after !== null
            ? '₦'.number_format((float) $this->patient_balance_after, 2)
            : null;
    }

    /**
     * Generate unique transaction ID.
     */
    public static function generateTransactionId(): string
    {
        do {
            $id = 'TXN-'.date('Ymd').'-'.strtoupper(Str::random(6));
        } while (static::where('transaction_id', $id)->exists());

        return $id;
    }
}
