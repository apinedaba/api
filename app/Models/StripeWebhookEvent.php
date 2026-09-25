<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeWebhookEvent extends Model
{
    protected $fillable = ['event_id', 'type', 'status', 'processed_at', 'last_error'];
    protected $casts = ['processed_at' => 'datetime'];
}
