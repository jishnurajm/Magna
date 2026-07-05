<?php

declare(strict_types=1);

namespace Magna\Users;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Magna\Auth\Concerns\HasRoles;

/**
 * @property UserStatus $status
 * @property Carbon|null $email_verified_at
 * @property string|null $avatar_path
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 */
#[Fillable(['name', 'email', 'password', 'status', 'widget_order', 'avatar_path'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements HasAvatar, MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUlids;
    use Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'two_factor_confirmed_at' => 'datetime',
            'widget_order' => 'array',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Avatar shown in the Filament user menu and topbar. Returns null when no
     * photo is set so Filament falls back to the generated initials avatar.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->avatar_path === null || $this->avatar_path === '') {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
