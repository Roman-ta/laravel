<?php

namespace App\Telegram;

use App\Models\Cache;
use App\Models\CurrencySubscriptionModel;
use App\Models\WeatherSubscriptionModel;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Log;

/**
 * controller subscription for currency
 */
class CurrencySubs extends WebhookHandler
{
    /**
     * @param TelegraphChat $chat
     * @return void
     */
    public function start(TelegraphChat $chat)
    {
        $chatInfo = $this->getChatInfo($chat);

        $keyboard = (new Keyboard())::make();
        $row = [];
        for ($i = 0; $i < 24; $i++) {
            $hour = (string)$i;
            if (strlen($hour) < 2) {
                $hour = '0' . $hour;
            }
            $row[] = Button::make($hour)->action('currency_subs')->param('hour', $hour)->param('step', 2);
        }
        $chunks = array_chunk($row, 6);
        foreach ($chunks as $chunk) {
            $keyboard->row($chunk);
        }
        $currencyHaveSubscription = CurrencySubscriptionModel::where('chat_id', $chat->chat_id)->first();
        if (!$currencyHaveSubscription) {
            Telegraph::chat($chat->chat_id)
                ->message("Привет, *{$chatInfo['first_name']}*! Я могу каждый день присылать тебе курс валют в ПМР. Давай выберем удобное время — сначала укажи час ⏰")
                ->keyboard($keyboard)
                ->send();
        } else {
            Telegraph::chat($chat->chat_id)
                ->message("*{$chatInfo['first_name']}* ты уже подписан(а) на ежедневные уведомления о курсе валют в *{$currencyHaveSubscription['hour']}:{$currencyHaveSubscription['minute']}* 📩\nЕсли хочешь изменить время — просто выбери новое ⏰")
                ->keyboard($keyboard)
                ->send();
        }
    }

    public function currencySubscriptionGetHours(TelegraphChat $chat, $hour)
    {
        $keyboard = Keyboard::make();
        $row = [];
        for ($i = 0; $i < 60; $i += 5) {
            $minute = (string)$i;
            if (strlen($minute) < 2) {
                $minute = '0' . $minute;
            }
            $row[] = Button::make($minute)->action('currency_subs')->param('hour', $hour)->param('minute', $minute)->param('step', 3);
        }
        $chunks = array_chunk($row, 6);
        foreach ($chunks as $chunk) {
            $keyboard->row($chunk);
        }
        if (strlen($hour) < 2) {
            $hour = '0' . $hour;
        }
        $keyboard->button('🔙 back')->action('currency_subs')->param('step', 1)->width(1);
        Telegraph::chat($chat->chat_id)
            ->message('Отлично, теперь выбери минуты')
            ->keyboard($keyboard)
            ->send();

    }

    public function currencySubscriptionGetMinutes(TelegraphChat $chat, $hour, $minute)
    {
        $chatInfo = $this->getChatInfo($chat);
        $keyboard = Keyboard::make();
        $keyboard->button('🔙 back')->action('currency_subs')->param('step', 2)->param('hour', $hour)->width(0.5);
        $keyboard->button('Yes')->action('currency_subs')->param('step', 4)->width(0.5)->param('hour', $hour)->param('minute', $minute);
        if (strlen($minute) < 2) {
            $minute = '0' . $minute;
        }
        Telegraph::chat($chat->chat_id)
            ->message("Отлично {$chatInfo['first_name']}, ты выбрал *{$hour}:{$minute}* все верно?")
            ->keyboard($keyboard)
            ->send();
    }


    /**
     * @param TelegraphChat $chat
     * @param $hour
     * @param $minute
     * @return void
     */
    public function finish(TelegraphChat $chat, $hour, $minute): void
    {
        try {
            $chatInfo = $this->getChatInfo($chat);
            $customerSubscriptionExists = CurrencySubscriptionModel::where('chat_id', $chat->chat_id)->exists();

            if (!$customerSubscriptionExists) {
                CurrencySubscriptionModel::create([
                    'chat_id' => $chat->chat_id,
                    'name' => $chatInfo['first_name'],
                    'hour' => $hour,
                    'minute' => $minute,
                ]);
            } else {
                CurrencySubscriptionModel::where('chat_id', $chat->chat_id)->update([
                    'hour' => $hour,
                    'minute' => $minute,
                ]);
            }
            Telegraph::chat($chat->chat_id)
                ->message("✅ Готово! Теперь каждый день в *{$hour}:{$minute}* ты будешь получать свежую информацию о курсе валют в ПМР.")
                ->send();


        } catch (\Exception $e) {
            Log::error('error' . $e->getMessage() . $e->getFile() . $e->getLine());
        }
    }

    /**
     * @param TelegraphChat $chat
     * @param $hour
     * @param $minute
     * @return void
     */
    public function sendSubscriptionCurrency(TelegraphChat $chat, $hour, $minute): void
    {
        $bankData = (new Currency())->getDataFromBank();

        $message = "🎉 Отлично! Ты подписан на ежедневную рассылку курсов валют в ПМР в *{$hour}:{$minute}*.\n\n";
        $message .= "📊 А вот свежая информация на сейчас:\n\n";
        $message .= "💱 *Курсы валют:*\n";
        $message .= "💰 *Валюта* | 💵 *Покупка* | 💳 *Продажа*\n";
        $message .= "------------------------------------------\n";

        $flags = \App\Models\CurrencyModel::all()->pluck('flag', 'currency');

        foreach ($bankData as $item) {
            if (!in_array('RUP', $item)) continue;

            $fromCurrency = $item[0];
            $toCurrency = $item[1];

            $flagFrom = $flags[$fromCurrency] ?? '';
            $flagTo = $flags[$toCurrency] ?? '';

            $message .= "*{$flagFrom} {$fromCurrency} → {$flagTo} {$toCurrency}*\n\n";
            $message .= "🟢 *{$item['buy']}* | 🔴 *{$item['sell']}*\n";
            $message .= "---------------------------------\n";
        }
        Telegraph::chat($chat->chat_id)
            ->message($message)
            ->send();
    }

    /**
     * @return void
     */
    public function getSubscriptionCurrency(): void
    {
        $customers = CurrencySubscriptionModel::all();
        $currentTime = date('H:i');

        foreach ($customers as $customer) {
            if (strlen($customer['hour']) < 2) {
                $customer['hour'] = '0' . $customer['hour'];
            }
            if (strlen($customer['minute']) < 2) {
                $customer['minute'] = '0' . $customer['minute'];
            }
            $timeSubscription = $customer['hour'] . ":" . $customer['minute'];


            if ($timeSubscription == $currentTime) {
                $chat = TelegraphChat::where('chat_id', $customer['chat_id'])->first();
                $this->sendSubscriptionCurrency($chat, $customer['hour'], $customer['minute']);
            }
        }
    }

    /**
     * @param $chat
     * @return array
     */
    private function getChatInfo($chat): array
    {
        try {
            $chatInfo = TelegraphChat::where('chat_id', $chat->chat_id)->first();
            return $chatInfo->info();
        } catch (\Exception $e) {
            Log::error('error' . $e->getMessage() . $e->getFile() . $e->getLine());
            return [];
        }
    }
}
