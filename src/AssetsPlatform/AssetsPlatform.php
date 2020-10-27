<?php

namespace servd\AssetStorage\AssetsPlatform;

use Aws\Handler\GuzzleV6\GuzzleHandler;
use Craft;
use craft\base\Component;
use craft\events\GetAssetThumbUrlEvent;
use craft\events\GetAssetUrlEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\Image as ImageHelper;
use craft\models\AssetTransform;
use craft\services\Assets;
use craft\services\Volumes;
use Exception;
use servd\AssetStorage\Plugin;
use servd\AssetStorage\Volume as AssetStorageVolume;
use yii\base\Event;

class AssetsPlatform extends Component
{

    const S3_BUCKET = 'cdn-assets-servd-host';
    const S3_REGION = 'eu-west-1';
    const CACHE_KEY_PREFIX = 'servdassets.';
    const CACHE_DURATION_SECONDS = 3600 * 24;
    const DEFAULT_SECURITY_TOKEN_URL = 'https://app.servd.host/create-assets-token';

    public $imageTransforms;

    public function init()
    {
        $this->imageTransforms = new ImageTransforms();
        $this->registerEventHandlers();
    }

    public function getStorageBaseDirectory()
    {
        $settings = Plugin::$plugin->getSettings();
        $fullPath = $settings->getProjectSlug() . '/';
        return $fullPath;
    }

    public function getS3ConfigArray()
    {

        $settings = Plugin::$plugin->getSettings();
        $projectSlug = $settings->getProjectSlug();
        $securityKey = $settings->getSecurityKey();

        $config = [
            'region' => static::S3_REGION,
            'version' => 'latest',
        ];

        $credentials = [];
        $tokenKey = static::CACHE_KEY_PREFIX . md5($projectSlug);
        if (Craft::$app->cache->exists($tokenKey)) {
            $credentials = Craft::$app->cache->get($tokenKey);
        } else {
            //Grab tokens from token service
            $credentials = $this->getSecurityToken($projectSlug, $securityKey);
            Craft::$app->cache->set($tokenKey, $credentials, static::CACHE_DURATION_SECONDS);
        }

        $config['credentials'] = $credentials;
        $config['endpoint'] = 'https://s3.eu-central-003.backblazeb2.com';
        $client = Craft::createGuzzleClient();
        $config['http_handler'] = new GuzzleHandler($client);

        return $config;
    }

    private function getSecurityToken($projectSlug, $securityKey)
    {
        $securityTokenUrl = getenv('SECURITY_TOKEN_URL');
        if (empty($securityTokenUrl)) {
            $securityTokenUrl = static::DEFAULT_SECURITY_TOKEN_URL;
        }

        $client = Craft::createGuzzleClient();
        $response = $client->post($securityTokenUrl, [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'form_params' => [
                'slug' => $projectSlug,
                'key' => $securityKey,
            ],
        ]);
        $res = json_decode($response->getBody(), true);

        return $res['credentials'];
    }

    public function registerEventHandlers()
    {
        Event::on(Volumes::class, Volumes::EVENT_REGISTER_VOLUME_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = AssetStorageVolume::class;
        });

        Event::on(
            Assets::class,
            Assets::EVENT_GET_ASSET_URL,
            function (GetAssetUrlEvent $event) {
                $asset = $event->asset;
                $volume = $asset->getVolume();
                if ($volume instanceof AssetStorageVolume) {
                    $asset = $event->asset;
                    $transform = $event->transform;
                    $event->url = $this->handleAssetTransform($asset, $transform);
                }
            }
        );

        Event::on(
            Assets::class,
            Assets::EVENT_GET_ASSET_THUMB_URL,
            function (GetAssetThumbUrlEvent $event) {
                $asset = $event->asset;
                $volume = $asset->getVolume();
                if ($volume instanceof AssetStorageVolume) {
                    $asset = $event->asset;
                    $width = $event->width;
                    $height = $event->height;

                    $transform = new AssetTransform([
                        'height' => $height,
                        'width' => $width,
                        'interlace' => 'line',
                    ]);

                    $event->url = $this->handleAssetTransform($asset, $transform);
                }
            }
        );
    }

    public function handleAssetTransform($asset, $transform)
    {
        $volume = $asset->getVolume();

        if (!ImageHelper::canManipulateAsImage(pathinfo($asset->filename, PATHINFO_EXTENSION))) {
            return AssetsHelper::generateUrl($volume, $asset);
        }

        //If the input type is gif respect the no transform flag
        if ($this->imageTransforms->inputIsGif($asset) && !(Craft::$app->getConfig()->getGeneral()->transformGifs ?? false)) {
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
        if ($this->imageTransforms->outputWillBeSVG($asset, $transform)) {
            return null;
        }

        return $this->imageTransforms->transformUrl($asset, $transform);
    }
}
