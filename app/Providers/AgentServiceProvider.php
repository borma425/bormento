<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\FacebookService;
use App\Contracts\ECommerceProviderInterface;
use App\Contracts\PaymentProviderInterface;
use App\Services\PineconeService;
use App\Services\OpenRouterService;
use App\Services\ChatService;
use App\Services\InventoryService;
use App\Services\MessageCoordinator;
use App\Services\Messaging\MessagingRouter;
use App\Services\Agent\ToolExecutor;
use App\Services\Agent\AgentExecutor;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton bindings for infrastructure services
        $this->app->singleton(FacebookService::class);
        // Concrete services moved to TenantServiceProvider
        // The ones left here are global infrastructure
        $this->app->singleton(PineconeService::class);
        $this->app->singleton(OpenRouterService::class);
        $this->app->singleton(ChatService::class);

        $this->app->singleton(\App\Services\InstagramService::class);

        // Messaging Router (routes to Facebook, Instagram, or HTML cache based on userId)
        $this->app->singleton(MessagingRouter::class, function ($app) {
            return new MessagingRouter(
                $app->make(FacebookService::class),
                $app->make(\App\Services\InstagramService::class),
            );
        });

        // Inventory service depends on Pinecone + Gravoni
        $this->app->singleton(InventoryService::class, function ($app) {
            return new InventoryService(
                $app->make(PineconeService::class),
                $app->make(ECommerceProviderInterface::class),
            );
        });

        // Tool executor depends on all external services + MessagingRouter
        $this->app->singleton(ToolExecutor::class, function ($app) {
            return new ToolExecutor(
                $app->make(InventoryService::class),
                $app->make(ECommerceProviderInterface::class),
                $app->make(PaymentProviderInterface::class),
                $app->make(MessagingRouter::class),
                $app->make(OpenRouterService::class),
                $app->make(ChatService::class),
            );
        });

        // Agent executor uses MessagingRouter
        $this->app->singleton(AgentExecutor::class, function ($app) {
            return new AgentExecutor(
                $app->make(ChatService::class),
                $app->make(OpenRouterService::class),
                $app->make(ToolExecutor::class),
                $app->make(MessagingRouter::class),
            );
        });

        // Message coordinator uses MessagingRouter
        $this->app->singleton(MessageCoordinator::class, function ($app) {
            return new MessageCoordinator(
                $app->make(AgentExecutor::class),
                $app->make(MessagingRouter::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
