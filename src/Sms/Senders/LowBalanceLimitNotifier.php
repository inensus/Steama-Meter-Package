<?php

namespace Inensus\SteamaMeter\Sms\Senders;

class LowBalanceLimitNotifier extends SteamaSmsSender
{
    protected $references = [
        'header' => 'SteamaSmsLowBalanceHeader',
        'body' => 'SteamaSmsLowBalanceBody',
        'footer' => 'SteamaSmsLowBalanceFooter'
    ];
}
