<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Artisan::command('help', function () {
    /** @var  \DefStudio\Telegraph\Models\TelegraphBot $bot */
    $bot= \DefStudio\Telegraph\Models\TelegraphBot::find(1);
    dd($bot->registerCommands([
        'start'=> 'начальная команда',
        'help' => "инструкция к боту"
    ])->send());
});

Artisan::command('insert_currency', function () {
    \App\Models\Currency::insert([
        ['currency' => 'USD', 'text' => '🇺🇸 USD'],
        ['currency' => 'EUR', 'text' => '🇪🇺 EUR'],
        ['currency' => 'MDL', 'text' => '🇲🇩 MDL'],
        ['currency' => 'RUB', 'text' => '🇷🇺 RUB'],
        ['currency' => 'RUP', 'text' => '⚒ RUP'],
    ]);
});
