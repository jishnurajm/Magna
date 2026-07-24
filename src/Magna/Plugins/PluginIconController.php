<?php

declare(strict_types=1);

namespace Magna\Plugins;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves an installed plugin's icon (declared via magna.json's optional
 * "icon" field — same convention as a VS Code extension's package.json
 * "icon"). ManifestValidator already rejects traversal/absolute-path/URL
 * values at manifest-load time; this re-validates via realpath() at serve
 * time too, since resolving an arbitrary path off disk is worth checking
 * twice rather than trusting a value that was merely valid when the plugin
 * was enabled.
 */
class PluginIconController
{
    public function show(string $vendor, string $package): BinaryFileResponse|Response
    {
        $record = PluginRecord::query()->where('name', $vendor.'/'.$package)->first();
        if ($record === null) {
            abort(404);
        }

        $iconRelative = is_string($record->manifest['icon'] ?? null) ? $record->manifest['icon'] : null;
        if ($iconRelative === null) {
            abort(404);
        }

        $basePath = realpath(rtrim((string) $record->base_path, '/\\'));
        $resolved = $basePath !== false ? realpath($basePath.DIRECTORY_SEPARATOR.$iconRelative) : false;

        if ($basePath === false || $resolved === false || ! str_starts_with($resolved, $basePath)) {
            abort(404);
        }

        $mime = match (strtolower((string) pathinfo($resolved, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => null,
        };

        if ($mime === null) {
            abort(404);
        }

        return response()->file($resolved, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
