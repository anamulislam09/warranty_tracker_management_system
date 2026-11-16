<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'account_id', 'name', 'email', 'password', 'mobile', 'country_code', 'language',
        'timezone', 'role', 'role_id', 'permissions', 'denied_permissions', 'status', 'email_verified_at', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'permissions'       => 'array',          // extra permissions (outside the role)
            'denied_permissions' => 'array',         // permissions turned off for this user
        ];
    }

    public function account() { return $this->belongsTo(Account::class); }
    public function notifications() { return $this->hasMany(UserNotification::class); }
    public function assignedRole() { return $this->belongsTo(Role::class, 'role_id'); }

    public function isSuperAdmin(): bool { return $this->role === 'super_admin'; }
    public function isOwner(): bool { return $this->role === 'owner'; }

    /**
     * What this user may do (only for users that a customer created):
     *   role permissions + extra permissions - permissions the customer turned off,
     * limited to active permissions. The super admin and the customer owner never use this: they can do everything.
     */
    public function effectivePermissions(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        $slugs = $this->permissions ?? [];

        $role = $this->assignedRole;
        if ($role && $role->is_active) {
            $slugs = array_merge($slugs, $role->permissions()->pluck('slug')->all());
        }

        $slugs = array_diff($slugs, $this->denied_permissions ?? []);     // turned off

        $active = Permission::where('is_active', true)->pluck('slug')->all();

        return $this->permissionCache = array_values(array_intersect(array_unique($slugs), $active));
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin() || $this->isOwner()) {
            return true;
        }
        return in_array($permission, $this->effectivePermissions(), true);
    }

    public function unreadCount(): int
    {
        return $this->notifications()->whereNull('read_at')->count();
    }
}
