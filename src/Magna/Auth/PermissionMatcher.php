<?php

declare(strict_types=1);

namespace Magna\Auth;

/**
 * Matches permission grants (which may contain wildcards) against
 * concrete permission keys.
 *
 * Wildcard semantics:
 * - "*" as the final segment matches one or more remaining segments,
 *   so "blog.*" matches "blog.posts.create" and "blog.settings".
 * - "*" in the middle matches exactly one segment, so "content.*.view"
 *   matches "content.article.view" but not "content.article.publish".
 * - A grant of just "*" matches every key.
 *
 * Registered permission keys never contain wildcards — only grants do.
 */
final class PermissionMatcher
{
    public static function matches(string $grant, string $key): bool
    {
        if ($grant === $key) {
            return true;
        }

        if ($grant === '*') {
            return true;
        }

        $grantSegments = explode('.', $grant);
        $keySegments = explode('.', $key);

        foreach ($grantSegments as $index => $segment) {
            $isLastGrantSegment = $index === count($grantSegments) - 1;

            if ($segment === '*' && $isLastGrantSegment) {
                // Trailing wildcard: at least one key segment must remain.
                return count($keySegments) > $index;
            }

            if (! array_key_exists($index, $keySegments)) {
                return false;
            }

            if ($segment !== '*' && $segment !== $keySegments[$index]) {
                return false;
            }
        }

        // Every grant segment consumed exactly one key segment; the key
        // must not have segments left over.
        return count($keySegments) === count($grantSegments);
    }

    /**
     * @param  list<string>  $grants
     */
    public static function anyMatches(array $grants, string $key): bool
    {
        foreach ($grants as $grant) {
            if (self::matches($grant, $key)) {
                return true;
            }
        }

        return false;
    }
}
