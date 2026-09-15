<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HospitalWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'account_number',
        'balance',
        'total_received',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_received' => 'decimal:2',
    ];

    /**
     * Transactions credited to the hospital wallet.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->latest();
    }

    /**
     * Formatted balance in Naira.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return '₦'.number_format((float) $this->balance, 2);
    }

    /**
     * Formatted total received in Naira.
     */
    public function getFormattedTotalReceivedAttribute(): string
    {
        return '₦'.number_format((float) $this->total_received, 2);
    }

    /**
     * Retrieve or create the primary singleton hospital wallet.
     */
    public static function getSingleton(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Hospital Main Wallet',
                'account_number' => 'HSP-MAIN-001',
                'balance' => 0.00,
                'total_received' => 0.00,
            ]
        );
    }
}
