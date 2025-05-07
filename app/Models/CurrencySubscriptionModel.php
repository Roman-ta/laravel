<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * model for currency_subscription
 */
class CurrencySubscriptionModel extends Model
{
    protected $table = 'currency_subscription';
    protected $fillable = [
        'chat_id',
        'name',
        'hour',
        'minute',
    ];

}
