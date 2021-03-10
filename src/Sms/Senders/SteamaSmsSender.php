<?php

namespace Inensus\SteamaMeter\Sms\Senders;

use App\Exceptions\MissingSmsReferencesException;
use Illuminate\Support\Facades\Log;
use Inensus\SteamaMeter\Services\SteamaSmsBodyService;
use Webpatser\Uuid\Uuid;

abstract class SteamaSmsSender
{
    protected $smsBodyService;
    protected $data;
    protected $references;
    public $body = '';
    protected $receiver;
    protected $callback;

    public function __construct($data, SteamaSmsBodyService $smsBodyService)
    {
        $this->smsBodyService = $smsBodyService;
        $this->data = $data;
        $this->validateReferences();
        $this->prepareHeader();
        $this->prepareBody();
        $this->prepareFooter();
    }

    public function sendSms()
    {
        if (config('app.debug')) {
            Log::debug(
                'Send sms on debug is not allowed in debug mode',
                ['number' => $this->receiver, 'message' => $this->body]
            );
            return;
        }
        $nullSmsBodies = $this->smsBodyService->getNullBodies();
        if (count($nullSmsBodies)) {
            Log::debug('Send sms rejected, some of sms bodies are null', ['Sms Bodies' => $nullSmsBodies]);
            return;
        }

        //add sms to sms_gateway
        resolve('SmsProvider')
            ->sendSms(
                $this->receiver,
                $this->body,
                $this->callback
            );
    }

    public function prepareHeader()
    {
        $smsBody = $this->smsBodyService->getSmsBodyByReference($this->references['header']);
        $className = 'Inensus\\SteamaMeter\\Sms\\BodyParsers\\' . $this->references['header'];
        $smsObject = new  $className($this->data);
        $this->body .= $smsObject->parseSms($smsBody->body);
    }

    public function prepareBody()
    {
        $smsBody = $this->smsBodyService->getSmsBodyByReference($this->references['body']);
        $className = 'Inensus\\SteamaMeter\\Sms\\BodyParsers\\' . $this->references['body'];
        $smsObject = new $className($this->data);
        $this->body .= ' ' . $smsObject->parseSms($smsBody->body);
    }

    public function prepareFooter()
    {
        $smsBody = $this->smsBodyService->getSmsBodyByReference($this->references['footer']);
        $this->body .= ' ' . $smsBody->body;
    }

    private function validateReferences()
    {
        if (
            !array_key_exists('header', $this->references) ||
            !array_key_exists('body', $this->references) ||
            !array_key_exists('footer', $this->references)
        ) {
            throw  new MissingSmsReferencesException('header, body & footer keys must be defined in references array');
        }
    }

    public function getReceiver()
    {
        $this->receiver = strpos(
            $this->data->mpmPerson->addresses[0]->phone,
            '+'
        ) === 0 ? $this->data->mpmPerson->addresses[0]->phone : '+' . $this->data->mpmPerson->addresses[0]->phone;

        return $this->receiver;
    }

    public function generateCallback()
    {
        $uuid = (string)Uuid::generate(4);
        $this->callback = sprintf(config()->get('services.sms.callback'), $uuid);
        return $uuid;
    }
}
