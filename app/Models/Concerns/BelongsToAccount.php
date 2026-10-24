<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Multi-tenant isolation: every query on a model using this trait is
 * automatically limited to the logged-in user's account (customer).
 * The super admin sees everything.
 */
trait BelongsToAccount
{
    protected static function bootBelongsToAccount(): void
    {
        static::addGlobalScope('account', function (Builder $builder) {
            $user = auth()->user();
            if (! $user || $user->isSuperAdmin()) {
                return;
            }
            $builder->where($builder->getModel()->getTable() . '.account_id', $user->account_id ?? 0);
        });

        static::creating(function ($model) {
            if (! $model->account_id && auth()->check() && auth()->user()->account_id) {
                $model->account_id = auth()->user()->account_id;
            }
        });
    }
}
