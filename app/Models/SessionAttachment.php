<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionAttachment extends Model
{
    use HasFactory;

    protected $appends = [
        'display_name',
        'formatted_size',
        'icon',
    ];

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

    public function getDisplayNameAttribute(): string
    {
        $filename = trim((string) $this->filename);
        $publicId = trim((string) $this->public_id);
        $extension = strtolower((string) $this->extension);

        if ($filename !== '' && ! $this->looksLikeGeneratedCloudinaryName($filename, $publicId)) {
            return $filename;
        }

        return $extension !== '' ? 'Documento ' . strtoupper($extension) : 'Documento adjunto';
    }

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
        return $this->belongsTo(Appointment::class, 'session_id');
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

        if (! $size) {
            return null;
        }

        if ($size < 1024)
            return $size . ' B';
        if ($size < 1048576)
            return round($size / 1024, 1) . ' KB';
        return round($size / 1048576, 1) . ' MB';
    }

    private function looksLikeGeneratedCloudinaryName(string $filename, string $publicId): bool
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $publicName = pathinfo(basename($publicId), PATHINFO_FILENAME);

        if ($publicName !== '' && $name === $publicName) {
            return true;
        }

        return (bool) preg_match('/^[a-z0-9_-]{12,}$/i', $name);
    }
}
