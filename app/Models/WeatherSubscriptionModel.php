<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model for weather_subscription
 */
class WeatherSubscriptionModel extends Model
{

    protected $table = 'weather_subscriptions';
    protected $fillable = [
        'chat_id',
        'name',
        'city',
        'hour',
        'minute',
    ];
}
