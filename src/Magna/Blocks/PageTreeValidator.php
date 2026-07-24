<?php

declare(strict_types=1);

namespace Magna\Blocks;

/**
 * Validates a decoded blocks_data payload (array of section objects) against
 * the rules from ADR-0002.  Validation is enforced on save and skipped on read
 * so that disabled-plugin blocks remain tolerated.
 */
final class PageTreeValidator
{
    /**
     * Block handles whose Blade partials render field data unescaped
     * ({!! !!}) by design — resources/views/blocks/{html,text}.blade.php.
     * Keep in sync with any future block type that does the same. Gated
     * behind a dedicated permission (Stage 7 finding) rather than being
     * available to anyone who can edit a `blocks` field at all — ordinary
     * content-edit permission (content.{type}.update) says nothing about
     * whether that editor should be trusted with raw HTML/script.
     *
     * @var list<string>
     */
    private const RAW_HTML_BLOCK_HANDLES = ['html', 'text'];

    public function __construct(private readonly BlockRegistry $registry) {}

    /**
     * Validate the raw blocks_data array.
     *
     * @param  list<mixed>  $data
     * @return list<string> Error messages (empty = valid)
     */
    public function validate(array $data): array
    {
        $errors = [];
        $seenIds = [];

        foreach ($data as $sectionIndex => $sectionRaw) {
            if (! is_array($sectionRaw)) {
                $errors[] = "Section #{$sectionIndex} must be an object.";

                continue;
            }

            $sectionId = isset($sectionRaw['id']) && is_string($sectionRaw['id']) ? $sectionRaw['id'] : '';
            if ($sectionId === '') {
                $errors[] = "Section #{$sectionIndex} is missing an 'id'.";
            } elseif (in_array($sectionId, $seenIds, true)) {
                $errors[] = "Duplicate id '{$sectionId}' in the page tree.";
            } else {
                $seenIds[] = $sectionId;
            }

            $this->validateTokenOverrides($sectionRaw['settings'] ?? [], $sectionIndex, $errors);

            $columns = $sectionRaw['columns'] ?? [];
            if (! is_array($columns)) {
                $errors[] = "Section '{$sectionId}' columns must be an array.";

                continue;
            }

            // Validate column spans sum to 12
            $spanSum = 0;
            foreach ($columns as $col) {
                if (is_array($col)) {
                    $spanVal = $col['span'] ?? 0;
                    $spanSum += is_int($spanVal) ? $spanVal : 0;
                }
            }
            if (count($columns) > 0 && $spanSum !== 12) {
                $errors[] = "Section '{$sectionId}' column spans sum to {$spanSum}, must be 12.";
            }

            foreach ($columns as $colIndex => $colRaw) {
                if (! is_array($colRaw)) {
                    continue;
                }

                $colId = isset($colRaw['id']) && is_string($colRaw['id']) ? $colRaw['id'] : '';
                if ($colId !== '') {
                    if (in_array($colId, $seenIds, true)) {
                        $errors[] = "Duplicate id '{$colId}' in the page tree.";
                    } else {
                        $seenIds[] = $colId;
                    }
                }

                $blocks = $colRaw['blocks'] ?? [];
                if (! is_array($blocks)) {
                    continue;
                }

                foreach ($blocks as $blockIndex => $blockRaw) {
                    if (! is_array($blockRaw)) {
                        continue;
                    }

                    $blockId = isset($blockRaw['id']) && is_string($blockRaw['id']) ? $blockRaw['id'] : '';
                    if ($blockId !== '') {
                        if (in_array($blockId, $seenIds, true)) {
                            $errors[] = "Duplicate id '{$blockId}' in the page tree.";
                        } else {
                            $seenIds[] = $blockId;
                        }
                    }

                    $handle = isset($blockRaw['block']) && is_string($blockRaw['block']) ? $blockRaw['block'] : '';
                    if (! $this->registry->has($handle)) {
                        $errors[] = "Block handle '{$handle}' in section '{$sectionId}', column #{$colIndex}, block #{$blockIndex} is not registered.";
                    } elseif (in_array($handle, self::RAW_HTML_BLOCK_HANDLES, true)
                        && ! (auth()->user()?->can('blocks.raw_html') ?? false)
                    ) {
                        $errors[] = "Block '{$handle}' (id: {$blockId}) renders raw HTML and requires the blocks.raw_html permission.";
                    } else {
                        // Validate block data against the block's field rules
                        $definition = $this->registry->get($handle);
                        if ($definition !== null) {
                            $blockData = is_array($blockRaw['data'] ?? null) ? $blockRaw['data'] : [];
                            $fieldErrors = $definition->validate($blockData);
                            foreach ($fieldErrors as $fieldHandle => $fieldMessages) {
                                foreach ($fieldMessages as $message) {
                                    $errors[] = "Block '{$handle}' (id: {$blockId}): {$message}";
                                }
                            }
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param  list<string>  $errors
     */
    private function validateTokenOverrides(mixed $settings, int $sectionIndex, array &$errors): void
    {
        if (! is_array($settings)) {
            return;
        }

        $overrides = $settings['tokenOverrides'] ?? null;
        if ($overrides === null) {
            return;
        }

        if (! is_array($overrides)) {
            $errors[] = "Section #{$sectionIndex} tokenOverrides must be an object.";

            return;
        }

        foreach ($overrides as $key => $value) {
            if (! is_string($key) || $key === '') {
                $errors[] = "Section #{$sectionIndex} tokenOverrides contains an empty or non-string key.";
            } elseif (str_contains($key, ';') || str_contains($key, ':')) {
                $errors[] = "Section #{$sectionIndex} tokenOverrides key '{$key}' must not contain ';' or ':'.";
            }
            if (! is_string($value) || $value === '') {
                $errors[] = "Section #{$sectionIndex} tokenOverrides key '{$key}' has an empty or non-string value.";
            } elseif (str_contains($value, ';')) {
                $errors[] = "Section #{$sectionIndex} tokenOverrides key '{$key}' value must not contain ';'.";
            }
        }
    }
}
