<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $email
 * @property bool        $is_admin
 * @property int|null    $current_company_id
 */
#[Fillable(['name', 'email', 'password', 'current_company_id', 'is_admin'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'password'                => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    public function hasCompany(): bool
    {
        return $this->current_company_id !== null;
    }

    public function memberCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_users')
            ->withPivot('role', 'joined_at', 'is_active')
            ->withTimestamps();
    }

    public function roleInCompany(int $companyId): string
    {
        // Owner gets 'owner' role
        if ($this->companies()->where('id', $companyId)->exists()) {
            return 'owner';
        }
        $pivot = $this->memberCompanies()
            ->where('company_id', $companyId)
            ->first()?->pivot;
        return $pivot?->role ?? 'viewer';
    }
}
