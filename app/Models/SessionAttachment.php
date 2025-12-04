<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'public_id',
        'url',
        'filename',
        'extension',
        'size',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function getSizeHumanAttribute()
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . $units[$i];
    }

    // Relación: adjunto pertenece a sesión
    public function session()
    {
        return $this->belongsTo(Sesion::class, 'session_id');
    }

    // Tipo de archivo detectado automáticamente
    public function getIconAttribute()
    {
        $ext = strtolower($this->extension);

        return match ($ext) {
            'pdf' => 'pdf',
            'doc', 'docx' => 'doc',
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'img',
            'zip', 'rar', '7z' => 'zip',
            default => 'file'
        };
    }

    public function getFormattedSizeAttribute()
    {
        $size = $this->size;

        if ($size < 1024)
            return $size . ' B';
        if ($size < 1048576)
            return round($size / 1024, 1) . ' KB';
        return round($size / 1048576, 1) . ' MB';
    }
}
