<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        "street",
        "zipcode",
        "city",
        "neighborhood",
        "country",
        "state",
        "user_id"
    ];
}
