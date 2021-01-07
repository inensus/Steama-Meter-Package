<?php


namespace Inensus\SteamaMeter\Services;


use App\Models\Meter\MeterToken;
use App\Models\Transaction\ThirdPartyTransaction;
use App\Models\Transaction\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Inensus\SteamaMeter\Http\Requests\SteamaMeterApiRequests;
use Inensus\SteamaMeter\Models\SteamaCustomer;
use Inensus\SteamaMeter\Models\SteamaMeter;
use Inensus\SteamaMeter\Models\SteamaTransaction;
use phpDocumentor\Reflection\Types\This;
use PHPUnit\Runner\BeforeFirstTestHook;

class SteamaTransactionsService implements ISynchronizeService
{

    private $stemaMeterService;
    private $steamaCustomerService;
    private $steamaCredentialService;
    private $steamaSiteService;
    private $steamaAgentService;
    private $steamaTransaction;
    private $steamaMeter;
    private $steamaApi;
    private $thirdPartyTransaction;
    private $rootUrl = '/transactions';
    private $transaction;
    private $meterToken;
    private $steamaCustomer;
    public function __construct(
        SteamaMeterService $steamaMeterService,
        SteamaCustomerService $steamaCustomerService,
        SteamaCredentialService $steamaCredentialService,
        SteamaSiteService $steamaSiteService,
        SteamaAgentService $steamaAgentService,
        SteamaTransaction $steamaTransaction,
        SteamaMeterApiRequests $steamaMeterApiRequests,
        Transaction $transaction,
        SteamaMeter $steamaMeter,
        ThirdPartyTransaction $thirdPartyTransaction,
        MeterToken $meterToken,
        SteamaCustomer $steamaCustomer

    ) {
        $this->stemaMeterService = $steamaMeterService;
        $this->steamaCustomerService = $steamaCustomerService;
        $this->steamaCredentialService = $steamaCredentialService;
        $this->steamaSiteService = $steamaSiteService;
        $this->steamaAgentService = $steamaAgentService;
        $this->steamaTransaction = $steamaTransaction;
        $this->steamaApi = $steamaMeterApiRequests;
        $this->transaction = $transaction;
        $this->steamaMeter = $steamaMeter;
        $this->thirdPartyTransaction = $thirdPartyTransaction;
        $this->meterToken=$meterToken;
        $this->steamaCustomer=$steamaCustomer;
    }

