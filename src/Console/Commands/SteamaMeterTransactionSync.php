<?php


namespace Inensus\SteamaMeter\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Inensus\SteamaMeter\Services\SteamaTransactionsService;
use Inensus\StemaMeter\Exceptions\CronJobException;

class SteamaMeterTransactionSync extends Command
{
    protected $signature = 'steama-meter:transactionSync';
    protected $description = 'Synchronise transactions from Steama Meter.';

    private $steamaTransactionsService;

    public function __construct(SteamaTransactionsService $steamaTransactionsService)
    {
        parent::__construct();
        $this->steamaTransactionsService = $steamaTransactionsService;
    }

    public function handle(): void
    {

        $timeStart = microtime(true);
        $this->info('#############################');
        $startedAt=Carbon::now()->toIso8601ZuluString();
        $this->info('transactionSync command started at '.$startedAt);

        try {
          $resultMessage = $this->steamaTransactionsService->sync();
          $this->info('transactionSync command is finished with result : ' . $resultMessage);
        } catch (CronJobException $e) {
            $this->warn('transactionSync command is failed. message => ' . $e->getMessage());
        }
        $timeEnd = microtime(true);
        $totalTime=$timeEnd - $timeStart;
        $this->info("Took ".$totalTime." seconds.");
        $this->info('#############################');
    }
}