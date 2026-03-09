<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Tenant;
use App\Contracts\ECommerceProviderInterface;
use App\Contracts\PaymentProviderInterface;
use App\Services\StandardECommerceProvider;
use App\Services\StandardPaymentProvider;

class TenantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ECommerceProviderInterface::class, function ($app) {
            $tenant = $app->bound(Tenant::class) ? $app->make(Tenant::class) : null;
            $config = $tenant ? $tenant->ecommerce_config : [];
            
            // Route everything through the Native SaaS DB Provider 
            // since products are now stored locally in the SaaS database.
            return new \App\Services\LocalTenantECommerceProvider();
        });

        // Bind PaymentProviderInterface
        $this->app->bind(PaymentProviderInterface::class, function ($app) {
            $tenant = $app->bound(Tenant::class) ? $app->make(Tenant::class) : null;
            $config = $tenant ? $tenant->payment_config : [];
            
            // Depending on $config['provider'], we could return different services.
            // For now, default to CashUp.
            return new StandardPaymentProvider($config ?: []);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
