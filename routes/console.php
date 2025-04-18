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
        ['currency' => 'USD', 'text' => '🇺🇸 USD', 'flag'=>'🇺🇸'],
        ['currency' => 'EUR', 'text' => '🇪🇺 EUR', 'flag'=>'🇪🇺'],
        ['currency' => 'MDL', 'text' => '🇲🇩 MDL', 'flag'=>'🇲🇩'],
        ['currency' => 'RUB', 'text' => '🇷🇺 RUB', 'flag'=>'🇷🇺'],
        ['currency' => 'RUP', 'text' => '⚒ RUP', 'flag'=>'🏦'],
    ]);
});


Schedule::call(function () {
    (new \App\Telegram\WeatherSubs())->getSubscriptionWeather();
    (new \App\Telegram\CurrencySubs())->getSubscriptionCurrency();
})->everyMinute();

