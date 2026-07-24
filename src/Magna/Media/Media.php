<?php

declare(strict_types=1);

namespace Magna\Media;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Magna\Users\User;

/**
 * @property string $id
 * @property string|null $folder_id
 * @property string|null $uploaded_by
 * @property string $disk
 * @property string $path
 * @property string $filename
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt
 * @property string|null $title
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    use HasUlids;
    use SoftDeletes;

    protected $table = 'magna_media';

    protected $fillable = [
        'folder_id', 'uploaded_by', 'disk', 'path', 'filename', 'original_filename',
        'mime_type', 'size', 'width', 'height', 'alt', 'title', 'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /** @return BelongsTo<MediaFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasMany<MediaConversion, $this> */
    public function conversions(): HasMany
    {
        return $this->hasMany(MediaConversion::class, 'media_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/') && $this->mime_type !== 'image/svg+xml';
    }

    protected static function newFactory(): MediaFactory
    {
        return MediaFactory::new();
    }
}
