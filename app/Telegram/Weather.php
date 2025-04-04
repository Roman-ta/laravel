<?php

namespace App\Telegram;

use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use DiDom\Document;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Get Weather
 */
class Weather extends WebhookHandler
{
    private Document|null $document;
    public string|null $weatherApi;
    private string|null $botToken;
    private object|null $client;

    /**
     *
     */
    public function __construct()
    {
        $this->document = new Document();
        $this->weatherApi = env('WEATHER_API', '');
        $this->botToken = env('BOT_TOKEN', '');
        $this->client = new Client();
    }

    /**
     * @param TelegraphChat $chat
     * @return void
     */
    public function startWeather(TelegraphChat $chat)
    {

        Telegraph::bot($this->botToken)->chat($chat->chat_id)->message("Отлично, ты хочешь узнать погоду, пиши город")->send();
        Cache::put("weather-{$chat->chat_id}", [
            'controller' => 'weather'
        ], now()->addMinutes(10));
    }

    /**
     * @param array $data
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function today(array $data, TelegraphChat $chat): void
    {
        try {
            $result = $this->getWeatherApiResult($data['city'], $data['api']);
            $response = $this->getWeatherForDay($result);
            Telegraph::chat($chat->chat_id)
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
    public function week(array $data, TelegraphChat $chat):void
    {
        try {
            $result = $this->getWeatherApiResult($data['city'], $data['api']);
            $response = $this->getWeatherForWeek($result);
            Telegraph::chat($chat->chat_id)
                ->photo($response['photo'])
                ->message($response['message'])
                ->send();
        } catch (\Exception $e) {
            Log::error('Error while sending message: ' . $e->getMessage());
        }

    }

    /**
     * @param $res
     * @return string[]
     */
    private function getWeatherForWeek($res)
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
     * @return string[]|void
     */
    private function getWeatherForDay($res)
    {
        if (empty($res)) {
            log::debug('Ответ api пустой');
            $this->reply('Ошибка в api');
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
     * @param string $message
     * @return array|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getDefaultWeatherResult(string $message) : array|null
    {
       return $this->client->request('GET', "https://api.openweathermap.org/data/2.5/weather", [
            'query' => [
                'q' => $message,
                'appid' => $this->weatherApi,
                'units' => 'metric',
                'lang' => 'ru'
            ]
        ]);
    }
}
