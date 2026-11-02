<?php

namespace App\Services\Gateways;

/** Finds the gateway that is switched on in config/billing.php. Add new gateways to drivers(). */
class GatewayManager
{
    /** @return array<string, GatewayInterface> */
    private function drivers(): array
    {
        return [
            'sslcommerz' => new SslCommerzGateway(),
            // 'bkash' => new BkashGateway(),
        ];
    }

    public function driver(?string $name = null): ?GatewayInterface
    {
        $name ??= config('billing.online.driver');
        return $this->drivers()[$name] ?? null;
    }

    /** Is "Pay online" available for customers right now? */
    public function enabled(): bool
    {
        $driver = $this->driver();
        return (bool) config('billing.online.enabled') && $driver && $driver->isConfigured();
    }
}
