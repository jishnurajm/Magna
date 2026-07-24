<?php

declare(strict_types=1);

namespace Magna\Media\Console;

use Illuminate\Console\Command;
use Magna\Media\ConversionPreset;
use Magna\Media\ConversionPresetRegistry;
use Magna\Media\Jobs\ProcessMediaConversionJob;
use Magna\Media\Media;

class MediaReconvertCommand extends Command
{
    protected $signature = 'magna:media:reconvert
                            {--preset= : Re-queue only this preset (omit for all)}
                            {--id=     : Re-queue only the media item with this ULID}';

    protected $description = 'Re-queue conversion jobs for existing media items.';

    public function handle(ConversionPresetRegistry $presets): int
    {
        $presetOption = $this->option('preset');
        $idOption = $this->option('id');

        $presetNames = is_string($presetOption) && $presetOption !== ''
            ? [$presetOption]
            : array_map(fn (ConversionPreset $p): string => $p->name, $presets->all());

        if ($presetNames === []) {
            $this->warn('No presets registered — nothing to reconvert.');

            return self::SUCCESS;
        }

        $query = Media::query()->whereIn('mime_type', [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif',
        ]);

        if (is_string($idOption) && $idOption !== '') {
            $query->where('id', $idOption);
        }

        $dispatched = 0;

        foreach ($query->lazyById() as $media) {
            foreach ($presetNames as $presetName) {
                if ($presets->get($presetName) !== null) {
                    ProcessMediaConversionJob::dispatch($media->id, $presetName);
                    $dispatched++;
                }
            }
        }

        $this->info("Queued {$dispatched} conversion job(s).");

        return self::SUCCESS;
    }
}
