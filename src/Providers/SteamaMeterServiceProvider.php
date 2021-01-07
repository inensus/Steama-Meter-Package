<?php
namespace Inensus\SteamaMeter\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
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
    public function boot()
    {
        $this->app->register(RouteServiceProvider::class);
        if ($this->app->runningInConsole()) {
            $this->publishConfigFiles();
            $this->publishVueFiles();
            $this->publishMigrations();
            $this->commands([InstallPackage::class,SteamaMeterTransactionSync::class,SteamaMeterUpdatesGetter::class]);
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
                'steama_transaction'=>SteamaTransaction::class
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

    public function publishMigrations()
    {
        if (!class_exists('CreateSteamaCredentials')) {
            $timestamp = date('Y_m_d_His');
           $this->publishes([
               __DIR__ . '/../../database/migrations/create_steama_agents.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_agents.php",
               __DIR__ . '/../../database/migrations/create_steama_asset_rates_payment_plans.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_asset_rates_payment_plans.php",
               __DIR__ . '/../../database/migrations/create_steama_credentials.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_credentials.php",
               __DIR__ . '/../../database/migrations/create_steama_customer_basis_payment_plans.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_customer_basis_payment_plans.php",
               __DIR__ . '/../../database/migrations/create_steama_customers.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_customers.php",
               __DIR__ . '/../../database/migrations/create_steama_flat_rate_payment_plans.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_flat_rate_payment_plans.php",
               __DIR__ . '/../../database/migrations/create_steama_hybrid_payment_plans.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_hybrid_payment_plans.php",
               __DIR__ . '/../../database/migrations/create_steama_meters.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_meters.php",
               __DIR__ . '/../../database/migrations/create_steama_meter_types.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_meter_types.php",
               __DIR__ . '/../../database/migrations/create_steama_minimum_top_up_requirements_payment_plans.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_minimum_top_up_requirements_payment_plans.php",
               __DIR__ . '/../../database/migrations/create_steama_site_level_payment_plan_types.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_site_level_payment_plan_types.php",
               __DIR__ . '/../../database/migrations/create_steama_site_level_payment_plans.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_site_level_payment_plans.php",
               __DIR__ . '/../../database/migrations/create_steama_sites.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_sites.php",
               __DIR__ . '/../../database/migrations/create_steama_subscription_payment_plans.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_subscription_payment_plans.php",
               __DIR__ . '/../../database/migrations/create_steama_tariff_override_payment_plans.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_tariff_override_payment_plans.php",
               __DIR__ . '/../../database/migrations/create_steama_per_kwh_payment_plans.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_per_kwh_payment_plans.php",
               __DIR__ . '/../../database/migrations/create_steama_tariffs.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_tariffs.php",
               __DIR__ . '/../../database/migrations/create_steama_transactions.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_transactions.php",
               __DIR__ . '/../../database/migrations/create_steama_user_types.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_user_types.php",
               __DIR__ . '/../../database/migrations/create_steama_customer_basis_time_of_usages.php.stub' => $this->app->databasePath() . "/migrations/{$timestamp}_create_steama_customer_basis_time_of_usages",

            ], 'migrations');
        }
    }
}