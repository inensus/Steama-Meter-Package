<?php


namespace Inensus\SteamaMeter\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Inensus\SteamaMeter\Services\SteamaAgentService;
use Inensus\SteamaMeter\Services\SteamaCustomerService;
use Inensus\SteamaMeter\Services\SteamaMeterService;
use Inensus\SteamaMeter\Services\SteamaSiteService;
use Inensus\StemaMeter\Exceptions\CronJobException;

class SteamaMeterUpdatesGetter extends Command
{
    protected $signature = 'steama-meter:updatesGetter';
    protected $description = 'Gets updates from Steama Meter.';

    private $stemaMeterService;
    private $steamaCustomerService;
    private $steamaSiteService;
    private $steamaAgentService;
    public function __construct(

        SteamaMeterService $steamaMeterService,
        SteamaCustomerService $steamaCustomerService,
        SteamaSiteService $steamaSiteService,
        SteamaAgentService $steamaAgentService
    ){
        parent::__construct();
        $this->stemaMeterService = $steamaMeterService;
        $this->steamaCustomerService = $steamaCustomerService;
        $this->steamaSiteService = $steamaSiteService;
        $this->steamaAgentService = $steamaAgentService;
    }

    public function handle(): void
    {
        try {
            $this->steamaSiteService->sync();

            $this->steamaCustomerService->sync();

            $this->stemaMeterService->sync();

            $this->steamaAgentService->sync();

        }catch (CronJobException $e){
            Log::critical('steama-meter:updates-getter failed.' ,['message'=>$e->getMessage()]);
        }
    }
}