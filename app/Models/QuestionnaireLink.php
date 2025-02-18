<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\QuestionnairesLinkResponses;
class QuestionnaireLink extends Model
{
    use HasFactory;
    protected $fillable = [
        'questionnaire_id',
        'token',
        'expires_at',
        'user',
        'patient'
    ];

    protected $dates = [
        'expires_at',
    ];

    public function questionnaire()
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function questionnaireLink()
    {
        return $this->hasOne(QuestionnairesLinkResponses::class);
    }
}
