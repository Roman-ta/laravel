<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model for weather_subscription
 */
class WeatherSubscriptionModel extends Model
{

    protected  $fillable = [
        'chatId',
        'name',
        'city',
        'hour',
        'minute',
    ];

    /**
     * @return string
     */
    public function getTable(): string
    {
        return 'weather_subscription';
    }
}
