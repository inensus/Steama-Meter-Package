<?php

namespace Inensus\SteamaMeter\Providers;


use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Inensus\SteamaMeter\Console\Commands\InstallPackage;
use Inensus\SteamaMeter\Console\Commands\SteamaMeterTransactionSync;
use Inensus\SteamaMeter\Console\Commands\SteamaMeterUpdatesGetter;
use Inensus\SteamaMeter\Console\Kernel;
use Inensus\SteamaMeter\Models\SteamaAssetRatesPaymentPlan;
use Inensus\SteamaMeter\Models\SteamaCustomerBasisTimeOfUsage;
use Inensus\SteamaMeter\Models\SteamaFlatRatePaymentPlan;
use Inensus\SteamaMeter\Models\SteamaHybridPaymentPlan;
use Inensus\SteamaMeter\Models\SteamaMinimumTopUpRequirementsPaymentPlan;
use Inensus\SteamaMeter\Models\SteamaSubscriptionPaymentPlan;
use Inensus\SteamaMeter\Models\SteamaTariffOverridePaymentPlan;
use Inensus\SteamaMeter\Models\SteamaTransaction;
use Inensus\SteamaMeter\SteamaMeterApi;
use GuzzleHttp\Client;

class SteamaMeterServiceProvider extends ServiceProvider
{
    public function boot(Filesystem $filesystem)
    {
        $this->app->register(RouteServiceProvider::class);
        if ($this->app->runningInConsole()) {
            $this->publishConfigFiles();
            $this->publishVueFiles();
            $this->publishMigrations($filesystem);
            $this->commands([
                InstallPackage::class,
                SteamaMeterTransactionSync::class,
                SteamaMeterUpdatesGetter::class
            ]);
        }
        Relation::morphMap(
            [
                'flat_rate' => SteamaFlatRatePaymentPlan::class,
                'hybrid' => SteamaHybridPaymentPlan::class,
                'subscription' => SteamaSubscriptionPaymentPlan::class,
                'minimum_top_up' => SteamaMinimumTopUpRequirementsPaymentPlan::class,
                'asset_rates' => SteamaAssetRatesPaymentPlan::class,
                'tariff_override' => SteamaTariffOverridePaymentPlan::class,
                'customer_time_of_usage' => SteamaCustomerBasisTimeOfUsage::class,
                'steama_transaction' => SteamaTransaction::class
            ]);
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/steama-meter.php', 'steama');
        $this->app->register(EventServiceProvider::class);
        $this->app->register(ObserverServiceProvider::class);

        $this->app->singleton('SteamaMeterApi', static function ($app) {
            return new SteamaMeterApi(new Client());
        });

        $this->app->singleton('Kernel', function ($app) {
            $dispatcher = $app->make(\Illuminate\Contracts\Events\Dispatcher::class);
            return new Kernel($app, $dispatcher);
        });
        $this->app->make('Kernel');
    }

    public function publishConfigFiles()
    {
        $this->publishes([
            __DIR__ . '/../../config/steama-meter.php' => config_path('steama-meter.php'),
        ]);
    }

    public function publishVueFiles()
    {
        $this->publishes([
            __DIR__ . '/../resources/assets' => resource_path('assets/js/plugins/steama-meter'
            ),
        ], 'vue-components');
    }

    public function publishMigrations($filesystem)
    {
        $this->publishes([
           __DIR__.'/../../database/migrations/create_steama_tables.php.stub' => $this->getMigrationFileName($filesystem),
            ], 'migrations');
    }

    protected function getMigrationFileName(Filesystem $filesystem): string
    {
        $timestamp = date('Y_m_d_His');
        return Collection::make($this->app->databasePath() . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR)
            ->flatMap(function ($path) use ($filesystem) {
                return $filesystem->glob($path . '*_create_steama_tables.php');
            })->push($this->app->databasePath() . "/migrations/{$timestamp}_create_steama_tables.php")
            ->first();
    }


}