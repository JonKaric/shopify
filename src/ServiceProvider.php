<?php

namespace Jackabox\Shopify;

use Statamic\Facades\CP\Nav;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;
use PHPShopify\ShopifySDK;

class ServiceProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
        'web' => __DIR__.'/../routes/web.php',
    ];

    protected $fieldtypes = [
        \Jackabox\Shopify\Fieldtypes\Variants::class
    ];

    protected $scripts = [
        __DIR__.'/../dist/js/statamic-shopify-cp.js',
    ];

    protected $tags = [
        \Jackabox\Shopify\Tags\ShopifyScripts::class
    ];

    public function boot()
    {
        parent::boot();

        $this->createNavigation();

        $this->loadViewsFrom(__DIR__ . '/../resources/views/', 'shopify');
        $this->mergeConfigFrom(__DIR__ . '/../config/shopify.php', 'shopify');

        Statamic::booted(function() {
            $this->setShopifyApiConfig();
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../dist/js/statamic-shopify-front.js' => public_path('vendor/shopify/js/statamic-shopify-front.js'),
            ], 'shopify-assets');

            $this->publishes([
                __DIR__.'/../content/collections' => base_path('content/collections'),
            ], 'shopify-collections');

            $this->publishes([
                __DIR__.'/../resources/blueprints' => resource_path('blueprints'),
            ], 'shopify-blueprints');

            $this->publishes([
                __DIR__.'/../config/shopify.php' => config_path('shopify.php'),
            ], 'shopify-config');
        }
    }

    private function createNavigation(): void
    {
        Nav::extend(function ($nav) {
            $nav->create('Settings')
                ->icon('drawer-file')
                ->section('Shopify')
                ->route('shopify.index');
        });
    }

    private function setShopifyApiConfig(): void
    {
        $config = [
            'ShopUrl' => config('shopify.auth.url'),
            'ApiKey' => config('shopify.auth.key'),
            'Password' => config('shopify.auth.password'),
        ];

        ShopifySDK::config($config);
    }

    private function publishAssets(): void
    {
        Statamic::afterInstalled(function () {
            Artisan::call('vendor:publish --tag=shopify-config');
            Artisan::call('vendor:publish --tag=shopify-blueprints');
            Artisan::call('vendor:publish --tag=shopify-collections');
        });
    }
}