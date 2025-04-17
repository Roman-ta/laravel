<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model for currency_list
 */
class CurrencyModel extends Model
{
    use HasFactory;

    protected $fillable = ['currency', 'text', 'flag'];

    public function getTable()
    {
        return 'currency_list';
    }
}
