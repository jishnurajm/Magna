<?php

declare(strict_types=1);

namespace Magna\Media;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $media_id
 * @property string $preset
 * @property string $format
 * @property string $path
 * @property int $width
 * @property int $height
 * @property int $size
 * @property Carbon|null $created_at
 */
class MediaConversion extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'magna_media_conversions';

    protected $fillable = ['media_id', 'preset', 'format', 'path', 'width', 'height', 'size'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
