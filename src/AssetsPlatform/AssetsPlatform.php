<?php

namespace servd\AssetStorage\AssetsPlatform;

use Aws\Handler\GuzzleV6\GuzzleHandler;
use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\events\GetAssetThumbUrlEvent;
use craft\events\GetAssetUrlEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\VolumeEvent;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\Image as ImageHelper;
use craft\models\AssetTransform;
use craft\services\Assets;
use craft\services\Volumes;
use craft\web\UrlManager;
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
        $this->hookCPSidebarTemplate();
    }

    public function getStorageBaseDirectory()
    {
        $settings = Plugin::$plugin->getSettings();
        $fullPath = $settings->getProjectSlug() . '/';
        return $fullPath;
    }

    public function getS3ConfigArray($forceSlug = null, $forceKey = null)
    {

        $settings = Plugin::$plugin->getSettings();
        $projectSlug = $forceSlug ?? $settings->getProjectSlug();
        $securityKey = $forceKey ?? $settings->getSecurityKey();

        $config = [
            'region' => static::S3_REGION,
            'version' => 'latest',
            'http'    => [
                'connect_timeout' => 3,
                'timeout' => 30,
            ]
        ];

        $credentials = [];
        $tokenKey = static::CACHE_KEY_PREFIX . md5($projectSlug);
        $usageKey = static::CACHE_KEY_PREFIX . 'usage.' . md5($projectSlug);
        if (Craft::$app->cache->exists($tokenKey)) {
            $credentials = Craft::$app->cache->get($tokenKey);
        } else {
            //Grab tokens from token service
            $credentialsResponse = $this->getSecurityToken($projectSlug, $securityKey);
            $credentials = $credentialsResponse['credentials'];
            $usage = $credentialsResponse['usage'] ?? 0;
            Craft::$app->cache->set($tokenKey, $credentials, static::CACHE_DURATION_SECONDS);
            Craft::$app->cache->set($usageKey, $usage, static::CACHE_DURATION_SECONDS);
        }

        $config['credentials'] = $credentials;
        $config['endpoint'] = 'https://s3.eu-central-003.backblazeb2.com';
        $config['use_path_style_endpoint'] = true;
        $config['dual_stack'] = false;
        $config['accelerate'] = false;
        $client = Craft::createGuzzleClient();
        $config['http_handler'] = new GuzzleHandler($client);

        return $config;
    }

    public function getCurrentUsagePercent()
    {
        $settings = Plugin::$plugin->getSettings();
        $projectSlug = $settings->getProjectSlug();
        $usageKey = static::CACHE_KEY_PREFIX . 'usage.' . md5($projectSlug);
        return Craft::$app->cache->get($usageKey) ?? 0;
    }

    private function getSecurityToken($projectSlug, $securityKey)
    {
        $securityTokenUrl = getenv('SECURITY_TOKEN_URL');
        if (empty($securityTokenUrl)) {
            $securityTokenUrl = static::DEFAULT_SECURITY_TOKEN_URL;
        }

        try {
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
        } catch (\Exception $e) {
            throw new Exception("Failed to obtain an Assets Platform security token: " . $e->getMessage());
        }

        if (!isset($res['status'])) {
            throw new Exception("Failed to obtain an Assets Platform security token: Invalid response from server.");
        }

        if ($res['status'] != 'success') {
            $msg = $res['message'] ?? '';
            if (isset($res['errors'])) {
                $msgs = array_map(function ($el) {
                    return is_array($el) ? implode(' ', $el) : $el;
                }, $res['errors']);
                $msg .= implode(' ', $msgs);
            }
            $msg = empty($msg) ? 'An unknown error occured' : $msg;

            throw new Exception("Failed to obtain an Assets Platform security token: " . $msg);
        }

        return $res;
    }

    public function registerEventHandlers()
    {
        Event::on(Volumes::class, Volumes::EVENT_REGISTER_VOLUME_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = AssetStorageVolume::class;
        });

        // Force Servd asset volumes to use the correct public root URL
        Event::on(Volumes::class, Volumes::EVENT_BEFORE_SAVE_VOLUME, function (VolumeEvent $event) {
            $volume = $event->volume;
            if ($volume instanceof AssetStorageVolume) {
                $volume->hasUrls = true;
                $volume->url = 'https://cdn2.assets-servd.host/';
            }
        });

        Event::on(
            Assets::class,
            Assets::EVENT_GET_ASSET_URL,
            function (GetAssetUrlEvent $event) {

                // If another plugin set the url, we'll just use that.
                if ($event->url !== null) {
                    return;
                }

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

                // If another plugin set the url, we'll just use that.
                if ($event->url !== null) {
                    return;
                }

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

    public function getFileUrl(Asset $asset)
    {

        $settings = Plugin::$plugin->getSettings();
        $volume = $asset->getVolume();

        //If a custom pattern is set, use that
        $customPattern = Craft::parseEnv($volume->cdnUrlPattern);
        if (!empty($customPattern)) {
            $variables = [
                "environment" => $settings->getAssetsEnvironment(),
                "projectSlug" => $settings->getProjectSlug(),
                "subfolder" => trim($volume->customSubfolder, "/"),
                "filePath" => $asset->getPath(),
            ];
            $finalUrl = $customPattern;
            foreach ($variables as $key => $value) {
                $finalUrl = str_replace('{{' . $key . '}}', $value, $finalUrl);
            }
            return $finalUrl;
        }

        return AssetsHelper::generateUrl($volume, $asset);
    }

    public function handleAssetTransform(Asset $asset, $transform)
    {
        $volume = $asset->getVolume();

        if (!ImageHelper::canManipulateAsImage(pathinfo($asset->filename, PATHINFO_EXTENSION))) {
            return $this->getFileUrl($asset);
        }

        //If the input type is gif respect the no transform flag
        if ($this->imageTransforms->inputIsGif($asset) && !(Craft::$app->getConfig()->getGeneral()->transformGifs ?? false)) {
            return $this->getFileUrl($asset);
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
            //TODO: Check if the transform is null. If so throw a nice error to let the user know what happened
        }

        //If the output type is svg, no transform is occuring, just let Craft handle it
        //This should return a link to the CDN path without optimisation
        if ($this->imageTransforms->outputWillBeSVG($asset, $transform)) {
            return $this->getFileUrl($asset);
        }

        $transformOptions = new TransformOptions();
        $transformOptions->fillFromCraftTransform($asset, $transform);
        return $this->imageTransforms->transformUrl($asset, $transformOptions);
    }

    private function hookCPSidebarTemplate()
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsCpRequest() && !$request->getIsConsoleRequest()) {

            Craft::$app->view->hook('cp.assets.edit.details', function (array &$context) {
                $html = '';
                $element = $context['element'];
                $volume = $context['volume'];
                if ($volume instanceof AssetStorageVolume) {
                    return Craft::$app->view->renderTemplate('servd-asset-storage/cp-extensions/asset-cache-clear.twig', ['elementUid' => $element->id]);
                }
                return '';
            });
        }
    }
}
