<?php

namespace App\Telegram;

use App\Models\CurrencyModel;
use App\Models\CurrencySubscriptionModel;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Log;

/**
 * controller subscription for currency
 */
class CurrencySubs extends WebhookHandler
{

    public function start(TelegraphChat $chat): void
    {
        try {
            $chatInfo = $chat->info();

            $keyboard = Helper::getHoursKeyboard('currency_subs');

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
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

    }

    /**
     * @param TelegraphChat $chat
     * @param $hour
     * @return void
     */
    public function setMinutes(TelegraphChat $chat, $hour): void
    {
        $keyboard = Helper::getMinutesKeyboard('currency_subs', $hour);
        $keyboard->button('🔙 back')->action('currency_subs')->param('step', 1)->width(1);
        Telegraph::chat($chat->chat_id)
            ->message('Отлично, теперь выбери минуты')
            ->keyboard($keyboard)
            ->send();

    }

    /**
     * @param TelegraphChat $chat
     * @param $hour
     * @param $minute
     * @return void
     */
    public function timeConfirm(TelegraphChat $chat, $hour, $minute): void
    {
        $minute = Helper::addZeroForTime($minute);

        $keyboard = Keyboard::make();
        $keyboard->button('🔙 back')->action('currency_subs')->param('step', 2)->param('hour', $hour)->width(0.5);
        $keyboard->button('Yes')->action('currency_subs')->param('step', 4)->width(0.5)->param('hour', $hour)->param('minute', $minute);

        Telegraph::chat($chat->chat_id)
            ->message("Отлично, ты выбрал *{$hour}:{$minute}* все верно?")
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
        $hour = Helper::addZeroForTime($hour);
        $minute = Helper::addZeroForTime($minute);

        try {
            $chatInfo = $chat->info();
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
     * @return void
     */
    public function getSubscriptionCurrency(): void
    {
        $customers = CurrencySubscriptionModel::all();
        $currentTime = date('H:i');

        foreach ($customers as $customer) {
            $minute = Helper::addZeroForTime($customer['minute']);
            $hour = Helper::addZeroForTime($customer['hour']);

            $timeSubscription = $hour . ":" . $minute;

            if ($timeSubscription == $currentTime) {
                $chat = TelegraphChat::where('chat_id', $customer['chat_id'])->first();
                $this->sendMessageFromCurrency($chat, $hour, $minute);
            }
        }
    }

    /**
     * @param TelegraphChat $chat
     * @param $hour
     * @param $minute
     * @return void
     */
    public function sendMessageFromCurrency(TelegraphChat $chat, $hour, $minute): void
    {
        $bankData = (new Currency())->getActualCurrencyFromBank();

        $message = "🎉 Отлично! Ты подписан на ежедневную рассылку курсов валют в ПМР в *{$hour}:{$minute}*.\n\n";
        $message .= "📊 А вот свежая информация на сейчас:\n\n";
        $message .= "💱 *Курсы валют:*\n";
        $message .= "💰 *Валюта* | 💵 *Покупка* | 💳 *Продажа*\n";
        $message .= "------------------------------------------\n";

        $flags = CurrencyModel::all()->pluck('flag', 'currency');

        foreach ($bankData as $item) {
            if (!in_array('RUP', $item)) continue;

            $fromCurrency = $item['from'];
            $toCurrency = $item['to'];

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
}
