<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Drug extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'generic_name',
        'category',
        'dosage_form',
        'strength',
        'stock_quantity',
        'reorder_level',
        'unit_price',
        'expiry_date',
        'status',
    ];

    protected $casts = [
        'stock_quantity' => 'integer',
        'reorder_level' => 'integer',
        'unit_price' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function prescriptionItems(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->stock_quantity <= $this->reorder_level;
    }

    public function getFormattedPriceAttribute(): string
    {
        return '₦' . number_format((float) $this->unit_price, 2);
    }
}
