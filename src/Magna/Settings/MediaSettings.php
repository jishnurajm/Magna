<?php

declare(strict_types=1);

namespace Magna\Settings;

class MediaSettings extends Settings
{
    /** Maximum upload size in bytes for raster images (JPEG, PNG, GIF, WebP, AVIF). */
    public int $max_image_upload_bytes = 20_971_520; // 20 MB

    /** Maximum upload size in bytes for SVG files. */
    public int $max_svg_upload_bytes = 2_097_152; // 2 MB

    /** Maximum upload size in bytes for documents (PDF etc.). */
    public int $max_document_upload_bytes = 52_428_800; // 50 MB

    /** JPEG/WebP encoding quality used during ingest re-encoding (1–100). */
    public int $default_image_quality = 90;

    /** Generate WebP conversion presets for uploaded raster images. */
    public bool $webp_enabled = true;

    /** Generate AVIF conversion presets for uploaded raster images (best-effort; skipped if GD lacks libavif). */
    public bool $avif_enabled = false;
}
