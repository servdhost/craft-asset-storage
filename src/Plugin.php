<?php

namespace servd\AssetStorage;

use craft\base\Element;
use craft\elements\Asset;
use craft\events\AssetTransformImageEvent;
use craft\events\GenerateTransformEvent;
use craft\events\GetAssetThumbUrlEvent;
use craft\events\GetAssetUrlEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\ReplaceAssetEvent;
use craft\services\Assets;
use craft\services\AssetTransforms;
use craft\services\Volumes;
use servd\AssetStorage\services\Handlers;
use servd\AssetStorage\services\Optimise;
use yii\base\Event;

class Plugin extends \craft\base\Plugin
{
    public $schemaVersion = '1.0';
    public static $plugin;

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->registerComponentsAndServices();
        $this->installEventHandlers();
    }

    public function registerComponentsAndServices()
    {
        $this->setComponents([
            'handlers' => Handlers::class,
            'optimise' => Optimise::class,
        ]);
    }

    protected function installEventHandlers()
    {
        Event::on(Volumes::class, Volumes::EVENT_REGISTER_VOLUME_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = Volume::class;
        });

        Event::on(
            Assets::class,
            Assets::EVENT_GET_ASSET_URL,
            function (GetAssetUrlEvent $event) {
                $asset = $event->asset;
                $volume = $asset->getVolume();
                if ($volume instanceof Volume) {
                    $event->url = Plugin::$plugin->handlers->getAssetUrlEvent($event);
                }
            }
        );

        Event::on(
            Assets::class,
            Assets::EVENT_GET_ASSET_THUMB_URL,
            function (GetAssetThumbUrlEvent $event) {
                $asset = $event->asset;
                $volume = $asset->getVolume();
                if ($volume instanceof Volume) {
                    $event->url = Plugin::$plugin->handlers->getAssetThumbUrlEvent($event);
                }
            }
        );

        Event::on(
            AssetTransforms::class,
            AssetTransforms::EVENT_GENERATE_TRANSFORM,
            function (GenerateTransformEvent $event) {
                // Return the path to the optimized image to _createTransformForAsset()
                // $event->tempPath = ImageOptimize::$plugin->optimize->handleGenerateTransformEvent(
                //     $event
                // );
            }
        );

        Event::on(
            AssetTransforms::class,
            AssetTransforms::EVENT_AFTER_DELETE_TRANSFORMS,
            function (AssetTransformImageEvent $event) {
                // ImageOptimize::$plugin->optimize->handleAfterDeleteTransformsEvent(
                //     $event
                // );
            }
        );

        Event::on(
            Assets::class,
            Assets::EVENT_BEFORE_REPLACE_ASSET,
            function (ReplaceAssetEvent $event) {
                // @var Asset $element
                //$element = $event->asset;
                // Purge the URL
                // $purgeUrl = ImageOptimize::$plugin->transformMethod->getPurgeUrl($element);
                // if ($purgeUrl) {
                //     ImageOptimize::$plugin->transformMethod->purgeUrl($purgeUrl);
                // }
            }
        );

        Event::on(
            Assets::class,
            Assets::EVENT_AFTER_REPLACE_ASSET,
            function (ReplaceAssetEvent $event) {
                // @var Asset $element
                // $element = $event->asset;
                // if (null !== $element->id) {
                //     ImageOptimize::$plugin->optimizedImages->resaveAsset($element->id);
                // }
            }
        );
    }
}
