<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinderReport extends Model
{
    use HasFactory;

    protected $table = 'minder_reports';

    protected $fillable = [
        'message_id',
        'reported_by',
        'reason',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(MinderMessage::class, 'message_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'resolved_by');
    }
}
