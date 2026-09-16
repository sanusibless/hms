<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ward extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'department',
        'type',
        'capacity',
        'daily_rate',
        'is_active',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'daily_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function getOccupiedBedsCountAttribute(): int
    {
        return $this->beds()->where('status', 'occupied')->count();
    }

    public function getAvailableBedsCountAttribute(): int
    {
        return $this->beds()->where('status', 'available')->count();
    }

    public function getOccupancyRateAttribute(): float
    {
        $total = $this->beds()->count();
        if ($total === 0) {
            return 0.0;
        }
        return round(($this->occupied_beds_count / $total) * 100, 1);
    }
}
