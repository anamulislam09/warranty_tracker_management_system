<?php

namespace App\Support;

use App\Models\Account;

/** How much of the plan a customer has used: products + documents, storage, users. Shown on the dashboard and the product list. */
class PlanUsage
{
    public static function forAccount(?Account $account): ?array
    {
        $plan = optional($account)->plan;
        if (! $account || ! $plan) return null;

        return [
            'plan'    => $plan->name,
            'items'   => self::bar($account->productCount(), (int) $plan->max_products),                          // 0 = unlimited
            'storage' => self::bar(round($account->storageUsedBytes() / 1048576, 1), (int) $plan->storage_limit_mb),
            'users'   => self::bar($account->users()->count(), (int) $plan->max_users),
        ];
    }

    private static function bar(float $used, int $limit): array
    {
        $unlimited = $limit <= 0;
        $percent   = $unlimited ? 0 : (int) min(100, round($used / $limit * 100));

        return [
            'used'      => $used,
            'limit'     => $limit,
            'unlimited' => $unlimited,
            'remaining' => $unlimited ? null : max(0, $limit - $used),
            'percent'   => $percent,
            // standard traffic-light colours: fine, getting full, (almost) full
            'color'     => $percent >= 90 ? 'danger' : ($percent >= 70 ? 'warning' : 'success'),
        ];
    }
}
