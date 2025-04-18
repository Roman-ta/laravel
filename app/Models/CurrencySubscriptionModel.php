<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * model for currency_subscription
 */
class CurrencySubscriptionModel extends Model
{
    protected $fillable = [
        'chat_id',
        'name',
        'hour',
        'minute',
    ];
    protected $table = 'currency_subscription';

}
