<?php

namespace Inensus\SteamaMeter\Providers;

use App\Models\Meter\MeterParameter;
use App\Models\Transaction\Transaction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Inensus\SteamaMeter\Console\Commands\InstallPackage;
use Inensus\SteamaMeter\Console\Commands\SteamaMeterDataSynchronizer;
use Inensus\SteamaMeter\Console\Commands\SteamaSmsNotifier;
use Inensus\SteamaMeter\Models\SteamaAssetRatesPaymentPlan;
use Inensus\SteamaMeter\Models\SteamaCustomer;
use Inensus\SteamaMeter\Models\SteamaCustomerBasisTimeOfUsage;
use Inensus\SteamaMeter\Models\SteamaFlatRatePaymentPlan;
use Inensus\SteamaMeter\Models\SteamaHybridPaymentPlan;
use Inensus\SteamaMeter\Models\SteamaMinimumTopUpRequirementsPaymentPlan;
use Inensus\SteamaMeter\Models\SteamaSmsSetting;
use Inensus\SteamaMeter\Models\SteamaSubscriptionPaymentPlan;
use Inensus\SteamaMeter\Models\SteamaSyncSetting;
use Inensus\SteamaMeter\Models\SteamaTariffOverridePaymentPlan;
use Inensus\SteamaMeter\Models\SteamaTransaction;
use Inensus\SteamaMeter\Services\SteamaCredentialService;
use Inensus\SteamaMeter\Services\SteamaCustomerService;
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
                SteamaMeterDataSynchronizer::class,
                SteamaSmsNotifier::class

            ]);
        }
        $this->app->booted(function ($app) {
            $app->make(Schedule::class)->command('steama-meter:dataSync')->withoutOverlapping(50)
                ->appendOutputTo(storage_path('logs/cron.log'));
            $app->make(Schedule::class)->command('steama-meter:smsNotifier')->withoutOverlapping(50)
                ->appendOutputTo(storage_path('logs/cron.log'));
        });
        Relation::morphMap(
            [
                'flat_rate' => SteamaFlatRatePaymentPlan::class,
                'hybrid' => SteamaHybridPaymentPlan::class,
                'subscription' => SteamaSubscriptionPaymentPlan::class,
                'minimum_top_up' => SteamaMinimumTopUpRequirementsPaymentPlan::class,
                'asset_rates' => SteamaAssetRatesPaymentPlan::class,
                'tariff_override' => SteamaTariffOverridePaymentPlan::class,
                'customer_time_of_usage' => SteamaCustomerBasisTimeOfUsage::class,
                'steama_transaction' => SteamaTransaction::class,
                'sync_setting' => SteamaSyncSetting::class,
                'sms_setting' => SteamaSmsSetting::class,
            ]
        );
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/steama-meter.php', 'steama');
        $this->app->register(EventServiceProvider::class);
        $this->app->register(ObserverServiceProvider::class);
        $this->app->bind('SteamaMeterApi',SteamaMeterApi::class);
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
            __DIR__ . '/../resources/assets' => resource_path('assets/js/plugins/steama-meter'),
        ], 'vue-components');
    }

    public function publishMigrations($filesystem)
    {
        $this->publishes([
            __DIR__ . '/../../database/migrations/create_steama_tables.php.stub'
            => $this->getMigrationFileName($filesystem),
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
