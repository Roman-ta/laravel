<?php

namespace App\Telegram;

use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use DiDom\Document;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Stringable;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\Button;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class Handle extends WebhookHandler
{
    private object|null $client;

    public string|null $weatherApi;
    private string|null $botToken;
    private string $bankUrl;
    private object|null $document;

    private Weather $weather;

    private $currencyArray;

    public function __construct()
    {
        $this->client = new Client();
        $this->botToken = env('BOT_TOKEN', '');
        $this->currencyArray = DB::table('currency_list')->get();
        $this->currencyArray = json_decode(json_encode($this->currencyArray), true);
        $this->bankUrl = "https://www.agroprombank.com/";
        $this->document = new Document();
        $this->weather = new Weather();
    }

    /**
     * @return void
     */
    public function start(): void
    {
        $nameUser = $this->message ? $this->message->from()->firstName() : ($this->data ? $this->data->get('name') : '');
        try {
            Telegraph::bot($this->botToken)->chat($this->chat->chat_id)
                ->message("🌤 Привет! *{$nameUser}* Я твой личный бот. Готов помочь узнать погоду и рассчитать валюту в любой момент! что тебе нужно?")
                ->keyboard(Keyboard::make()->row([
                    Button::make('Погода')->action('weather'),
                    Button::make('Курс валют')->action('currency'),
                ]))->send();
            Log::info('Message sent successfully!');
        } catch (\Exception $e) {
            Log::error('Error while sending message: ' . $e->getMessage());
        }
    }

    /**
     * @return void
     */
    public function weather(): void
    {
        $this->weather->startWeather($this->chat);
    }

    /**
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function today():void
    {
        $data = $this->getDataFromButtons();
        $this->weather->today($data, $this->chat);
    }

    /**
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function week():void
    {
        $data = $this->getDataFromButtons();
        $this->weather->week($data, $this->chat);
    }

    /**
     * @return array
     */
    private function getDataFromButtons() : array
    {
        return [
            'city' => $this->data->get('city'),
            'api' => $this->data->get('api')
        ];
    }
    /**
     * @return void
     */
    public function currency(): void
    {
        $customerName = $this->callbackQuery->from()->firstName();
        try {
            $keyboard = Keyboard::make();
            foreach ($this->currencyArray as $key => $currency) {
                $keyboard->button($currency['text'])->action('exchange')->param('from', $currency['currency'])->param('name', $customerName)->width(1 / count($this->currencyArray));
            }
            $keyboard->button('🔙 back')->action('start')->width(1);
            Telegraph::bot($this->botToken)->chat($this->chat->chat_id)
                ->message("Отлично, выбери *из какой валюты* тебе нужно перевести")
                ->keyboard($keyboard)->send();
        } catch (\Exception $e) {
            Log::error('Error while sending message: ' . $e->getMessage());
        }
    }

    /**
     * @return void
     */
    public function exchange()
    {
        $customerName = $this->callbackQuery->from()->firstName();
        $from = $this->data->get('from');

        try {
            $keyboard = Keyboard::make();
            foreach ($this->currencyArray as $key => $label) {
                if ($label['currency'] == $from) {
                    continue;
                }
                $keyboard->button($label['text'])->action('exchangeTo')->param('to', $label['currency'])->param('from', $from)->width(1 / count($this->currencyArray));
            }
            $keyboard->button('🔙 back')->action('currency')->param('name', $customerName)->width(1);
            Telegraph::bot($this->botToken)->chat($this->chat->chat_id)
                ->message("Теперь выбери, *в какую валюту* хочешь перевести")
                ->keyboard($keyboard)
                ->send();
        } catch (\Exception $e) {
            Log::error('Error while sending message: ' . $e->getMessage());
        }
    }


    /**
     * @return void
     */
    public function exchangeTo()
    {
        $chatId = $this->chat->chat_id;
        $from = $this->data->get('from');
        $to = $this->data->get('to');
        $keyboard = Keyboard::make()->button('🔙 back')->action('exchange')->param('from', $from)->param('to', $to)->width(1);
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


    protected function handleChatMessage(Stringable $message): void
    {
        $chatId = $this->chat->chat_id;
        $dataFromCurrency = Cache::get("exchange-{$this->chat->chat_id}");
        $dataFromWeatherSubs = Cache::get("weather_subs-{$this->chat->chat_id}");
        $dataFromWeather = Cache::get("weather-{$this->chat->chat_id}");
        if (!empty($dataFromCurrency)) {
            if (!is_numeric($message->value())) {
                $this->reply('Необходимо ввести число');
                return;
            }
            $result = $this->getDataFromBank();
            $response = '';
            foreach ($result as $key => $item) {
                if (in_array($dataFromCurrency['from'], $item) && in_array($dataFromCurrency['to'], $item)) {
                    if ($item[0] == $dataFromCurrency['from']) {
                        $response = $message->value() * $item['buy'];
                    }
                    if ($item[1] == $dataFromCurrency['from']) {
                        $value = str_replace(',', '', $message->value());
                        $response = round($value / $item['sell'], 2);
                    }
                }
            }

            $this->reply("{$message->value()} *{$dataFromCurrency['from']}* ровняется {$response} *{$dataFromCurrency['to']}*");

        } else if (!empty($dataFromWeather)) {
            try {
               $this->weather->getDefaultWeatherResult($message->value());

                Telegraph::bot($this->botToken)->chat($this->chat->chat_id)
                    ->message("❓ Что тебя интересует: \n☀️ Погода на сегодня \n 📅 Прогноз на неделю \nНажми на кнопку ниже, чтобы выбрать! 👇")
                    ->keyboard(Keyboard::make()->row([
                        Button::make('На сегодня')->action('today')->param('city', $message->value())->param('api', 'weather'),
                        Button::make('На 5 дней')->action('week')->param('city', $message->value())->param('api', 'forecast')
                    ]))->send();
            } catch (\Exception $e) {
                $this->reply("Не могу распознать город, попробуй еще раз");
                return;
            }
        }
    }

    private function createButton(string $action = '', string $from = '', string $to = '', int $width = 1, string $label = '🔙 back'): \DefStudio\Telegraph\Proxies\KeyboardButtonProxy
    {
        return Keyboard::make()->button($label)->action($action)->param('from', $from)->param('to', $to)->width($width);
    }



    /**
     * @return void
     */
    public function weather_subs(): void
    {
        $customer = $this->message->from();
        $keyBoard = ReplyKeyboard::make();

        Telegraph::bot($this->botToken)->chat($this->chat->chat_id)
            ->message('Я могу отправлять сообщение с данными о погоде в удобное для тебя время, выбери')
            ->replyKeyboard($keyBoard
                ->row([
                    ReplyButton::make('7:00'),
                    ReplyButton::make('7:30'),
                    ReplyButton::make('8:00'),
                    ReplyButton::make('8:30'),
                ])->row([
                    ReplyButton::make('9:00'),
                    ReplyButton::make('9:30'),
                    ReplyButton::make('10:00'),
                    ReplyButton::make('10:30'),
                ])
                ->row([
                    ReplyButton::make('11:00'),
                    ReplyButton::make('11:30'),
                    ReplyButton::make('12:00'),
                    ReplyButton::make('12:30'),
                ])->row([
                    ReplyButton::make('13:00'),
                    ReplyButton::make('13:30'),
                    ReplyButton::make('14:00'),
                    ReplyButton::make('14:30'),
                ])
                ->row([
                    ReplyButton::make('15:00'),
                    ReplyButton::make('15:30'),
                    ReplyButton::make('16:00'),
                    ReplyButton::make('16:30'),
                ])->row([
                    ReplyButton::make('17:00'),
                    ReplyButton::make('17:30'),
                    ReplyButton::make('18:00'),
                    ReplyButton::make('18:30'),
                ])
                ->row([
                    ReplyButton::make('19:00'),
                    ReplyButton::make('19:30'),
                    ReplyButton::make('20:00'),
                    ReplyButton::make('20:30'),
                ])
            )
            ->send();
        Cache::put('weather_subs-' . $this->chat->chat_id, [
            'idCustomer' => $customer->id(),
            'name' => $customer->username(),
        ], now()->addHours(1));
    }


    /**
     * @return void
     */
    public function help(): void
    {
        $helpText = "Привет! Я твой личный погодный бот. Вот что я могу делать:\n\n";
        $helpText .= "/start - Приветствие и начало работы с ботом.\n";
        $helpText .= "Просто введи название города, чтобы узнать погоду!\n\n";
        $helpText .= "Вот что я могу сделать:\n";
        $helpText .= "1. Получить погоду на сегодня.\n";
        $helpText .= "2. Получить прогноз погоды на 5 дней.\n\n";
        $helpText .= "Пример: напиши *Москва* и выбери, что тебя интересует — погода на сегодня или прогноз на неделю.\n\n";
        $helpText .= "Если возникнут проблемы, просто напиши мне, и я постараюсь помочь!";

        $this->reply($helpText);
    }

    public function handleUnknownCommand(Stringable $text): void
    {
        if ($text->value() !== '/start' || $text->value() !== '/help') {
            $this->reply('Неизвестная команда');
        }
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
