<?php

namespace servd\AssetStorage;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\events\DeleteTemplateCachesEvent;
use craft\events\ElementEvent;
use craft\events\GetAssetThumbUrlEvent;
use craft\events\GetAssetUrlEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Assets;
use craft\services\Elements;
use craft\services\TemplateCaches;
use craft\services\Volumes;
use craft\utilities\ClearCaches;
use craft\web\View;
use servd\AssetStorage\services\Handlers;
use servd\AssetStorage\services\Optimise;
use yii\base\Event;

class Plugin extends \craft\base\Plugin
{
    public $schemaVersion = '1.0';
    public static $plugin;
    public $hasCpSettings = true;

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $settings = $this->getSettings();

        $this->registerComponentsAndServices();
        $this->installEventHandlers();
        if ($settings->injectCors) {
            $this->injectCSRFTokenScript();
        }
    }

    protected function createSettingsModel()
    {
        return new \servd\AssetStorage\models\Settings();
    }

    protected function settingsHtml()
    {
        return \Craft::$app->getView()->renderTemplate('servd-asset-storage/settings', [
            'settings' => $this->getSettings()
        ]);
    }

    public function registerComponentsAndServices()
    {
        $this->setComponents([
            'handlers' => Handlers::class,
            'optimise' => Optimise::class,
        ]);
    }

    protected function injectCSRFTokenScript()
    {
        $view = Craft::$app->getView();

        if (!Craft::$app->getRequest()->getIsCpRequest()) {
            $url = '/' . Craft::$app->getConfig()->getGeneral()->actionTrigger . '/servd-asset-storage/csrf-token/get-token';
            $view->registerJs('
                function injectCSRF() {
                    var xhr = new XMLHttpRequest();
                    xhr.onload = function () {
                        if (xhr.status >= 200 && xhr.status <= 299) {
                            var tokenInfo = JSON.parse(this.responseText);
                            window.csrfTokenName = tokenInfo.name;
                            window.csrfTokenValue = tokenInfo.token;
                            var inputs = document.getElementsByName(tokenInfo.name);
                            var len = inputs.length;
                            for (var i=0; i<len; i++) {
                                inputs[i].setAttribute("value", tokenInfo.token);
                            }
                        }
                    };
                    xhr.open("GET", "' . $url . '");
                    xhr.send();
                }
                setTimeout(injectCSRF, 200);
            ', View::POS_END);
        }
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
                if (
                    Element::STATUS_ENABLED == $element->getStatus()
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
