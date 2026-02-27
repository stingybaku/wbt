<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    public const MANAGE_ROLES = 'roles.manage';
    public const MANAGE_USERS = 'users.manage';
    public const ACCESS_ADMIN_PANEL = 'admin.access';
    public const ACCESS_FRONTEND_PORTAL = 'frontend.access';

    public const PERMISSION_OPTIONS = [
        self::ACCESS_ADMIN_PANEL => 'Access admin portal',
        self::ACCESS_FRONTEND_PORTAL => 'Access frontend portal',
        self::MANAGE_USERS => 'Manage users',
        self::MANAGE_ROLES => 'Manage roles and permissions',
    ];

    protected $fillable = [
        'name',
        'slug',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    public static function getPermissionOptions(): array
    {
        return self::PERMISSION_OPTIONS;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
