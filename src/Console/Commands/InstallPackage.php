<?php

namespace Inensus\SteamaMeter\Console\Commands;

use Illuminate\Console\Command;
use Inensus\SteamaMeter\Helpers\ApiHelpers;
use Inensus\SteamaMeter\Services\MenuItemService;
use Inensus\SteamaMeter\Services\SteamaAgentService;
use Inensus\SteamaMeter\Services\SteamaCredentialService;
use Inensus\SteamaMeter\Services\SteamaSiteLevelPaymentPlanTypeService;
use Inensus\SteamaMeter\Services\SteamaSiteService;
use Inensus\SteamaMeter\Services\SteamaTariffService;
use Inensus\SteamaMeter\Services\SteamaUserTypeService;

class InstallPackage extends Command
{
    protected $signature = 'steama-meter:install';
    protected $description = 'Install SteamaMeter Package';

    private $menuItemService;
    private $agentService;
    private $credentialService;
    private $paymentPlanService;
    private $tariffService;
    private $transactionCategoryService;
    private $userTypeService;
    private $apiHelpers;
    private $siteService;

    public function __construct(
        MenuItemService $menuItemService,
        SteamaAgentService $agentService,
        SteamaCredentialService $credentialService,
        SteamaSiteLevelPaymentPlanTypeService $paymentPlanService,
        SteamaTariffService $tariffService,
        SteamaUserTypeService $userTypeService,
        ApiHelpers $apiHelpers,
        SteamaSiteService $siteService
    ) {
        parent::__construct();
        $this->apiHelpers = $apiHelpers;
        $this->menuItemService = $menuItemService;
        $this->agentService = $agentService;
        $this->credentialService = $credentialService;
        $this->paymentPlanService = $paymentPlanService;
        $this->tariffService = $tariffService;
        $this->userTypeService = $userTypeService;
        $this->siteService=$siteService;

    }

    public function handle(): void
    {
        $this->info('Installing SteamaMeter Integration Package\n');

        $this->info('Copying migrations\n');
        $this->call('vendor:publish', [
            '--provider' => "Inensus\SteamaMeter\Providers\ServiceProvider",
            '--tag' => "migrations"
        ]);

        $this->info('Creating database tables\n');
        $this->call('migrate');

        $this->info('Copying vue files\n');
        $this->call('vendor:publish', [
            '--provider' => "Inensus\SteamaMeter\Providers\ServiceProvider",
            '--tag' => "vue-components"
        ]);

        $this->apiHelpers->registerSparkMeterManufacturer();

        $this->credentialService->createCredentials();
        $tariff = $this->tariffService->createTariff();
        $this->userTypeService->createUserTypes($tariff);
        $this->paymentPlanService->createPaymentPlans();
        $this->agentService->createSteamaAgentCommission();

        $this->call('plugin:add', [
            'name' => "SteamaMeter",
            'composer_name' => "inensus/steama-meter",
            'description' => "SteamaMeter integration package for MicroPowerManager",
        ]);


        $this->call('routes:generate');

        $menuItems = $this->menuItemService->createMenuItems();
        $this->call('menu-items:generate', [
            'menuItem' => $menuItems['menuItem'],
            'subMenuItems' => $menuItems['subMenuItems'],
        ]);


         $this->call('sidebar:generate');

        $this->info('Package installed successfully..');

        if(!$this->siteService->checkLocationAvailability()){
            $this->warn('------------------------------');
            $this->warn("Steama Meter package needs least one registered Cluster.");
            $this->warn("If you have no Cluster, please navigate to #Locations# section and register your locations.");
        }
    }
}