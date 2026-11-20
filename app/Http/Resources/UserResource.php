<?php

namespace App\Http\Resources;

use App\Models\Permission;
use Illuminate\Http\Resources\Json\JsonResource;

/** The logged-in user. "permissions" is everything the app may show: the owner gets all of them. */
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        $u = $this->resource;
        $role = $u->isSuperAdmin() ? 'super_admin' : $u->role;          // super_admin | owner | member
        $permissions = ($u->isOwner() || $u->isSuperAdmin())
            ? Permission::where('is_active', true)->pluck('slug')->values()->all()
            : $u->effectivePermissions();

        return [
            'id'          => $u->id,
            'name'        => $u->name,
            'email'       => $u->email,
            'mobile'      => $u->mobile,
            'role'        => $role,                                                 // super_admin | owner | member
            'role_name'   => $u->isSuperAdmin() ? 'Super Admin' : ($u->isOwner() ? 'Owner' : optional($u->assignedRole)->name),
            'is_owner'    => $u->isOwner(),
            'is_super_admin' => $u->isSuperAdmin(),
            'country_code' => $u->country_code,
            'language'    => $u->language,
            'timezone'    => $u->timezone,
            'status'      => $u->status,
            'permissions' => $permissions,
            'last_login_at' => optional($u->last_login_at)->toIso8601String(),
        ];
    }
}
