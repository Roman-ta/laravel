<?php

namespace App\Telegram;

use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Stringable;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\Button;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * class Handle
 */
class Handle extends WebhookHandler
{

    private string|null $botToken;

    private Weather $weather;
    private Currency $currency;

    private $currencyArray;

    public function __construct()
    {
        $this->botToken = env('BOT_TOKEN', '');
        $this->weather = new Weather();
        $this->currency = new Currency();
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
        $step = $this->data->get('step');
        $from = $this->data->get('from') ?? '';
        $to = $this->data->get('to') ?? '';
        switch ($step) {
            case 1:
                $this->currency->getCurrency($this->chat, $this->callbackQuery);
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
        $params = [
            'city' => $this->data->get('city') ?? '',
            'api' => $this->data->get('api') ?? ''
        ];
        if ($params['api'] == 'weather') {
            $this->weather->today($params, $this->chat);
        }
        $this->weather->week($params, $this->chat);

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
            $this->reply("{$message->value()} *{$dataFromCurrency['from']}* ровняется {$response} *{$dataFromCurrency['to']}*");
        } else if (!empty($dataFromWeather)) {
            try {
                $weatherData = $this->weather->getDefaultWeatherResult($message->value());
                if ($weatherData == null) {
                    $this->reply('Не могу распознать город, попробуй еще раз');
                    return;
                }
                Telegraph::bot($this->botToken)->chat($this->chat->chat_id)
                    ->message("❓ Что тебя интересует: \n☀️ Погода на сегодня \n 📅 Прогноз на неделю \nНажми на кнопку ниже, чтобы выбрать! 👇")
                    ->keyboard(Keyboard::make()->row([
                        Button::make('На сегодня')->action('getWeather')->param('city', $message->value())->param('api', 'weather'),
                        Button::make('На 5 дней')->action('getWeather')->param('city', $message->value())->param('api', 'forecast')
                    ]))->send();
            } catch (\Exception $e) {
                Log::error('Ошибка в обработке погоды: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                $this->reply("Не могу распознать город, попробуй еще раз");
            }
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
