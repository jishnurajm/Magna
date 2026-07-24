<?php

declare(strict_types=1);

namespace Magna\Media\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Magna\Media\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a media file from storage, enforcing safe delivery headers.
 *
 * SVG files are always served as attachments (Content-Disposition: attachment)
 * regardless of web server configuration. This prevents stored XSS if a
 * sanitizer bypass ever reaches storage — the browser downloads the file
 * instead of rendering it inline in the admin's security origin.
 *
 * Used by two routes:
 *   magna.media.serve        — signed, expiring (private disks)
 *   magna.media.serve.public — unsigned, permanent (public disk SVGs only)
 */
class MediaServeController extends Controller
{
    public function __invoke(Request $request, Media $media): StreamedResponse
    {
        $disk = Storage::disk($media->disk);

        if ($media->mime_type === 'image/svg+xml') {
            // Force download: browser must not render SVGs inline from our origin.
            $filename = $media->original_filename ?? basename($media->path);

            return $disk->download($media->path, $filename, [
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return $disk->response($media->path);
    }
}
