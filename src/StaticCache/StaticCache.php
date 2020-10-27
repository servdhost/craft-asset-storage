<?php

namespace servd\AssetStorage\StaticCache;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use Exception;
use Redis;
use servd\AssetStorage\Plugin;
use yii\base\ErrorException;
use yii\base\Event;
use craft\base\Element;
use craft\elements\Entry;
use craft\events\DeleteTemplateCachesEvent;
use craft\events\ElementEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\services\Elements;
use craft\services\TemplateCaches;
use craft\utilities\ClearCaches;

class StaticCache extends Component
{

    public function init()
    {
        $this->registerEventHandlers();
    }

    private function registerEventHandlers()
    {

        $settings = Plugin::$plugin->getSettings();

        if (
            $settings->clearCachesOnSave == 'always'
            || ($settings->clearCachesOnSave == 'control-panel'
                && Craft::$app->getRequest()->getIsCpRequest())
        ) {
            Event::on(
                Elements::class,
                Elements::EVENT_AFTER_SAVE_ELEMENT,
                function (ElementEvent $event) {
                    $element = $event->element;
                    if (
                        Element::STATUS_ENABLED == $element->getStatus()
                        || Entry::STATUS_LIVE == $element->getStatus()
                    ) {
                        $this->clearStaticCache($event);
                    }
                }
            );
        }

        Event::on(
            TemplateCaches::class,
            TemplateCaches::EVENT_AFTER_DELETE_CACHES,
            function (DeleteTemplateCachesEvent $event) {
                $this->clearStaticCache($event);
            }
        );

        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function (RegisterCacheOptionsEvent $event) {
                $event->options[] = [
                    'key' => 'servd-asset-storage',
                    'label' => Craft::t('servd-asset-storage', 'Servd Static Cache'),
                    'action' => function () {
                        $this->clearStaticCache();
                    },
                ];
            }
        );
    }

    public function clearStaticCache(Event $event = null)
    {
        //Clear the cache
        $this->clearFolderBasedCache();
        $this->clearRedisBasedCache();
    }

    private function clearRedisBasedCache()
    {
        if (!extension_loaded('redis')) {
            return;
        }

        if (
            empty(getenv('REDIS_STATIC_CACHE_DB'))
            || empty(getenv('REDIS_HOST'))
            || empty(getenv('REDIS_PORT'))
        ) {
            return;
        }

        try {
            $redisDb = intval(getenv('REDIS_STATIC_CACHE_DB'));
            $redis = new Redis();
            $redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'));
            $redis->select($redisDb);
            $redis->flushDb(true);
            $redis->close();
        } catch (Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }
    }

    private function clearFolderBasedCache()
    {
        $cachePath = '/nginxcache';

        if (!file_exists($cachePath)) {
            return;
        }

        try {
            FileHelper::clearDirectory($cachePath);
        } catch (ErrorException $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }
    }
}
