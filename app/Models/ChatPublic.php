<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatPublic extends Model
{
    use HasFactory;

    protected $fillable = [
        'psicologo_id',
        'paciente_id',
        'messages',
    ];
}
