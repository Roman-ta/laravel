<?php

namespace App\Telegram;

use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DiDom\Document;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Currency extends WebhookHandler
{
    private object|null $client = null;
    private object|null $document = null;
    private string $bankUrl;
    private $currencyArray;
    private $botToken;

    public function __construct()
    {
        $this->client = new Client();
        $this->document = new Document();
        $this->bankUrl = "https://www.agroprombank.com/";

        $this->botToken = env('BOT_TOKEN', '');
    }





}
