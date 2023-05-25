<?php

/**
 * @see https://servd.host/
 *
 * @copyright Copyright (c) Bit Breakfast Ltd.
 * @license MIT
 */

namespace servd\AssetStorage;

use Aws\Handler\GuzzleV6\GuzzleHandler;
use Aws\S3\S3Client;
use Craft;
use craft\base\FlysystemVolume;
use craft\behaviors\EnvAttributeParserBehavior;
use League\Flysystem\AdapterInterface;
use servd\AssetStorage\assetsPlatform\AssetsPlatform;
use servd\AssetStorage\AssetsPlatform\AwsS3Adapter;
use servd\AssetStorage\models\Settings;

class Volume extends FlysystemVolume
{

    public $customSubfolder = '';
    public $makeUploadsPublic = true;
    public $optimisePrefix = ''; //DEPRECATED
    public $projectSlug = '';
    public $securityKey = '';
    public $cdnUrlPattern = '';
    public $optimiseUrlPattern = '';

    public $subfolder = null;

    protected $isVolumeLocal = false;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $pluginInstance = Plugin::$plugin;
        if(empty($pluginInstance)){
            //Plugin hasn't been initialised - maybe we're in a migration?
        } else {
            $settings = $pluginInstance->getSettings();
            $this->subfolder = $this->_subfolder();
            if (Settings::$CURRENT_TYPE == 'wasabi') {
                $this->url = 'https://' . $settings->getProjectSlug() . '.files.svdcdn.com';
            } else {
                $this->url = 'https://cdn2.assets-servd.host/';
            }
        }
    }

    public static function displayName(): string
    {
        return 'Servd Asset Storage';
    }

    public function behaviors()
    {
        return parent::behaviors();
    }

    public function rules()
    {
        return parent::rules();
    }

    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('servd-asset-storage/volumeSettings', [
            'volume' => $this,
        ]);
    }

    public function getRootUrl()
    {
        if (false !== ($rootUrl = parent::getRootUrl())) {
            $rootUrl .= $this->_subfolder();
        }

        return $rootUrl;
    }

    public function _subfolder(): string
    {
        $settings = Plugin::$plugin->getSettings();
        $environment = $settings->getAssetsEnvironment();

        $fullPath = Plugin::$plugin->assetsPlatform->getStorageBaseDirectory();
        $fullPath .= trim($environment, '/') . '/';

        $trimmedSubfolder = trim(Craft::parseEnv($this->customSubfolder), '/');
        if (!empty($trimmedSubfolder)) {
            $fullPath .= $trimmedSubfolder . '/';
        }

        return $fullPath;
    }

    protected function createAdapter()
    {
        $config = Plugin::$plugin->assetsPlatform->getS3ConfigArray();
        $client = static::client($config);
        return new AwsS3Adapter($client, $config['bucket'], $this->_subfolder(), [], false);
    }

    protected static function client(array $config = []): S3Client
    {
        return new S3Client($config);
    }

    protected function visibility(): string
    {
        return AdapterInterface::VISIBILITY_PUBLIC;
    }
}
