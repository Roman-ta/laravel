<?php

namespace App\Telegram;

use App\Telegram\Currency;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use DiDom\Document;
use GuzzleHttp\Client;
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

    private object|null $document;
    private string $bankUrl;

    private array $state;
    private array $currencyArray = [
        'USD' => '🇺🇸 USD',
        'EUR' => '🇪🇺 EUR',
        'MDL' => '🇲🇩 MDL',
        'RUB' => '🇷🇺 RUB',
        'RUP' => '⚒ RUP',
    ];

    public function __construct()
    {
        $this->client = new Client();
        $this->document = new Document();
        $this->weatherApi = env('WEATHER_API', '');
        $this->botToken = env('BOT_TOKEN', '');
        $this->bankUrl = "https://www.agroprombank.com/";
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

    public function weather(): void
    {
        Telegraph::bot($this->botToken)->chat($this->chat->chat_id)->message("Отлично, ты хочешь узнать погоду, пиши город")->send();
        Cache::put("weather-{$this->chat->chat_id}", [
            'controller' => 'weather'
        ], now()->addMinutes(10));
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
                $keyboard->button($currency)->action('exchange')->param('from', $key)->width(1 / count($this->currencyArray));
            }
            $keyboard->button('🔙 back')->action('start')->param('name', $customerName)->width(1);
            Telegraph::bot($this->botToken)->chat($this->chat->chat_id)
                ->message("Отлично, выбери *из какой валюты* тебе нужно перевести")
                ->keyboard($keyboard)->send();
            Log::info('Message sent successfully!');
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
        if (isset($this->currencyArray[$from])) {
            unset($this->currencyArray[$from]);
        }
        try {
            $keyboard = Keyboard::make();

            foreach ($this->currencyArray as $key => $label) {
                $keyboard->button($label)->action('exchangeTo')->param('to', $key)->param('from', $from)->width(1 / count($this->currencyArray));
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
        $keyboard = Keyboard::make();
        $keyboard->button('🔙 back')->action('exchange')->param('from', $from)->width(1);
        Telegraph::bot($this->botToken)->chat($this->chat->chat_id)
            ->message("Отлично, ты хочешь перевести из *{$from}* в *{$to}*, теперь введи сумму")
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
        $dataFromCurrency = Cache::get("exchange-{$this->chat->chat_id}");
        $dataFromWeatherSubs = Cache::get("weather_subs-{$this->chat->chat_id}");
        if (!empty($dataFromCurrency)) {
            $result = $this->getDataFromBank();
            $response = '';
            foreach ($result as $key => $item) {
                Log::info('test', $item);
                if (in_array($dataFromCurrency['from'], $item) && in_array($dataFromCurrency['to'], $item)) {
                    if ($item[0] == $dataFromCurrency['from']) {
                        $response = $message->value() * $item['buy'];
                    }
                    if ($item[1] == $dataFromCurrency['from']) {
                        $value = str_replace(',', '', $message->value());
                        $this->reply($message->value() . " " . $value . ' ' . $item['sell']);
                        $response = round($value / $item['sell'], 2);
                    }
                }
            }
            $this->reply("{$message->value()} *{$dataFromCurrency['from']}* ровняется {$response} *{$dataFromCurrency['to']}*");

        } else if (!empty($dataFromWeatherSubs)) {
           Log::info($message->value(), $dataFromWeatherSubs);

        } else {
            try {
                $this->client->request('GET', "https://api.openweathermap.org/data/2.5/weather", [
                    'query' => [
                        'q' => $message->value(),
                        'appid' => $this->weatherApi,
                        'units' => 'metric',
                        'lang' => 'ru'
                    ]
                ]);
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

    /**
     * @return array
     */
    private function getDataFromButtons()
    {
        return [
            'city' => $this->data->get('city'),
            'api' => $this->data->get('api')
        ];
    }

    /**
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function today()
    {
        $buttonsData = $this->getDataFromButtons();
        try {
            $result = $this->getWeatherApiResult($buttonsData['city'], $buttonsData['api']);
            $response = $this->getWhetherForDay($result);
            Telegraph::chat($this->chat->chat_id)
                ->photo($response['photo'])
                ->message($response['message'])
                ->send();
        } catch (\Exception $e) {
            Log::error('Error while sending message: ' . $e->getMessage());
        }
    }

    /**
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function week()
    {
        $buttonsData = $this->getDataFromButtons();
        try {
            $result = $this->getWeatherApiResult($buttonsData['city'], $buttonsData['api']);
            $response = $this->getWhetherForWeek($result);
            Telegraph::chat($this->chat->chat_id)
                ->photo($response['photo'])
                ->message($response['message'])
                ->send();
        } catch (\Exception $e) {
            Log::error('Error while sending message: ' . $e->getMessage());
        }

    }

    /**
     * @param $city
     * @param $api
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getWeatherApiResult($city, $api)
    {
        $res = $this->client->request('GET', "https://api.openweathermap.org/data/2.5/{$api}", [
            'query' => [
                'q' => $city,
                'appid' => $this->weatherApi,
                'units' => 'metric',
                'lang' => 'ru'
            ]
        ]);
        return json_decode($res->getBody(), true);
    }

    /**
     * @param $res
     * @return string[]
     */
    private function getWhetherForWeek($res)
    {
        if (empty($res)) {
            log::debug('Что то с api');
        }
        $forecastData = $res['list'] ?? [];
        $cityName = $res['city']['name'] ?? 'Неизвестно';

        $responseMessage = "📅 *Прогноз погоды для {$cityName}*\n\n";

        foreach ($forecastData as $key => $item) {
            if ($key % 8 === 0) {
                $date = date('d.m', $item['dt']);
                $icon = $item['weather'][0]['icon'] ?? '01d';
                $icons = "https://openweathermap.org/img/wn/{$icon}.png";
                $temp = round($item['main']['temp'], 1);
                $desc = ucfirst($item['weather'][0]['description'] ?? 'Нет данных');
                $wind = $item['wind']['speed'] ?? 0;

                $responseMessage .= "📅 *{$date}*: {$desc}\n";
                $responseMessage .= "🌡 *Температура*: {$temp}°C\n";
                $responseMessage .= "💨 *Ветер*: {$wind} м/с\n";
            }
        }

        return [
            'message' => $responseMessage,
            'photo' => $icons
        ];

    }

    /**
     * @param $res
     * @return string[]|void
     */
    private function getWhetherForDay($res)
    {
        if (empty($res)) {
            log::debug('Ответ api пустой');
        }
        try {
            // Получаем данные о погоде
            $icon = $res['weather'][0]['icon'] ?? '01d';
            $icons = "https://openweathermap.org/img/wn/{$icon}@4x.png";
            $temperature = round($res['main']['temp'] ?? 0, 1);
            $temperatureFeels = round($res['main']['feels_like'] ?? 0, 0);
            $windSpeed = $res['wind']['speed'] ?? 0;

            $responseMessage = "🌡 Температура в городе *{$res['name']}*:  *{$temperature}°C* ({$res['weather'][0]['description']})\n";
            $responseMessage .= "😌 Ощущается как: *{$temperatureFeels}°C*\n";
            $responseMessage .= "💨 Скорость ветра: *{$windSpeed} м/с*";
            return $result = [
                'message' => $responseMessage,
                'photo' => $icons,
            ];
        } catch (\Exception $e) {
            Log::error('Error while sending message: ' . $e->getMessage());
        }

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
    private function getDataFromBank()
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