    public function sync()
    {
        $syncCheck = $this->syncCheck();
        if ($syncCheck['result']) {

            $lastCreatedTransaction = $this->steamaTransaction->newQuery()->latest('timestamp')->orderBy('id','desc')->first();
            $lastRecordedTransactionId=0;

            if ($lastCreatedTransaction) {
                $url = $this->rootUrl . '?ordering=timestamp&created_after=' . Carbon::parse($lastCreatedTransaction->timestamp)->toIso8601ZuluString() . '&page=1&page_size=100';
                $lastRecordedTransactionId=$lastCreatedTransaction->transaction_id;
            } else {
                $url = $this->rootUrl . '?ordering=timestamp&created_before=' . Carbon::now()->toIso8601ZuluString() . '&page=1&page_size=100';
            }


            $result = $this->steamaApi->get($url);
            $transactions = $result['results'];
            while ($result['next']) {


                foreach ($transactions as $key => $transaction) {
                    $steamaMeter = $this->steamaMeter->newQuery()->with(['mpmMeter.meterParameter.owner'])->where('customer_id',
                        $transaction['customer_id'])->first();
                    if ($steamaMeter && $lastRecordedTransactionId < $transaction['id']) {

                        $steamaTransaction = $this->steamaTransaction->newQuery()->create([
                            'transaction_id' => $transaction['id'],
                            'site_id' => $transaction['site_id'],
                            'customer_id' => $transaction['customer_id'],
                            'amount' => $transaction['amount'],
                            'category' => $transaction['category'],
                            'provider' => $transaction['provider'] ?? 'AP',
                            'timestamp' => $transaction['timestamp'],
                            'synchronization_status' => $transaction['synchronization_status']
                        ]);

                        if ($transaction['category'] == 'PAY') {


                            $thirdPartyTransaction = $this->thirdPartyTransaction->newQuery()->make([
                                'transaction_id' => $transaction['id'],
                                'status' => $transaction['reversed_by_id'] !== null ? -1 : 1,
                                'description' => $transaction['provider'] === 'AA' ? 'Payment recorded by agent : ' . $transaction['agent_id'] . ' ~Steama Meter' : null,
                            ]);
                            $thirdPartyTransaction->manufacturerTransaction()->associate($steamaTransaction);
                            $thirdPartyTransaction->save();

                            $mainTransaction = $this->transaction->newQuery()->make([
                                'amount' => (int)$transaction['amount'],
                                'sender' => $transaction['customer_telephone'],
                                'message' => $steamaMeter->mpmMeter->serial_number,
                                'type' => 'energy',
                                'created_at'=>$transaction['timestamp'],
                                'updated_at'=>$transaction['timestamp'],
                            ]);
                            $mainTransaction->originalTransaction()->associate($thirdPartyTransaction);
                            $mainTransaction->save();
                            $owner = $steamaMeter->mpmMeter->meterParameter->owner;
                            $stmCustomer = $steamaMeter->stmCustomer->first();
                            $customerEnergyPrice = $stmCustomer->energy_price;
                            $chargedEnergy = $mainTransaction->amount / ($customerEnergyPrice);

                            $token=$transaction['site_id'].'-'.$transaction['category'].'-'.$transaction['provider'].'-'.$transaction['customer_id'];

                            $token = $this->meterToken->newQuery()->make([
                                'token' => $token,
                                'energy' => $chargedEnergy,

                            ]);

                            $token->transaction()->associate($mainTransaction);
                            $token->meter()->associate($steamaMeter->mpmMeter->first());
                            //save token
                            $token->save();

                          event('payment.successful', [
                                'amount' => $mainTransaction->amount,
                                'paymentService' => $mainTransaction->original_transaction_type,
                                'paymentType' => 'energy',
                                'sender' => $mainTransaction->sender,
                                'paidFor' => $token,
                                'payer' => $owner,
                                'transaction' => $mainTransaction,
                            ]);
                        }
                    }
                }

                $url = $this->rootUrl . '?' . explode('?', $result['next'])[1];
                $result = $this->steamaApi->get($url);
                $transactions = $result['results'];
            }

        } else {
            Log::debug('Transaction synchronising cancelled', ['message' => $syncCheck['message']]);
        }
        return $syncCheck['message'];
    }

    public function syncCheck()
    {
        $credentials = $this->steamaCredentialService->getCredentials();
        if ($credentials) {
            if ($credentials->is_authenticated) {
                $siteSynchronized = $this->steamaSiteService->syncCheck();

                if ($siteSynchronized['result']) {
                    $customerSynchronized = $this->steamaCustomerService->syncCheck();

                    if ($customerSynchronized['result']) {
                        $meterSynchronized = $this->stemaMeterService->syncCheck();

                        if ($meterSynchronized['result']) {
                            $agentSynchronized = $this->steamaAgentService->syncCheck();
                            if ($agentSynchronized['result']) {
                                return ['result' => true, 'message' => 'Records are updated'];
                            } else {
                                return ['result' => false, 'message' => 'Agent records are not up to date.'];
                            }
                        } else {
                            return ['result' => false, 'message' => 'Meter records are not up to date.'];
                        }
                    } else {
                        return ['result' => false, 'message' => 'Customer records are not up to date.'];
                    }
                } else {
                    return ['result' => false, 'message' => 'Site records are not up to date.'];
                }
            } else {
                return ['result' => false, 'message' => 'Credentials records are not up to date.'];
            }
        } else {
            return ['result' => false, 'message' => 'No Credentials record found.'];
        }
    }

    public function getTransactionsByCustomer($customer,$request)
    {
        $perPage = $request->input('per_page') ?? 15;
        return $this->steamaTransaction->newQuery()->where('customer_id',$customer)->paginate($perPage);
    }
}
