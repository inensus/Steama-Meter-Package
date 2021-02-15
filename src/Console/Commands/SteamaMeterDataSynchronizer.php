<?php

namespace Inensus\SteamaMeter\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Inensus\SteamaMeter\Services\SteamaAgentService;
use Inensus\SteamaMeter\Services\SteamaCustomerService;
use Inensus\SteamaMeter\Services\SteamaMeterService;
use Inensus\SteamaMeter\Services\SteamaSiteService;
use Inensus\SteamaMeter\Services\SteamaSyncSettingService;
use Inensus\SteamaMeter\Services\SteamaTransactionsService;
use Inensus\SteamaMeter\Services\StemaSyncActionService;
use Inensus\StemaMeter\Exceptions\CronJobException;

class SteamaMeterDataSynchronizer extends Command
{
    protected $signature = 'steama-meter:dataSync';
    protected $description = 'Synchronize data that needs to be updated from Steamaco Meter.';

    private $steamaTransactionsService;
    private $steamaSyncSettingservice;
    private $stemaMeterService;
    private $steamaCustomerService;
    private $steamaSiteService;
    private $steamaAgentService;
    private $steamaSyncActionService;

    public function __construct(
        SteamaTransactionsService $steamaTransactionsService,
        SteamaSyncSettingService $steamaSyncSettingService,
        SteamaMeterService $steamaMeterService,
        SteamaCustomerService $steamaCustomerService,
        SteamaSiteService $steamaSiteService,
        SteamaAgentService $steamaAgentService,
        StemaSyncActionService $steamaSyncActionService
    ) {
        parent::__construct();
        $this->steamaTransactionsService = $steamaTransactionsService;
        $this->steamaSyncSettingservice = $steamaSyncSettingService;
        $this->stemaMeterService = $steamaMeterService;
        $this->steamaCustomerService = $steamaCustomerService;
        $this->steamaSiteService = $steamaSiteService;
        $this->steamaAgentService = $steamaAgentService;
        $this->steamaSyncActionService = $steamaSyncActionService;
    }

    public function handle(): void
    {
        $timeStart = microtime(true);
        $this->info('#############################');
        $this->info('# Steamaco Meter Package #');
        $startedAt = Carbon::now()->toIso8601ZuluString();
        $this->info('dataSync command started at ' . $startedAt);

        $syncActions = $this->steamaSyncActionService->getActionsNeedsToSync();
        try {
            $this->steamaSyncSettingservice->getSyncSettings()->each(function ($syncSetting) use ($syncActions) {
                $syncNeeded = $syncActions->whereIn('sync_setting_id', $syncSetting->id)->where('attempts', '<', $syncSetting->max_attempts)->first();
                if ($syncNeeded) {
                    switch ($syncSetting->action_name) {
                        case 'Sites':
                            $this->steamaSiteService->sync();
                            break;
                        case 'Customers':
                            $this->steamaCustomerService->sync();
                            break;
                        case 'Meters':
                              $this->stemaMeterService->sync();
                            break;
                        case 'Agents':
                            $this->steamaAgentService->sync();
                            break;
                        case 'Transactions':
                            $this->steamaTransactionsService->sync();
                            break;
                    }
                }
            });
        } catch (CronJobException $e) {
            $this->warn('dataSync command is failed. message => ' . $e->getMessage());
        }
        $timeEnd = microtime(true);
        $totalTime = $timeEnd - $timeStart;
        $this->info("Took " . $totalTime . " seconds.");
        $this->info('#############################');
    }
}
