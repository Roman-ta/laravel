<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('createCommands', function () {
    /** @var  \DefStudio\Telegraph\Models\TelegraphBot $bot */
    $bot = \DefStudio\Telegraph\Models\TelegraphBot::find(1);
    dd($bot->registerCommands([
        'start' => 'начальная команда',
        'weather' => 'Погода',
        'currency' => 'Курс валют',
        'weather_subs' => 'Подписка на ежедневный прогноз погоды',
        'currency_subs' => 'Подписка на ежедневный курс валют',
        'help' => "инструкция к боту",
    ])->send());
});

Artisan::command('insert_currency', function () {
    \App\Models\CurrencyModel::insert([
        ['currency' => 'USD', 'text' => '🇺🇸 USD'],
        ['currency' => 'EUR', 'text' => '🇪🇺 EUR'],
        ['currency' => 'MDL', 'text' => '🇲🇩 MDL'],
        ['currency' => 'RUB', 'text' => '🇷🇺 RUB'],
        ['currency' => 'RUP', 'text' => '⚒ RUP'],
    ]);
});

Artisan::command('update_currency_list', function () {
    $data = [['currency' => 'USD', 'text' => '🇺🇸 USD'],
        ['currency' => 'EUR', 'text' => '🇪🇺 EUR'],
        ['currency' => 'MDL', 'text' => '🇲🇩 MDL'],
        ['currency' => 'RUB', 'text' => '🇷🇺 RUB'],
        ['currency' => 'RUP', 'text' => '⚒ RUP']];
    $flags = ['USD' => '🇺🇸',
        'EUR' => '🇪🇺',
        'MDL' => '🇲🇩',
        'UAH' => '🇺🇦',
        'RUB' => '🇷🇺',
        'RUP' => '🏦'];
    foreach ($data as $item) {
        \App\Models\CurrencyModel::where('currency', $item['currency'])->update([
            'text' => $item['currency'],
            'flag' => $flags[$item['currency']]
        ]);
    }
});

Schedule::call(function () {
    (new \App\Telegram\WeatherSubs())->getSubscriptionWeather();
})->everyMinute();

