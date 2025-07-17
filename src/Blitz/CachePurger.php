<?php

namespace servd\AssetStorage\Blitz;

use putyourlightson\blitz\drivers\purgers\BaseCachePurger;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;
use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;
use servd\AssetStorage\Plugin;
use servd\AssetStorage\StaticCache\Ledge;
use yii\base\Event;

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

    public function purgeUris(array $siteUris)
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_PURGE_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        if(!$this->isRunningInServd() || !$this->isStaticCachingEnabled()) {
            return;
        }

        Ledge::purgeUrls(SiteUriHelper::getUrlsFromSiteUris($siteUris));

        if ($this->hasEventHandlers(self::EVENT_AFTER_PURGE_CACHE)) {
            $this->trigger(self::EVENT_AFTER_PURGE_CACHE, $event);
        }
    }

    public function purgeAll()
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_PURGE_ALL_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        if(!$this->isRunningInServd() || !$this->isStaticCachingEnabled()) {
            return;
        }

        Plugin::$plugin->staticCache->clearStaticCache();

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
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('servd-blitz/settings', [
            'purger' => $this,
        ]);
    }

    // Private Methods
    // =========================================================================

    private function isRunningInServd()
    {
        return extension_loaded('redis') && !empty(getenv('REDIS_STATIC_CACHE_DB'));
    }

    private function isStaticCachingEnabled()
    {
        return getenv('SERVD_CACHE_ENABLED') === 'true';
    }
}