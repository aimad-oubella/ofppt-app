<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'administrateur';
    public const ROLE_MANAGER = 'gestionnaire';
    public const ROLE_VIEWER = 'consultation';
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_MANAGER, self::ROLE_VIEWER];

    protected $fillable = ['name', 'email', 'password', 'role', 'establishment_id', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function canWrite(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER], true);
    }
}
