<?php

namespace Inensus\SteamaMeter\Jobs;

use App\Exceptions\SmsBodyParserNotExtendedException;
use App\Exceptions\SmsTypeNotFoundException;
use App\Models\Sms;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Inensus\SteamaMeter\Sms\Senders\SteamaSmsSender;
use Inensus\SteamaMeter\Sms\SteamaSmsTypes;

class SteamaSmsProcessor implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $data;
    public $smsType;
    private $smsTypes = [
        SteamaSmsTypes::LOW_BALANCE_LIMIT_NOTIFIER => 'Inensus\SteamaMeter\Sms\Senders\LowBalanceLimitNotifier',
    ];

    /**
     * Create a new job instance.
     *
     * @param            $data
     * @param int $smsType
     */
    public function __construct($data, int $smsType)
    {
        $this->data = $data;
        $this->smsType = $smsType;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $smsType = $this->resolveSmsType();
        $receiver = $smsType->getReceiver();
        //dont send sms if debug
        if (config('app.debug')) {
            $sms = Sms::query()->make([
                'body' => $smsType->body,
                'receiver' => $receiver,
                'uuid' => "debug"
            ]);
            $sms->trigger()->associate($this->data);
            $sms->save();
            return;
        }
        try {
            //set the uuid for the callback
            $uuid = $smsType->generateCallback();
            //sends sms or throws exception

            $smsType->sendSms();
        } catch (\Exception $e) {
            //slack failure
            Log::debug(
                'Sms Service failed ' . $receiver,
                ['id' => '58365682988725', 'reason' => $e->getMessage()]
            );
            return;
        }
        $sms = Sms::query()->make([
            'uuid' => $uuid,
            'body' => $smsType->body,
            'receiver' => $receiver
        ]);
        $sms->trigger()->associate($this->data);
        $sms->save();
    }

    private function resolveSmsType()
    {
        if (!array_key_exists($this->smsType, $this->smsTypes)) {
            throw new SmsTypeNotFoundException('SmsType could not resolve.');
        }
        $smsBodyService = resolve('Inensus\SteamaMeter\Services\SteamaSmsBodyService');
        $reflection = new \ReflectionClass($this->smsTypes[$this->smsType]);

        if (!$reflection->isSubclassOf(SteamaSmsSender::class)) {
            throw new  SmsBodyParserNotExtendedException('SmsBodyParser has not extended.');
        }
        return $reflection->newInstanceArgs([$this->data, $smsBodyService]);
    }
}
