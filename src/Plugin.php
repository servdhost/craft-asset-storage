<?php

namespace servd\AssetStorage;

use Craft;
use craft\base\Model;
use craft\web\twig\variables\CraftVariable;
use servd\AssetStorage\AssetsPlatform\AssetsPlatform;
use servd\AssetStorage\AssetsPlatform\Fs;
use servd\AssetStorage\AssetsPlatform\ImageTransforms;
use servd\AssetStorage\Blitz\BlitzIntegration;
use servd\AssetStorage\CPAlerts\CPAlerts;
use servd\AssetStorage\CsrfInjection\CsrfInjection;
use servd\AssetStorage\Feedme\Feedme;
use servd\AssetStorage\ImageOptimize\ImageOptimize;
use servd\AssetStorage\Imager\Imager;
use servd\AssetStorage\LocalDev\LocalDev;
use servd\AssetStorage\models\Settings;
use servd\AssetStorage\RedisDebug\RedisDebug;
use servd\AssetStorage\StaticCache\StaticCache;
use servd\AssetStorage\StaticCache\Tags;
use servd\AssetStorage\variables\ServdVariable;
use yii\base\Event;

class Plugin extends \craft\base\Plugin
{
    public string $schemaVersion = '3.0.0';
    public static $plugin;
    public bool $hasCpSettings = true;

    public function init():void
    {
        parent::init();
        self::$plugin = $this;

        $settings = $this->getSettings();
        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'servd\\AssetStorage\\console\\controllers';
        } else {
            $this->controllerNamespace = 'servd\\AssetStorage\\controllers';
        }

        $this->registerVariables();
        $this->registerComponentsAndServices();
        $this->initialiseComponentsAndServices();

        $settings->checkForType();

    }

    protected function createSettingsModel() : ?Model
    {
        return new \servd\AssetStorage\models\Settings();
    }

    protected function settingsHtml() : ?string
    {
        $settings = $this->getSettings();
        return \Craft::$app->getView()->renderTemplate('servd-asset-storage/settings', [
            'settings' => $settings,
            'craft35' => version_compare(Craft::$app->getVersion(), '3.5', '>='),

        ]);
    }

    private function registerVariables()
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('servd', ServdVariable::class);
            }
        );
    }

    public function registerComponentsAndServices()
    {
        $this->setComponents([
            'staticCache' => StaticCache::class,
            'tags' => Tags::class,
            'assetsPlatform' => AssetsPlatform::class,
            'imager' => Imager::class,
            'imageOptimize' => ImageOptimize::class,
            'csrfInjection' => CsrfInjection::class,
            'cpAlerts' => CPAlerts::class,
            'redisDebug' => RedisDebug::class,
            'localDev' => LocalDev::class,
            'feedMe' => Feedme::class,
            'blitz' => BlitzIntegration::class,
        ]);
    }

    public function initialiseComponentsAndServices()
    {
        //Resolves the components which calls their init methods automatically
        $this->imager;
        $this->imageOptimize;
        $this->staticCache;
        $this->csrfInjection;
        $this->assetsPlatform;
        $this->cpAlerts;
        $this->redisDebug;
        $this->localDev;
        $this->feedMe;
        $this->blitz;
    }
}