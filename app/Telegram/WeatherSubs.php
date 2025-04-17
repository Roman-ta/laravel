<?php

namespace App\Telegram;

use App\Models\WeatherSubscriptionModel;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Log;

/**
 * Weather subs подписка на погоду
 */
class WeatherSubs extends WebhookHandler
{
    private string|null $botToken;

    public function __construct()
    {
        $this->botToken = env('BOT_TOKEN', '');
    }

    /**
     * @param TelegraphChat $chat
     * @param $city
     * @return void
     */
    public function start(TelegraphChat $chat, $city): void
    {
        $keyboard = Keyboard::make();
        $row = [];
        for ($i = 0; $i < 24; $i++) {
            $hour = (string)$i;
            if (strlen($hour) < 2) {
                $hour = '0' . $hour;
            }
            $row[] = Button::make($hour)->action('subWeather')->param('hour', $hour)->param('step', 2)->param('city', $city);
        }
        $chunks = array_chunk($row, 6);
        foreach ($chunks as $chunk) {
            $keyboard->row($chunk);
        }
        Telegraph::bot($this->botToken)->chat($chat->chat_id)
            ->message("Супер ты выбрал город *{$city}*  теперь настроим точное время, сперва выбери час")
            ->keyboard($keyboard)
            ->send();
    }

    /**
     * @param TelegraphChat $chat
     * @param $hour
     * @param $city
     * @return void
     */
    public function getHour(TelegraphChat $chat, $hour, $city)
    {
        $keyboard = Keyboard::make();
        $row = [];
        for ($i = 0; $i < 60; $i += 5) {
            $minute = (string)$i;
            if (strlen($minute) < 2) {
                $minute = '0' . $minute;
            }
            $row[] = Button::make($minute)->action('subWeather')->param('hour', $hour)->param('minute', $minute)->param('step', 3)->param('city', $city);
        }
        $chunks = array_chunk($row, 6);
        foreach ($chunks as $chunk) {
            $keyboard->row($chunk);
        }
        $keyboard->button('🔙 back')->action('subWeather')->param('step', 1)->width(1)->param('city', $city);
        Telegraph::bot($this->botToken)->chat($chat->chat_id)
            ->message('Отлично, теперь выбери минуты')
            ->keyboard($keyboard)
            ->send();
    }

    /**
     * @param TelegraphChat $chat
     * @param $hour
     * @param $minute
     * @param $city
     * @return void
     */
    public function getminute(TelegraphChat $chat, $hour, $minute, $city)
    {
        $keyboard = Keyboard::make();
        $keyboard->button('🔙 back')->action('subWeather')->param('step', 2)->param('hour', $hour)->width(0.5)->param('city', $city);
        $keyboard->button('Yes')->action('subWeather')->param('step', 4)->width(0.5)->param('hour', $hour)->param('minute', $minute)->param('city', $city);

        Telegraph::chat($chat->chat_id)
            ->message("Отлично, ты выбрал *{$hour}:{$minute}* все верно?")
            ->keyboard($keyboard)
            ->send();
    }

    /**
     * @param TelegraphChat $chat
     * @param $hour
     * @param $minute
     * @param $city
     * @return void
     * @throws \DefStudio\Telegraph\Exceptions\TelegraphException
     */
    public function finish(TelegraphChat $chat, $hour, $minute, $city)
    {
        $chatInfo = $chat->info();

        Telegraph::chat($chat->chat_id)
            ->message("Теперь каждый день в *{$hour}:{$minute}* ты будешь получать уведомление о погоде в городе *{$city}*")
            ->send();

        $customerSubscriptionExists = WeatherSubscriptionModel::where('chatId', $chat->chat_id)->exists();
        if (!$customerSubscriptionExists) {
            WeatherSubscriptionModel::create([
                'chatId' => $chat->chat_id,
                'name' => $chatInfo['first_name'],
                'city' => $city,
                'hour' => $hour,
                'minute' => $minute,
            ]);
        } else {
            WeatherSubscriptionModel::where('chatId', $chat->chat_id)->update([
                'city' => $city,
                'hour' => $hour,
                'minute' => $minute,
            ]);
        }

    }

    /**
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getSubscriptionWeather()
    {
        $customerSubscriptions = WeatherSubscriptionModel::all();
        $currentTime = date('H:i');
        foreach ($customerSubscriptions as $customerSubscription) {
            $time = $customerSubscription['hour'] . ':' . $customerSubscription['minute'];
            if ($time == $currentTime) {
                Telegraph::chat($customerSubscription['chatId'])
                    ->message("Привет *{$customerSubscription['name']}* спасибо за подписку на погоду, вот она")
                    ->send();
                $chat = TelegraphChat::where('chat_id', $customerSubscription['chatId'])->first();
                (new Weather())->today($chat, $customerSubscription['city']);
            }
        }
    }
}
