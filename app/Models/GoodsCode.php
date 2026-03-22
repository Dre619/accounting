<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsCode extends Model
{
    protected $fillable = ['name', 'description', 'hs_code'];
}
