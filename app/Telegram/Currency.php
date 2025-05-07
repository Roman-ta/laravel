<?php

namespace App\Telegram;

use App\Models\AgroBankModel;
use App\Models\CurrencyModel;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use DiDom\Document;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 *  Currency information
 */
class Currency extends WebhookHandler
{
    private object|null $client;
    private string $bankUrl;
    private object|null $document;
    private array $currency;

    public function __construct()
    {
        parent::__construct();
        $this->client = new Client();
        $this->bankUrl = "https://www.agroprombank.com/";
        $this->document = new Document();
        $this->currency = CurrencyModel::all()->toArray();
    }

    /**
     * @param TelegraphChat $chat
     * @return void
     */
    public function getCurrency(TelegraphChat $chat): void
    {
        try {
            $chatInfo = $chat->info();
            $customerName = $chatInfo['first_name'];
            $keyboard = Keyboard::make();
            foreach ($this->currency as $currency) {
                $keyboard->button($currency['flag'] . $currency['text'])->action('currency')->param('step', 2)
                    ->param('from', $currency['currency'])->width(1 / count($this->currency));
            }
            $keyboard->button('🔙 back')->action('start')->width(1);
            Telegraph::chat($chat->chat_id)
                ->message("👋 Привет, *{$customerName}*!\n\nДавай начнём обмен валют. Сначала выбери, *из какой валюты* ты хочешь перевести 💱")
                ->keyboard($keyboard)->send();
        } catch (\Exception $e) {
            Log::error('Error getCurrency() method ' . $e->getMessage());
        }
    }

    /**
     * @param TelegraphChat $chat
     * @param $from
     * @return void
     */
    public function exchange(TelegraphChat $chat, $from): void
    {
        try {
            $keyboard = Keyboard::make();
            foreach ($this->currency as $label) {

                if ($label['currency'] !== $from) {
                    $keyboard->button($label['flag'] . $label['text'])->action('currency')
                        ->param('step', 3)->param('to', $label['currency'])->param('from', $from)->width(1 / count($this->currency));
                }

            }
            $keyboard->button('🔙 back')->action('currency')->param('step', 1)->width(1);
            Telegraph::chat($chat->chat_id)
                ->message("👍 Отлично, ты выбрал *{$from}*.\n\nТеперь выбери, *в какую валюту* ты хочешь перевести 💹")
                ->keyboard($keyboard)
                ->send();
        } catch (\Exception $e) {
            Log::error('Error exchange() method: ' . $e->getMessage());
        }
    }


    /**
     * @param TelegraphChat $chat
     * @param $from
     * @param $to
     * @return void
     */
    public function exchangeTo(TelegraphChat $chat, $from, $to): void
    {
        Cache::put("exchange-{$chat->chat_id}", ['from' => $from, 'to' => $to, 'controller' => 'currency'], now()->addMinutes(10));

        $keyboard = Keyboard::make()->button('🔙 back')->action('currency')->param('step', 2)->param('from', $from)->param('to', $to)->width(1);

        Telegraph::chat($chat->chat_id)
            ->message("📥 Перевод из *{$from}* в *{$to}*\n\n💰 Введи сумму, которую хочешь обменять.")
            ->keyboard($keyboard)
            ->send();
    }

    /**
     * @return array|void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getDataFromBank()
    {
        try {
            $response = $this->client->get($this->bankUrl);
            $html = $response->getBody()->getContents();

            if (empty($html)) {
                throw new \Exception('Empty HTML response from bank');
            }

            $this->document->loadHTML($html);

            $currencyItems = $this->document->find('#rate-ib tbody tr td:nth-child(2)');
            $currencyBuy = $this->document->find('.exchange-rates-item tbody tr td:nth-child(3)');
            $currencySel = $this->document->find('.exchange-rates-item tbody tr td:nth-child(4)');

            if (!$currencyItems || !$currencyBuy || !$currencySel) {
                throw new \Exception('Failed to parse HTML elements');
            }

            $records = [];

            foreach ($currencyItems as $key => $item) {
                $dataValue1 = $item->getAttribute('data-name1') ?: 'N/A';
                $dataValue2 = $item->getAttribute('data-name2') ?: 'N/A';
                $dataBuy = isset($currencyBuy[$key]) ? $currencyBuy[$key]->text() : 'N/A';
                $dataSel = isset($currencySel[$key]) ? $currencySel[$key]->text() : 'N/A';

                $records[] = [
                    'from' => $dataValue1,
                    'to' => $dataValue2,
                    'buy' => $dataBuy,
                    'sell' => $dataSel,
                    'updated_at' => now(),
                    'created_at' => now(),
                ];
            }

            AgroBankModel::upsert(
                $records,
                ['from', 'to'],
                ['buy', 'sell', 'updated_at']
            );

        } catch (\Exception $e) {
            Log::error('Error while fetching bank data: ' . $e->getMessage());
            return [];
        }
    }

    public function getActualCurrencyFromBank(): array
    {
        return AgroBankModel::all()->toArray();
    }
}
