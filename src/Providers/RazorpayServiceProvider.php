<?php

namespace Wontonee\Razorpay\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

class RazorpayServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'razorpay');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'wontonee-razorpay');
        
        // Publish assets
        $this->publishes([
            __DIR__ . '/../Resources/assets/images' => public_path('vendor/wontonee/razorpay'),
        ], 'razorpay-assets');

        // Register event listeners for admin order page
        $this->registerEventListeners();
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConfig();
        $this->registerCommands();
        $this->registerServices();
    }

    /**
     * Register package services.
     *
     * @return void
     */
    protected function registerServices()
    {
        $this->app->singleton(\Wontonee\Razorpay\Services\RazorpayFallbackService::class);
    }

    /**
     * Register console commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Wontonee\Razorpay\Console\Commands\ProcessRazorpayFallback::class,
                \Wontonee\Razorpay\Console\Commands\CreateTestPaymentAttempt::class,
                \Wontonee\Razorpay\Console\Commands\RazorpayStatus::class,
            ]);
        }
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/paymentmethods.php',
            'payment_methods'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/system.php',
            'core'
        );
    }

    /**
     * Register event listeners for Razorpay refund functionality
     *
     * @return void
     */
    protected function registerEventListeners()
    {
        // Listen for the admin order page action buttons event
        Event::listen('bagisto.admin.sales.order.page_action.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('razorpay::admin.sales.orders.refund-button');
        });

        // Listen for the admin order view content event to inject payment details
        Event::listen('bagisto.admin.sales.order.view.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('razorpay::admin.sales.orders.payment-details');
        });
    }
}
