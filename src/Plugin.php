<?php

namespace servd\AssetStorage;

use Craft;
use servd\AssetStorage\AssetsPlatform\AssetsPlatform;
use servd\AssetStorage\AssetsPlatform\ImageTransforms;
use servd\AssetStorage\CPAlerts\CPAlerts;
use servd\AssetStorage\CsrfInjection\CsrfInjection;
use servd\AssetStorage\ImageOptimize\ImageOptimize;
use servd\AssetStorage\Imager\Imager;
use servd\AssetStorage\RedisDebug\RedisDebug;
use servd\AssetStorage\StaticCache\StaticCache;
use yii\base\Event;

class Plugin extends \craft\base\Plugin
{
    public $schemaVersion = '2.0.0';
    public static $plugin;
    public $hasCpSettings = true;

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $settings = $this->getSettings();

        $this->registerComponentsAndServices();
        $this->initialiseComponentsAndServices();
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
            'staticCache' => StaticCache::class,
            'assetsPlatform' => AssetsPlatform::class,
            'imager' => Imager::class,
            'imageOptimize' => ImageOptimize::class,
            'csrfInjection' => CsrfInjection::class,
            'cpAlerts' => CPAlerts::class,
            'redisDebug' => RedisDebug::class,
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
    }
}
