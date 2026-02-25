<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataCleaningHistory extends Model
{
    protected $table = 'data_cleaning_histories';

    protected $fillable = [
        'commodity',
        'date',
        'issue',
        'old_value',
        'new_value',
        'method',
        'created_at'
    ];

    public $timestamps = false;
}
