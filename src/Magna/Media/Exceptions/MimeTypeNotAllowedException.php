<?php

declare(strict_types=1);

namespace Magna\Media\Exceptions;

class MimeTypeNotAllowedException extends MediaIngestException
{
    public function __construct(
        public readonly string $mimeType,
        public readonly string $filename,
    ) {
        parent::__construct(
            "Rejected \"{$filename}\": content type \"{$mimeType}\" is not on the upload allowlist."
        );
    }
}
