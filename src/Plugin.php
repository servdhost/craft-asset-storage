<?php

namespace servd\AssetStorage;

use craft\events\GetAssetThumbUrlEvent;
use craft\events\GetAssetUrlEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Assets;
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
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                $element = $event->element;
                if (Element::STATUS_ENABLED == $element->getStatus()
                    || Entry::STATUS_LIVE == $element->getStatus()
                ) {
                    Plugin::$plugin->handlers->clearStaticCache($event);
                }
            }
        );

        Event::on(
            TemplateCaches::class,
            TemplateCaches::EVENT_AFTER_DELETE_CACHES,
            function (DeleteTemplateCachesEvent $event) {
                Plugin::$plugin->handlers->clearStaticCache($event);
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
                        Plugin::$plugin->handlers->clearStaticCache();
                    },
                ];
            }
        );
    }
}
