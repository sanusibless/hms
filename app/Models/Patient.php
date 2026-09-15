<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone_number',
        'email',
        'file_number',
        'date_of_birth',
        'gender',
        'created_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    /**
     * Get the patient's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the wallet associated with the patient.
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get all transactions for this patient.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->latest();
    }

    /**
     * User who registered the patient.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Auto-assign wallet upon patient creation if not already present.
     */
    protected static function booted(): void
    {
        static::created(function (Patient $patient) {
            if (! $patient->wallet()->exists()) {
                $patient->wallet()->create([
                    'account_number' => Wallet::generateAccountNumber(),
                    'balance' => 0.00,
                    'status' => 'active',
                ]);
            }
        });
    }
}
