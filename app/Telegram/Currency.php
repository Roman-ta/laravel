<?php

namespace App\Telegram;

use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use DiDom\Document;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Currency extends WebhookHandler
{
    private object|null $client;

    private string|null $botToken;
    private string $bankUrl;
    private object|null $document;

    public function __construct()
    {
        $this->client = new Client();
        $this->botToken = env('BOT_TOKEN', '');
        $this->bankUrl = "https://www.agroprombank.com/";
        $this->document = new Document();
    }

    public function getCurrency(TelegraphChat $chat, $callBack): void
    {

        $customerName = $callBack->from()->firstName();
        $currencyArray = DB::table('currency_list')->get();
        $currencyArray = json_decode(json_encode($currencyArray), true);
        try {
            $keyboard = Keyboard::make();
            foreach ($currencyArray as $key => $currency) {
                $keyboard->button($currency['text'])->action('currency')->param('step', 2)
                    ->param('from', $currency['currency'])->param('name', $customerName)->width(1 / count($currencyArray));
            }
            $keyboard->button('🔙 back')->action('start')->width(1);
            Telegraph::bot($this->botToken)->chat($chat->chat_id)
                ->message("Отлично, выбери *из какой валюты* тебе нужно перевести")
                ->keyboard($keyboard)->send();
        } catch (\Exception $e) {
            Log::error('Error while sending message: ' . $e->getMessage());
        }
    }

    /**
     * @return void
     */
    public function exchange(TelegraphChat $chat, $callBack, $from): void
    {
        $customerName = $callBack->from()->firstName();
        $currencyArray = DB::table('currency_list')->get();
        $currencyArray = json_decode(json_encode($currencyArray), true);

        try {
            $keyboard = Keyboard::make();
            foreach ($currencyArray as $key => $label) {
                if ($label['currency'] == $from) {
                    continue;
                }
                $keyboard->button($label['text'])->action('currency')
                    ->param('step', 3)->param('to', $label['currency'])->param('from', $from)->width(1 / count($currencyArray));
            }
            $keyboard->button('🔙 back')->action('currency')->param('step', 1)->param('name', $customerName)->width(1);
            Telegraph::bot($this->botToken)->chat($chat->chat_id)
                ->message("Теперь выбери, *в какую валюту* хочешь перевести")
                ->keyboard($keyboard)
                ->send();
        } catch (\Exception $e) {
            Log::error('Error while sending message: ' . $e->getMessage());
        }
    }


    /**
     * @param TelegraphChat $chat
     * @param $callBack
     * @param $from
     * @param $to
     * @return void
     */
    public function exchangeTo(TelegraphChat $chat, $callBack, $from, $to): void
    {
        $chatId = $chat->chat_id;
        $keyboard = Keyboard::make()->button('🔙 back')->action('currency')->param('step', 2)->param('from', $from)->param('to', $to)->width(1);
        Telegraph::bot($this->botToken)->chat($chatId)
            ->message("Отлично, ты хочешь перевести из *{$from}* в *{$to}*. Введи сумму.")
            ->keyboard($keyboard)
            ->send();
        Cache::put("exchange-{$chatId}", [
            'from' => $from,
            'to' => $to,
            'controller' => 'currency'
        ], now()->addMinutes(10));
    }

    /**
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * Get current data from agroprom
     */
    public function getDataFromBank(): array
    {
        $response = $this->client->get($this->bankUrl);
        $html = $response->getBody()->getContents();
        $this->document->loadHTML($html);
        $currencyItems = $this->document->find('#rate-ib tbody tr td:nth-child(2)');
        $currencyBuy = $this->document->find('.exchange-rates-item tbody tr td:nth-child(3)');
        $currencySel = $this->document->find('.exchange-rates-item tbody tr td:nth-child(4)');
        $result = [];
        foreach ($currencyItems as $key => $item) {
            $dataValue1 = $item->getAttribute('data-name1') ?: 'N/A';
            $dataValue2 = $item->getAttribute('data-name2') ?: 'N/A';
            $dataBuy = isset($currencyBuy[$key]) ? $currencyBuy[$key]->text() : 'N/A';
            $dataSel = isset($currencySel[$key]) ? $currencySel[$key]->text() : 'N/A';
            $result[] = [
                0 => $dataValue1,
                1 => $dataValue2,
                'buy' => $dataBuy,
                'sell' => $dataSel,
            ];
        }
        return $result;
    }
}
