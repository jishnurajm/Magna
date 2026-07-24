<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Magna\Media\Media;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $id = (string) Str::ulid();

        return [
            'id' => $id,
            'folder_id' => null,
            'disk' => 'public',
            'path' => 'media/2026/07/'.$id.'.jpg',
            'filename' => $id.'.jpg',
            'original_filename' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 5_000_000),
            'width' => fake()->numberBetween(100, 4000),
            'height' => fake()->numberBetween(100, 3000),
            'alt' => null,
            'title' => null,
            'metadata' => null,
        ];
    }
}
