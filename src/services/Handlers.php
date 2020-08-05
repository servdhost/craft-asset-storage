<?php

namespace servd\AssetStorage\services;

use Craft;
use craft\base\Component;
use craft\events\GetAssetThumbUrlEvent;
use craft\events\GetAssetUrlEvent;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\FileHelper;
use craft\helpers\Image as ImageHelper;
use craft\models\AssetTransform;
use Exception;
use Redis;
use servd\AssetStorage\Plugin;
use yii\base\ErrorException;
use yii\base\Event;

class Handlers extends Component
{
    public function getAssetUrlEvent(GetAssetUrlEvent $event)
    {
        $asset = $event->asset;
        $volume = $asset->getVolume();
        $transform = $event->transform;

        if (!ImageHelper::canManipulateAsImage(pathinfo($asset->filename, PATHINFO_EXTENSION))) {
            return AssetsHelper::generateUrl($volume, $asset);
        }

        //If the input type is gif respect the no transform flag
        if (Plugin::$plugin->optimise->inputIsGif($asset) && !(Craft::$app->getConfig()->getGeneral()->transformGifs ?? false)) {
            return AssetsHelper::generateUrl($volume, $asset);
        }

        if (empty($transform)) {
            $transform = new AssetTransform([
                //'height' => $asset->height,
                'width' => $asset->width,
                'interlace' => 'line',
            ]);
        }

        if (\is_array($transform)) {
            $transform = new AssetTransform($transform);
        }

        if (\is_string($transform)) {
            $assetTransforms = Craft::$app->getAssetTransforms();
            $transform = $assetTransforms->getTransformByHandle($transform);
        }

        //If the output type is svg, no transform is occuring, just let Craft handle it
        //This should return a link to the CDN path without optimisation
        if (Plugin::$plugin->optimise->outputWillBeSVG($asset, $transform)) {
            return null;
        }

        return Plugin::$plugin->optimise->transformUrl($asset, $transform);
    }

    public function getAssetThumbUrlEvent(GetAssetThumbUrlEvent $event)
    {
        $asset = $event->asset;
        $volume = $asset->getVolume();

        if (!ImageHelper::canManipulateAsImage(pathinfo($asset->filename, PATHINFO_EXTENSION))) {
            return AssetsHelper::generateUrl($volume, $asset);
        }

        //If the input type is gif respect the no transform flag
        if (Plugin::$plugin->optimise->inputIsGif($asset) && !(Craft::$app->getConfig()->getGeneral()->transformGifs ?? false)) {
            return AssetsHelper::generateUrl($volume, $asset);
        }

        $transform = new AssetTransform([
            'height' => $event->height,
            'width' => $event->width,
            'interlace' => 'line',
        ]);

        //If the output type is svg, no transform is occuring, just let Craft handle it
        //This should return a link to the CDN path without optimisation
        if (Plugin::$plugin->optimise->outputWillBeSVG($asset, $transform)) {
            return null;
        }

        return Plugin::$plugin->optimise->transformUrl($asset, $transform);
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
