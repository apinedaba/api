<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\QuestionnaireLink;
use App\Models\Patient;

class QuestionnairesLinkResponses extends Model
{
    use HasFactory;

    protected $fillable = [
        'response',
        'questionnaire_link_id'
    ];

    protected $casts = [
        'response' => 'array',
    ];

    public function questionnaireLink()
    {
        return $this->hasOne(QuestionnaireLink::class);
    }
    public function patient()
    {
        return $this->hasOne(Patient::class);
    }
}
