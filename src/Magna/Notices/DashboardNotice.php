<?php

declare(strict_types=1);

namespace Magna\Notices;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Local cache of an owner-authored dashboard banner, synced on every
 * check-in (Magna\Updater\UpdateCheckClient::checkIn()). See
 * docs/dashboard-notices-plan.md.
 *
 * Only ONE notice is ever shown at a time (DashboardNoticesBanner picks the
 * single highest-priority undismissed one) — never stack banners.
 *
 * @property int $remote_id
 * @property string $category 'announcement' | 'system_upgrade' | 'welcome'
 * @property string|null $category_description
 * @property string|null $image_url
 * @property string $title
 * @property string $description
 * @property Carbon|null $dismissed_at
 * @property string|null $link_github
 * @property string|null $link_docs
 * @property string|null $link_blog
 * @property string|null $link_community
 * @property string|null $link_themes
 * @property string|null $link_plugins
 */
class DashboardNotice extends Model
{
    /** Display priority, highest first — DashboardNoticesBanner shows only the top match. */
    public const PRIORITY = ['system_upgrade', 'welcome', 'announcement'];

    protected $table = 'dashboard_notices';

    protected $fillable = [
        'remote_id', 'category', 'category_description', 'image_url', 'title', 'description', 'dismissed_at',
        'link_github', 'link_docs', 'link_blog', 'link_community', 'link_themes', 'link_plugins',
    ];

    protected $casts = [
        'dismissed_at' => 'datetime',
    ];

    public function isSystemUpgrade(): bool
    {
        return $this->category === 'system_upgrade';
    }

    public function isWelcome(): bool
    {
        return $this->category === 'welcome';
    }

    /** @return list<array{label: string, url: string, icon: string}> only the buttons whose URL was actually configured */
    public function welcomeLinks(): array
    {
        $links = [
            ['label' => 'GitHub', 'url' => $this->link_github, 'icon' => 'github'],
            ['label' => 'Docs', 'url' => $this->link_docs, 'icon' => 'docs'],
            ['label' => 'Read Blog', 'url' => $this->link_blog, 'icon' => 'blog'],
            ['label' => 'Community', 'url' => $this->link_community, 'icon' => 'community'],
            ['label' => 'Themes', 'url' => $this->link_themes, 'icon' => 'themes'],
            ['label' => 'Plugins', 'url' => $this->link_plugins, 'icon' => 'plugins'],
        ];

        return array_values(array_filter($links, fn (array $l): bool => filled($l['url'])));
    }

    /** @param  Builder<DashboardNotice>  $query */
    public function scopeUndismissed(Builder $query): void
    {
        $query->whereNull('dismissed_at');
    }

    /**
     * The single notice to show, or null — highest priority first, never more
     * than one at once ("otherwise it will make a mess").
     */
    public static function toShow(): ?self
    {
        $all = static::query()->undismissed()->get()->keyBy('category');

        foreach (self::PRIORITY as $category) {
            if ($all->has($category)) {
                return $all->get($category);
            }
        }

        return null;
    }
}
