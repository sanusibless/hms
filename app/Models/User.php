<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'first_name', 'last_name', 'username', 'email', 'phone_number', 'role', 'department', 'is_active', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public const ROLE_ADMIN = 'admin';
    public const ROLE_DOCTOR = 'doctor';
    public const ROLE_NURSE = 'nurse';
    public const ROLE_LAB_TECH = 'lab_tech';
    public const ROLE_STAFF = 'staff';

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user is a doctor.
     */
    public function isDoctor(): bool
    {
        return $this->role === self::ROLE_DOCTOR;
    }

    /**
     * Check if user is a nurse.
     */
    public function isNurse(): bool
    {
        return $this->role === self::ROLE_NURSE;
    }

    /**
     * Check if user is a laboratory technician.
     */
    public function isLabTech(): bool
    {
        return in_array($this->role, [self::ROLE_LAB_TECH, 'lab_technician']);
    }

    /**
     * Check if user is staff (or admin).
     */
    public function isStaff(): bool
    {
        return in_array($this->role, [self::ROLE_STAFF, self::ROLE_ADMIN]);
    }

    /**
     * Check if user has any of the given roles.
     *
     * @param string|array<string> $roles
     */
    public function hasRole(string|array $roles): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $roles = (array) $roles;
        return in_array($this->role, $roles, true);
    }

    /**
     * Transactions processed by this user.
     */
    public function processedTransactions()
    {
        return $this->hasMany(Transaction::class, 'processed_by');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name ?: ($this->first_name.' '.$this->last_name), true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }
}
