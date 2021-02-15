<?php

namespace Inensus\SteamaMeter\Helpers;

use App\Models\PaymentHistory;
use Inensus\SteamaMeter\Models\SteamaCustomer;

class SmsBodyGenerator
{
    public static function generateSmsBody($transaction, $steamaCustomer)
    {

        if (!$transaction) {
            return 'Dear ' . $steamaCustomer->mpmPerson->name . ' ' . $steamaCustomer->mpmPerson->surname . ' your credit balance has reduced under ' . $steamaCustomer->low_balance_warning . ', your currently balance is :' . $steamaCustomer->account_balance;
        }
        $body = 'Dear ' . $steamaCustomer->mpmPerson->name . ' ' . $steamaCustomer->mpmPerson->surname . ' your payment has received.';
        $payments = $transaction->paymentHistories()->get();

        foreach ($payments as $payment) {
            $body .=  PHP_EOL . self::generateEnergyConfirmationBody($steamaCustomer, $payment);
        }
        return $body;
    }
    private static function generateEnergyConfirmationBody(SteamaCustomer $steamaCustomer, PaymentHistory $paymentHistory): string
    {

        $token = $paymentHistory->paidFor()->first();
        $transaction = $paymentHistory->transaction()->first();

        return 'Meter: {' . $transaction->message . '}, ' . $token->token . ' Unit ' . $token->energy . '. ' . config('steama.currency') . $paymentHistory->amount;
    }
}
