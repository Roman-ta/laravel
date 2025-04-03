<?php

namespace App\Telegram;


/**
 *
 */
class Weather
{
    public string|null $weatherApi;

    private object $client;
    private string $botToken;

    public function __construct()
    {

    }


    public function city($message, $chatId)
    {
        try {
            $city = $this->client->request('GET', "https://api.openweathermap.org/data/2.5/weather", [
                'query' => [
                    'q' => $message->value(),
                    'appid' => $this->weatherApi,
                    'units' => 'metric',
                    'lang' => 'ru'
                ]
            ]);

            Telegraph::bot($this->botToken)->chat($chatId)
                ->message("❓ Что тебя интересует: \n☀️ Погода на сегодня \n📅 Прогноз на неделю \n Нажми на кнопку ниже, чтобы выбрать! 👇")
                ->keyboard(Keyboard::make()->row([
                    Button::make('На сегодня')->action('today')->param('city', $message->value())->param('api', 'weather'),
                    Button::make('На 5 дней')->action('week')->param('city', $message->value())->param('api', 'forecast')
                ]))->send();
        } catch (\Exception $e) {
            Telegraph::bot($this->botToken)->chat($chatId)->message('Не могу определить город, попробуй еще раз!')->send();
            return;
        }
    }

    /**
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function today($city) :void
    {
        Log::info('test', [$city]);
        return;
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
     * Регистрируем обработчики действий.
     */
    public static function registerActions(): array
    {
        return [
            'today' => 'today', // Связали действие 'today' с методом 'today'
            'week' => 'week',    // Здесь аналогично можно добавлять другие действия
        ];
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
}
