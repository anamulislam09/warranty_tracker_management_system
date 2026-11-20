<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** A user of the customer, as the owner sees him: role, extra permissions and turned-off permissions. */
class TeamUserResource extends JsonResource
{
    public function toArray($request): array
    {
        $u = $this->resource;

        return [
            'id'                 => $u->id,
            'name'               => $u->name,
            'email'              => $u->email,
            'mobile'             => $u->mobile,
            'status'             => $u->status,
            'role'               => $u->role,                    // owner | member
            'is_owner'           => $u->isOwner(),
            'role_id'            => $u->role_id,
            'role_name'          => $u->isOwner() ? 'Owner' : optional($u->assignedRole)->name,
            'permissions'        => $u->isOwner() ? null : $u->effectivePermissions(),   // what the user can do now
            'extra_permissions'  => $u->permissions ?? [],       // given on top of the role
            'denied_permissions' => $u->denied_permissions ?? [], // taken away from the role
            'last_login_at'      => optional($u->last_login_at)->toIso8601String(),
            'created_at'         => optional($u->created_at)->toIso8601String(),
        ];
    }
}
