<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property string|null $avatar
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'avatar'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the user can manage other users.
     */
    public function canManageUsers(): bool
    {
        try {
            return $this->hasPermissionTo('manage users');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    /**
     * Determine whether the user can manage roles.
     */
    public function canManageRoles(): bool
    {
        try {
            return $this->hasPermissionTo('manage roles');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    /**
     * Determine whether the user can view the activity log.
     */
    public function canViewActivityLog(): bool
    {
        try {
            return $this->hasPermissionTo('manage activity log');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    /**
     * Get the user's avatar URL.
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value ? Storage::url($value) : null,
        );
    }

    /**
     * Get the file shares this user has received.
     *
     * @return HasMany<FileShare, $this>
     */
    public function sharedFiles(): HasMany
    {
        return $this->hasMany(FileShare::class, 'shared_with');
    }

    /**
     * Get the file shares this user has sent.
     *
     * @return HasMany<FileShare, $this>
     */
    public function sentFileShares(): HasMany
    {
        return $this->hasMany(FileShare::class, 'shared_by');
    }

    /**
     * Get the user's notification preferences.
     *
     * @return HasMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Configure which attributes the activity log records for this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'role'])
            ->useLogName('user')
            ->dontLogIfAttributesChangedOnly(['updated_at', 'remember_token'])
            ->setDescriptionForEvent(function (string $eventName): string {
                return match ($eventName) {
                    'created' => 'created user "'.$this->name.'"',
                    'updated' => $this->describeUserUpdate(),
                    'deleted' => 'deleted user "'.$this->name.'"',
                    default => $eventName,
                };
            });
    }

    /**
     * Build a description listing each changed attribute and its old/new value.
     */
    protected function describeUserUpdate(): string
    {
        $changes = array_intersect_key($this->getChanges(), array_flip(['name', 'email', 'role']));

        if ($changes === []) {
            return 'updated user "'.$this->name.'"';
        }

        $parts = [];

        foreach ($changes as $attribute => $newValue) {
            $oldValue = $this->oldAttributes[$attribute] ?? null;

            $parts[] = sprintf(
                '%s from %s to %s',
                $attribute,
                $oldValue ?? 'none',
                $newValue ?? 'none',
            );
        }

        return 'updated user "'.$this->name.'": '.implode(', ', $parts);
    }
}
