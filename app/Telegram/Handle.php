<?php

namespace App\Telegram;

use App\Models\CurrencySubscriptionModel;
use App\Models\WeatherSubscriptionModel;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Models\TelegraphChat;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Stringable;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\Button;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use voku\helper\ASCII;

/**
 * class Handle
 */
class Handle extends WebhookHandler
{
    private string|null $botToken;
    private Weather $weather;
    private Currency $currency;
    private WeatherSubs $weatherSubs;
    private CurrencySubs $currencySubs;
    private Client $client;

    public function __construct()
    {
        $this->botToken = env('BOT_TOKEN', '');
        $this->weather = new Weather();
        $this->currency = new Currency();
        $this->weatherSubs = new WeatherSubs();
        $this->client = new Client();
        $this->currencySubs = new CurrencySubs();
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
                    Button::make('Погода')->action('weather')->param('weather', 1),
                    Button::make('Курс валют')->action('currency')->param('step', 1),
                ]))->send();
            Log::info('Message sent successfully!');
        } catch (\Exception $e) {
            Log::error('Error while sending message: ' . $e->getMessage());
        }
    }

    /**
     * @return void
     */
    public function currency(): void
    {
        $step = $this->data->get('step') ?? 1;
        $from = $this->data->get('from') ?? '';
        $to = $this->data->get('to') ?? '';
        switch ($step) {
            case 1:
                $this->currency->getCurrency($this->chat);
                break;
            case 2:
                $this->currency->exchange($this->chat, $this->callbackQuery, $from);
                break;
            case 3:
                $this->currency->exchangeTo($this->chat, $this->callbackQuery, $from, $to);
                break;
        }
    }

    /**
     * @return void
     * @throws \DiDom\Exceptions\InvalidSelectorException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function currency_subs(): void
    {
        $step = $this->data->get('step') ?? 1;
        $hour = $this->data->get('hour') ?? 0;
        $minute = $this->data->get('minute') ?? 0;
        switch ($step) {
            case 1:
                $this->currencySubs->start($this->chat);
                break;
            case 2:
                $this->currencySubs->currencySubscriptionGetHours($this->chat, $hour);
                break;
            case 3:
                $this->currencySubs->currencySubscriptionGetMinutes($this->chat, $hour, $minute);
                break;
            case 4:
                $this->currencySubs->finish($this->chat, $hour, $minute);
                break;
        }

    }


    public function ai()
    {
        $question = "Курс валют пмр";

        try {
            $response = $this->client->post('https://openrouter.ai/api/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'https://yourdomain.com', // можно свой сайт
                    'X-Title' => 'My Telegram Bot'
                ],
                'json' => [
                    'model' => 'openai/gpt-3.5-turbo', // или другие: mistral, anthropic/claude-3-opus
                    'messages' => [
                        ['role' => 'user', 'content' => $question]
                    ]
                ]
            ]);

            $body = json_decode($response->getBody(), true);
            $reply = $body['choices'][0]['message']['content'] ?? 'Не удалось получить ответ от GPT 😞';

            Telegraph::bot($this->botToken)->chat($this->chat->chat_id)->message($reply)->send();

        } catch (\Exception $e) {
            Log::error('Ошибка при обращении к GPT: ' . $e->getMessage());
            $this->reply("⚠️ Не удалось получить ответ от ChatGPT. Попробуй позже.");
        }
    }

    /**
     * @return void
     */
    public function weather(): void
    {
        Cache::put("weather-{$this->chat->chat_id}", [
            'controller' => 'weather'
        ], now()->addMinutes(10));
        $this->weather->startWeather($this->chat);
    }

    /**
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getWeather(): void
    {
        if ($this->data->get('api') == 'weather') {
            $this->weather->today($this->chat, $this->data->get('city'), $this->data->get('api'));
        }
        $this->weather->week($this->chat, $this->data->get('city'), $this->data->get('api'));

    }

    /**
     * @param Stringable $message
     * @return void
     * @throws \DiDom\Exceptions\InvalidSelectorException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function handleChatMessage(Stringable $message): void
    {
        $test = DB::table('cache')->orderBy('expiration', 'desc')->first();

        if (str_contains($test->key, 'exchange')) {
            $dataFromCurrency = Cache::get("exchange-{$this->chat->chat_id}");

            if (!is_numeric($message->value())) {
                $this->reply("🚫 Пожалуйста, введи *числовое значение* для суммы обмена.");
                return;
            }

            $result = $this->currency->getDataFromBank();
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

            $this->reply("💱 *{$message->value()} {$dataFromCurrency['from']}* ≈ *{$response} {$dataFromCurrency['to']}*");
        } else if (str_contains($test->key, 'weather_subs')) {
            // Жестко задаём Tiraspol, если написали на кириллице
            $city = ($message->value() === 'тирасполь' || $message->value() === 'Тирасполь') ? 'Tiraspol' : $message->value();

            $weatherData = $this->weather->getDefaultWeatherResult($city);

            if ($weatherData == null) {
                $this->reply("🌧 Не удалось распознать город *{$message->value()}*. Попробуй ввести его ещё раз.");
                return;
            }

            $this->weatherSubs->start($this->chat, $city);
        } else {
            try {
                $weatherData = $this->weather->getDefaultWeatherResult($message->value());

                if ($weatherData == null) {
                    $this->reply("🌍 Не удалось найти такой город. Проверь правильность написания и попробуй снова.");
                    return;
                }

                Telegraph::bot($this->botToken)->chat($this->chat->chat_id)
                    ->message("📍 Город *{$message->value()}* найден!\n\nЧто именно тебе нужно?\n☀️ Погода на *сегодня*\n📅 Прогноз на *5 дней*\n\n👇 Нажми кнопку ниже:")
                    ->keyboard(Keyboard::make()->row([
                        Button::make('☀️ На сегодня')->action('getWeather')->param('city', $message->value())->param('api', 'weather'),
                        Button::make('📅 На 5 дней')->action('getWeather')->param('city', $message->value())->param('api', 'forecast')
                    ]))->send();

            } catch (\Exception $e) {
                Log::error('Ошибка в обработке погоды: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                $this->reply("⚠️ Что-то пошло не так. Попробуй позже или укажи другой город.");
            }
        }
    }


    /**
     * @return void
     */
    public function weather_subs(): void
    {
        $customer = $this->message->from();
        Cache::put('weather_subs-' . $this->chat->chat_id, [
            'idCustomer' => $customer->id(),
            'name' => $customer->firstName(),
        ], now()->addMinute(10));

        $weatherHaveData = WeatherSubscriptionModel::where('chatId', $this->chat->chat_id)->first();

        if (!$weatherHaveData) {
            Telegraph::bot($this->botToken)->chat($this->chat->chat_id)
                ->message("👋 Привет! Я могу ежедневно присылать тебе *прогноз погоды* в любое удобное время! 🌤️\n\n📍 Просто напиши название города, который тебя интересует — и мы всё настроим!")
                ->send();
        } else {
            Telegraph::bot($this->botToken)->chat($this->chat->chat_id)
                ->message("☀️ Привет, *{$weatherHaveData['name']}*! У тебя уже есть активная подписка 📨\n\n📍 Город: *{$weatherHaveData['city']}*\n🕒 Время отправки: *{$weatherHaveData['hour']}:{$weatherHaveData['minute']}*\n\nЕсли хочешь — просто напиши *новый город*, и я всё обновлю!")
                ->send();
        }
    }


    public function subWeather(): void
    {
        $step = $this->data->get('step') ?? 0;
        $hour = $this->data->get('hour') ?? 0;
        $minute = $this->data->get('minute') ?? 0;
        $city = $this->data->get('city') ?? '';
        switch ((string)$step) {
            case '1':
                $this->weatherSubs->start($this->chat, $city);
                break;
            case '2':
                $this->weatherSubs->getHour($this->chat, $hour, $city);
                break;
            case '3':
                $this->weatherSubs->getminute($this->chat, $hour, $minute, $city);
                break;
            case '4':
                $this->weatherSubs->finish($this->chat, $hour, $minute, $city);
                break;
        }
    }

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

}
