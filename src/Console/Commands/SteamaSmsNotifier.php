<?php


namespace Inensus\SteamaMeter\Console\Commands;

use App\Models\Sms;
use App\Sms\SmsTypes;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Inensus\SteamaMeter\Helpers\SmsBodyGenerator;
use Inensus\SteamaMeter\Services\SteamaCustomerService;
use Inensus\SteamaMeter\Services\SteamaSmsNotifiedCustomerService;
use Inensus\SteamaMeter\Services\SteamaSmsSettingService;
use Inensus\SteamaMeter\Services\SteamaTransactionsService;
use Inensus\StemaMeter\Exceptions\CronJobException;
use Webpatser\Uuid\Uuid;

class SteamaSmsNotifier extends Command
{
    protected $signature = 'steama-meter:smsNotifier';
    protected $description = '';

    private $smsSettingsService;
    private $sms;
    private $steamaTransactionService;
    private $steamaSmsNotifiedCustomerService;
    private $steamaCustomerService;
    private $smsBodyGenerator;

    public function __construct(
        SteamaSmsSettingService $smsSettingService,
        Sms $sms,
        SteamaTransactionsService $steamaTransactionsService,
        SteamaSmsNotifiedCustomerService $steamaSmsNotifiedCustomerService,
        SteamaCustomerService $steamaCustomerService,
        SmsBodyGenerator $smsBodyGenerator
    ) {
        parent::__construct();
        $this->smsSettingsService = $smsSettingService;
        $this->sms = $sms;
        $this->steamaTransactionService = $steamaTransactionsService;
        $this->steamaSmsNotifiedCustomerService = $steamaSmsNotifiedCustomerService;
        $this->steamaCustomerService = $steamaCustomerService;
        $this->smsBodyGenerator = $smsBodyGenerator;
    }

    public function handle()
    {
        $timeStart = microtime(true);
        $this->info('#############################');
        $this->info('# Steamaco Meter Package #');
        $startedAt = Carbon::now()->toIso8601ZuluString();
        $this->info('smsNotifier command started at ' . $startedAt);
        try {

            $smsSettings = $this->smsSettingsService->getSmsSettings();
            $transactionMin = $smsSettings->where('state', 'Transactions')->first()->not_send_elder_than_mins;
            $lowBalanceMin = $smsSettings->where('state', 'Low Balance Warning')->first()->not_send_elder_than_mins;
            $smsNotifiedCustomers = $this->steamaSmsNotifiedCustomerService->getSteamaSmsNotifiedCustomers();
            $customers = $this->steamaCustomerService->getSteamaCustomersWithAddress($lowBalanceMin);
            $this->sendTransactionNotifySms($transactionMin, $smsNotifiedCustomers, $customers);
            $this->sendLowBalanceWarningNotifySms($customers, $smsNotifiedCustomers, $lowBalanceMin);
        } catch (CronJobException $e) {
            $this->warn('dataSync command is failed. message => ' . $e->getMessage());
        }
        $timeEnd = microtime(true);
        $totalTime = $timeEnd - $timeStart;
        $this->info("Took " . $totalTime . " seconds.");
        $this->info('#############################');
    }

    private function sendTransactionNotifySms($transactionMin, $smsNotifiedCustomers, $customers)
    {
        $this->steamaTransactionService->getSteamaTransactions($transactionMin)->each(function ($steamaTransaction) use
        (
            $transactionMin,
            $smsNotifiedCustomers,
            $customers
        ) {

            $smsNotifiedCustomers = $smsNotifiedCustomers->where('notify_id',
                $steamaTransaction->id)->where('customer_id', $steamaTransaction->customer_id)->first();
            if ($smsNotifiedCustomers) {
                return true;
            }
            $notifyCustomer = $customers->filter(function ($customer) use ($steamaTransaction) {
                return $customer->customer_id == $steamaTransaction->customer_id;
            })->first();

            if (!$notifyCustomer) {

                return true;
            }

            if (!$notifyCustomer->mpmPerson->addresses || $notifyCustomer->mpmPerson->addresses[0]->phone === null ||
                $notifyCustomer->mpmPerson->addresses[0]->phone === "") {
                return true;
            }

            $sms = new Sms();
            $sms->uuid = (string)Uuid::generate(4);
            resolve('SmsProvider')
                ->sendSms(
                    $steamaTransaction->thirdPartyTransaction->transaction->sender,
                    $this->smsBodyGenerator->generateSmsBody($steamaTransaction->thirdPartyTransaction->transaction,
                        $notifyCustomer),
                    sprintf(config()->get('services.sms.callback'), $sms->uuid)
                );

            $this->steamaSmsNotifiedCustomerService->createTransactionSmsNotify($notifyCustomer->customer_id,
                $steamaTransaction->id);
            return true;
        });

    }

    private function sendLowBalanceWarningNotifySms($customers, $smsNotifiedCustomers, $lowBalanceMin)
    {

        $customers->each(function ($customer) use (
            $smsNotifiedCustomers,
            $lowBalanceMin
        ) {
            $notifiedCustomer = $smsNotifiedCustomers->where('notify_type','low_balance')->where('customer_id',
                $customer->customer_id)->first();
            if ($notifiedCustomer) {
                return true;
            }
            if ($customer->account_balance>$customer->low_balance_warning){
                return true;
            }
            if (!$customer->mpmPerson->addresses || $customer->mpmPerson->addresses[0]->phone === null ||
                $customer->mpmPerson->addresses[0]->phone === "") {
                return true;
            }
            $sms = new Sms();
            $sms->uuid = (string)Uuid::generate(4);
            resolve('SmsProvider')
                ->sendSms(
                    $customer->mpmPerson->addresses[0]->phone,
                    $this->smsBodyGenerator->generateSmsBody(null, $customer),
                    sprintf(config()->get('services.sms.callback'), $sms->uuid)
                );

            $this->steamaSmsNotifiedCustomerService->createLowBalanceSmsNotify($customer->customer_id);
            return true;
        });
    }
}