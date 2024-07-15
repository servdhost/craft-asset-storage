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
use servd\AssetStorage\models\Settings;
use servd\AssetStorage\Plugin;
use servd\AssetStorage\Volume as AssetStorageVolume;
use yii\base\Event;

class AssetsPlatform extends Component
{

    const CACHE_KEY_PREFIX = 'servdassets3.';
    const CACHE_DURATION_SECONDS = 3600 * 24;
    const DEFAULT_SECURITY_TOKEN_URL = 'https://app.servd.host/create-assets-token';
    const CACHE_KEY_TYPE = 'servdassets.type';

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
        if (Settings::$CURRENT_TYPE == 'wasabi') {
            $fullPath = '';
        } else {
            $fullPath = $settings->getProjectSlug() . '/';
        }
        return $fullPath;
    }

    public function getCacheKey($type)
    {
        $settings = Plugin::$plugin->getSettings();
        $v3 = Settings::$CURRENT_TYPE == 'wasabi';
        $projectSlug = $forceSlug ?? $settings->getProjectSlug();
        return static::CACHE_KEY_PREFIX . $type . '.' . md5($projectSlug) . '.' . ($v3 ? 'v3' : 'v2');
    }

    public function getStorageInfoFromServd($forceSlug = null, $forceKey = null)
    {
        $settings = Plugin::$plugin->getSettings();
        $projectSlug = $forceSlug ?? $settings->getProjectSlug();
        $securityKey = $forceKey ?? $settings->getSecurityKey();

        $credentials = [];
        $v3 = Settings::$CURRENT_TYPE == 'wasabi';
        $tokenKey = $this->getCacheKey('creds');
        $usageKey = $this->getCacheKey('usage');
        if (Craft::$app->cache->exists($tokenKey)) {
            $credentials = Craft::$app->cache->get($tokenKey);
            $usage = Craft::$app->cache->get($usageKey);
            $type = Craft::$app->cache->get(self::CACHE_KEY_TYPE);
        } else {
            //Grab tokens from token service
            $credentialsResponse = $this->getSecurityToken($projectSlug, $securityKey);
            $credentials = $credentialsResponse['credentials'];
            $usage = $credentialsResponse['usage'] ?? 0;
            $type = $credentialsResponse['type'] ?? 'backblaze';

            Craft::$app->cache->set($tokenKey, $credentials, static::CACHE_DURATION_SECONDS);
            Craft::$app->cache->set($usageKey, $usage, static::CACHE_DURATION_SECONDS);
            Craft::$app->cache->set(self::CACHE_KEY_TYPE, $type, 0);
        }

        $bucket = 'cdn-assets-servd-host';
        if (Settings::$CURRENT_TYPE == 'wasabi') {
            $bucket = 'servd-' . $projectSlug;
        }

        return [
            'type' => $type,
            'credentials' => $credentials,
            'usage' => $usage,
            'bucket' => $bucket
        ];
    }

    public function getS3ConfigArray($forceSlug = null, $forceKey = null)
    {

        $servdResponse = $this->getStorageInfoFromServd($forceSlug, $forceKey);

        $config = [
            'region' => $servdResponse['credentials']['region'],
            'version' => 'latest',
            'http'    => [
                'connect_timeout' => 3,
                'timeout' => 30,
            ]
        ];
        $config['bucket'] = $servdResponse['bucket'];
        $config['credentials'] = [
            'key' => $servdResponse['credentials']['key'],
            'secret' => $servdResponse['credentials']['secret'],
        ];
        $config['endpoint'] = $servdResponse['credentials']['endpoint'];
        $config['use_path_style_endpoint'] = true;
        $config['dual_stack'] = false;
        $config['accelerate'] = false;
        $client = Craft::createGuzzleClient();
        $config['http_handler'] = new GuzzleHandler($client);

        return $config;
    }

    public function getCurrentStorageType()
    {
        return Craft::$app->cache->get(self::CACHE_KEY_TYPE) ?? null;
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
                'connect_timeout' => 1, //If the user is offline, bail after 1 sec
                'timeout' => 20, //If the Servd servers aren't responding, wait max 5 seconds
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

                $pluginInstance = Plugin::$plugin;
                if (!empty($pluginInstance)) {
                    $settings = $pluginInstance->getSettings();
                    if (Settings::$CURRENT_TYPE == 'wasabi') {
                        $volume->url = 'https://' . $settings->getProjectSlug() . '.files.svdcdn.com';
                    } else {
                        $volume->url = 'https://cdn2.assets-servd.host/';
                    }
                }
            }
        });

        $settings = Plugin::$plugin->getSettings();
        if (!$settings->disableTransforms) {
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
                        $event->handled = true;
                        $event->url = $this->handleAssetTransform($asset, $transform, true);
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

                        $event->handled = true;
                        $event->url = $this->handleAssetTransform($asset, $transform, false);
                    }
                }
            );
        }
    }

    public function getFileUrl(Asset $asset)
    {

        $settings = Plugin::$plugin->getSettings();
        $volume = $asset->getVolume();

        $normalizedCustomSubfolder = Craft::parseEnv($volume->customSubfolder);

        //in_array($asset->getExtension(), craft\helpers\Assets::getFileKinds()[craft\elements\Asset::KIND_VIDEO]['extensions']);

        //Special handling for videos
        $assetIsVideo = AssetsHelper::getFileKindByExtension($asset->filename) === Asset::KIND_VIDEO
            || in_array(strtolower($asset->getExtension()), AssetsHelper::getFileKinds()[Asset::KIND_VIDEO]['extensions']);
        if ($assetIsVideo) {
            return 'https://servd-' . $settings->getProjectSlug() . '.b-cdn.net/' .
                $settings->getAssetsEnvironment() . '/' .
                (strlen(trim($normalizedCustomSubfolder, "/")) > 0 ? (trim($normalizedCustomSubfolder, "/") . '/') : '') .
                $asset->getPath();
        }

        //If a custom pattern is set, use that
        $customPattern = Craft::parseEnv($volume->cdnUrlPattern);
        if (!empty($customPattern)) {
            $variables = [
                "environment" => $settings->getAssetsEnvironment(),
                "projectSlug" => $settings->getProjectSlug(),
                "subfolder" => trim($normalizedCustomSubfolder, "/"),
                "filePath" => $asset->getPath(),
            ];
            $finalUrl = $customPattern;
            foreach ($variables as $key => $value) {
                $finalUrl = str_replace('{{' . $key . '}}', $value, $finalUrl);
            }
            //Apply rawurlencode to match AssetsHelper::generateUrl behaviour
            $urlParts = parse_url($finalUrl);
            $finalUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . implode('/', array_map('rawurlencode', explode('/', $urlParts['path'])));

        }else {
            $finalUrl = AssetsHelper::generateUrl($volume, $asset);
        }
        return $finalUrl;
    }

    public function handleAssetTransform(Asset $asset, $transform, $force = true)
    {

        // Check if the file can be handled by the Servd Asset Platform as an image
        $extension = strtolower(pathinfo($asset->filename, PATHINFO_EXTENSION));
        $assetPlatformSupportedTypes = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'avif'];

        //If the input type is gif respect the no transform flag
        if(Craft::$app->getConfig()->getGeneral()->transformGifs ?? false) {
            $assetPlatformSupportedTypes[] = 'gif';
        }

        if (!in_array($extension, $assetPlatformSupportedTypes)) {
            if ($force) {
                return $this->getFileUrl($asset);
            }
            return;
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
        if ($transform->format ?? 'auto' === 'svg') {
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
