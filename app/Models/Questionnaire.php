<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Questionnaire extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'title',
        'description',
        'structure',
        'user'
    ];

    protected $casts = [
        'structure' => 'array',
    ];

    public function links()
    {
        return $this->hasMany(QuestionnaireLink::class);
    }
}
