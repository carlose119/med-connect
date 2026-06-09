<?php

namespace App\Models;

use Database\Factories\MedicalAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalAttachment extends Model
{
    /** @use HasFactory<MedicalAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'medical_note_id',
        'file_path',
        'file_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function medicalNote(): BelongsTo
    {
        return $this->belongsTo(MedicalNote::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
