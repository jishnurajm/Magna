<?php

declare(strict_types=1);

namespace Magna\Updater;

/** Outcome of auto-disabling plugins made incompatible by a forced core update. */
final readonly class PluginDisableResult
{
    /**
     * @param  list<string>  $disabled
     * @param  list<string>  $failed
     */
    public function __construct(
        public array $disabled,
        public array $failed,
    ) {}
}
