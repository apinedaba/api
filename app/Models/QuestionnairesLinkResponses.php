<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\QuestionnaireLink;

class QuestionnairesLinkResponses extends Model
{
    use HasFactory;

    protected $fillable = [
        'response',
        'questionnaire_link_id'
    ];

    protected $casts = [
        'response' => 'json',
    ];

    public function questionnaireLink()
    {
        return $this->hasOne(QuestionnaireLink::class);
    }
}
