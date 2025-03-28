<?php

namespace App\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Facades\Telegraph;

class Currency extends WebhookHandler
{
    /**
     * @param $chat
     * @param $customerName
     * @return void
     */
    public function test($chat, $customerName = 'Roman') : void
    {
        Telegraph::chat($chat)->message($customerName)->send();
    }

    public function run()
    {

    }
}
