<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'account_number',
        'balance',
        'status',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    /**
     * The patient that owns this wallet.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Transactions on this wallet.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->latest();
    }

    /**
     * Get formatted balance in Naira.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return '₦'.number_format((float) $this->balance, 2);
    }

    /**
     * Generate a unique 10-digit account number (matching PDF example: 0003453125).
     */
    public static function generateAccountNumber(): string
    {
        do {
            // 10 digits starting with 000
            $number = '000'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
        } while (static::where('account_number', $number)->exists());

        return $number;
    }
}
