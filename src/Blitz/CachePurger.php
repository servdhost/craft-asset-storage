<?php

namespace servd\AssetStorage\Blitz;

use putyourlightson\blitz\drivers\purgers\BaseCachePurger;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\SiteUriHelper;
use Craft;
use servd\AssetStorage\StaticCache\Ledge;
use craft\helpers\Queue;
use servd\AssetStorage\StaticCache\Jobs\PurgeUrlsJob;
use servd\AssetStorage\StaticCache\Jobs\PurgeEdgeCachesForEnvironmentJob;
use servd\AssetStorage\StaticCache\Jobs\PurgeEdgeCachesForUrlsJob;
use servd\AssetStorage\StaticCache\Jobs\PurgeEnvironmentJob;
use servd\AssetStorage\StaticCache\StaticCache;

class CachePurger extends BaseCachePurger
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Servd Static Cache Purger';
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function purgeUrisWithProgress(array $siteUris, callable $setProgressHandler = null): void
    {
        $count = 0;
        $total = count($siteUris);
        $label = 'Purging {total} pages.';

        if (is_callable($setProgressHandler)) {
            $progressLabel = Craft::t('blitz', $label, ['total' => $total]);
            call_user_func($setProgressHandler, $count, $total, $progressLabel);
        }

        $urls = SiteUriHelper::getUrlsFromSiteUris($siteUris);

        if ($this->isOriginCachingEnabled()) {
            Queue::push(new PurgeUrlsJob(['urls' => $urls]), StaticCache::purgePriority());
        }

        if ($this->isEdgeCachingEnabled()) {
            Queue::push(new PurgeEdgeCachesForUrlsJob(['urls' => $urls]), StaticCache::purgePriority());
        }

        $count = $total;

        if (is_callable($setProgressHandler)) {
            $progressLabel = Craft::t('blitz', $label, ['total' => $total]);
            call_user_func($setProgressHandler, $count, $total, $progressLabel);
        }
    }

    /**
     * @inheritdoc
     */
    public function purgeAll(callable $setProgressHandler = null, bool $queue = true): void
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_PURGE_ALL_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        if (!$this->isOriginCachingEnabled() && !$this->isEdgeCachingEnabled()) {
            return;
        }

        if ($this->isOriginCachingEnabled()) {
            Queue::push(new PurgeEnvironmentJob(), StaticCache::purgePriority());
        }

        if ($this->isEdgeCachingEnabled()) {
            Queue::push(new PurgeEdgeCachesForEnvironmentJob(), StaticCache::purgePriority());
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_PURGE_ALL_CACHE)) {
            $this->trigger(self::EVENT_AFTER_PURGE_ALL_CACHE, $event);
        }
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        return true; //TODO
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('servd-blitz/settings', [
            'purger' => $this,
        ]);
    }

    // Private Methods
    // =========================================================================

    private function isOriginCachingEnabled(): bool
    {
        return extension_loaded('redis')
            && !empty(getenv('REDIS_STATIC_CACHE_DB'))
            && getenv('SERVD_CACHE_ENABLED') === 'true';
    }

    private function isEdgeCachingEnabled(): bool
    {
        return getenv('SERVD_EDGE_CACHING') === 'true';
    }
}