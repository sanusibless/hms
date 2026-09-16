<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'patient_id',
        'subtotal',
        'discount_amount',
        'waiver_amount',
        'total_amount',
        'paid_amount',
        'balance_due',
        'status',
        'payment_method',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'waiver_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function insuranceClaim(): HasOne
    {
        return $this->hasOne(InsuranceClaim::class);
    }

    public static function generateInvoiceNumber(): string
    {
        do {
            $num = 'INV-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('invoice_number', $num)->exists());

        return $num;
    }

    public function getFormattedTotalAttribute(): string
    {
        return '₦' . number_format((float) $this->total_amount, 2);
    }

    public function getFormattedBalanceAttribute(): string
    {
        return '₦' . number_format((float) $this->balance_due, 2);
    }
}
