<?php

namespace App\Telegram;

use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Models\TelegraphChat;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Get Weather
 */
class Weather extends WebhookHandler
{
    public string|null $weatherApi;
    private object|null $client;

    public function __construct()
    {
        parent::__construct();
        $this->weatherApi = env('WEATHER_API', '');
        $this->client = new Client();
    }

    /**
     * @param TelegraphChat $chat
     * @param string $city
     * @param string $api
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getWeathers(TelegraphChat $chat, string $city = '', string $api = 'weather'): void
    {
        try {
            $result = $this->getDefaultWeatherResult($city, $api);
            if ($result) {
                if ($api === 'weather') {
                    $response = $this->getWeatherForDay($result);
                } else {
                    $response = $this->getWeatherForWeek($result);
                }
                Telegraph::chat($chat->chat_id)
                    ->photo($response['photo'])
                    ->message($response['message'])
                    ->send();
            }
        } catch (\Exception $e) {
            Log::error('Error while sending message: ' . $e->getMessage());
        }
    }


    /**
     * @param string $city
     * @param string $api
     * @return array|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getDefaultWeatherResult(string $city, string $api = "weather"): ?array
    {
        try {
            $response = $this->client->request('GET', "https://api.openweathermap.org/data/2.5/{$api}", [
                'query' => [
                    'q' => $city,
                    'appid' => $this->weatherApi,
                    'units' => 'metric',
                    'lang' => 'ru'
                ]
            ]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('Ошибка в обработке погоды: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return null;
        }
    }

    /**
     * @param $res
     * @return string[]
     */
    private function getWeatherForWeek($res): array
    {
        $forecastData = $res['list'] ?? [];
        $cityName = $res['city']['name'] ?? 'Неизвестно';
        $responseMessage = "📅 *Прогноз погоды для {$cityName}*\n\n";
        $icons = "https://openweathermap.org/img/wn/01d.png";
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
    private function getWeatherForDay($res)
    {
        try {
            $icon = $res['weather'][0]['icon'] ?? '01d';
            $icons = "https://openweathermap.org/img/wn/{$icon}@4x.png";
            $temperature = round($res['main']['temp'] ?? 0, 1);
            $temperatureFeels = round($res['main']['feels_like'] ?? 0, 0);
            $windSpeed = $res['wind']['speed'] ?? 0;

            $responseMessage = "🌡 Температура в городе *{$res['name']}*:  *{$temperature}°C* ({$res['weather'][0]['description']})\n";
            $responseMessage .= "😌 Ощущается как: *{$temperatureFeels}°C*\n";
            $responseMessage .= "💨 Скорость ветра: *{$windSpeed} м/с*";
            return [
                'message' => $responseMessage,
                'photo' => $icons,
            ];
        } catch (\Exception $e) {
            Log::error('Error while sending message: ' . $e->getMessage());
        }
    }
}
