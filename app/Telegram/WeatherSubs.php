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

    /**
     * @param TelegraphChat $chat
     * @param $city
     * @return void
     */
    public function start(TelegraphChat $chat, $city): void
    {
        $keyboard = Helper::getHoursKeyboard("subWeather", $city);
        Telegraph::chat($chat->chat_id)
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
    public function getHour(TelegraphChat $chat, $hour, $city): void
    {
        $keyboard = Helper::getMinutesKeyboard("subWeather", $hour, $city);
        $keyboard->button('🔙 back')->action('subWeather')->param('step', 1)->width(1)->param('city', $city);

        Telegraph::chat($chat->chat_id)
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
    public function getMinute(TelegraphChat $chat, $hour, $minute, $city): void
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
     */
    public function finish(TelegraphChat $chat, $hour, $minute, $city): void
    {
        try {
            $chatInfo = $chat->info();

            Telegraph::chat($chat->chat_id)
                ->message("Теперь каждый день в *{$hour}:{$minute}* ты будешь получать уведомление о погоде в городе *{$city}*")
                ->send();

            $customerSubscriptionExists = WeatherSubscriptionModel::where('chat_id', $chat->chat_id)->exists();
            if (!$customerSubscriptionExists) {
                WeatherSubscriptionModel::create([
                    'chat_id' => $chat->chat_id,
                    'name' => $chatInfo['first_name'],
                    'city' => $city,
                    'hour' => $hour,
                    'minute' => $minute,
                ]);
            } else {
                WeatherSubscriptionModel::where('chat_id', $chat->chat_id)->update([
                    'city' => $city,
                    'hour' => $hour,
                    'minute' => $minute,
                ]);
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    /**
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getSubscriptionWeather(): void
    {
        try {
            $customerSubscriptions = WeatherSubscriptionModel::all();
            $currentTime = date('H:i');
            foreach ($customerSubscriptions as $customerSubscription) {
                $hour = Helper::addZeroForTime($customerSubscription['hour']);
                $minute = Helper::addZeroForTime($customerSubscription['minute']);
                $time = $hour . ':' . $minute;

                if ($time == $currentTime) {
                    $chat = TelegraphChat::where('chat_id', $customerSubscription['chat_id'])->first();
                    Telegraph::chat($chat->chat_id)
                        ->message("Привет *{$customerSubscription['name']}* спасибо за подписку на погоду, вот она")
                        ->send();
                    (new Weather())->getWeathers($chat, $customerSubscription['city']);
                }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
