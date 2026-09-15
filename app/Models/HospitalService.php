<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HospitalService extends Model
{
    use HasFactory;

    protected $table = 'services';

    protected $fillable = [
        'name',
        'department',
        'default_amount',
        'description',
        'is_active',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Scope for active services.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Formatted default amount.
     */
    public function getFormattedDefaultAmountAttribute(): ?string
    {
        return $this->default_amount !== null
            ? '₦'.number_format((float) $this->default_amount, 2)
            : null;
    }
}
