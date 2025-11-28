<?php

/**
 * @see https://servd.host/
 *
 * @copyright Copyright (c) Bit Breakfast Ltd.
 * @license MIT
 */

namespace servd\AssetStorage\AssetsPlatform;

use Aws\Handler\GuzzleV6\GuzzleHandler;
use Aws\S3\S3Client;
use Craft;
use craft\flysystem\base\FlysystemFs;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\helpers\App;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use servd\AssetStorage\assetsPlatform\AssetsPlatform;
use servd\AssetStorage\AssetsPlatform\AwsS3Adapter;
use servd\AssetStorage\Plugin;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use servd\AssetStorage\models\Settings;

class Fs extends FlysystemFs
{
    //const S3_BUCKET = 'cdn-assets-servd-host';
    public $customSubfolder = '';
    public $makeUploadsPublic = true;
    public $optimisePrefix = ''; //DEPRECATED
    public $projectSlug = '';
    public $securityKey = '';
    public $cdnUrlPattern = '';
    public $optimiseUrlPattern = '';
    public $disableTransforms = false;

    public $subfolder = ''; //Required for compatibility with Imager-X + Imgix

    public function __construct($config = [])
    {
        parent::__construct($config);
        $settings = Plugin::$plugin->getSettings();
        $this->subfolder = $this->_subfolder();
        if(Settings::$CURRENT_TYPE == 'wasabi'){
            $this->url = 'https://' . $settings->getProjectSlug() . '.files.svdcdn.com';
        } else {
            $this->url = 'https://cdn2.assets-servd.host/';
        }
    }

    public static function displayName(): string
    {
        return 'Servd Asset Storage';
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('servd-asset-storage/fsSettings', [
            'fs' => $this,
        ]);
    }

    public function getRootUrl(): ?string
    {
        $base = rtrim($this->url, '/') . '/';
        return $base . $this->_subfolder();
    }

    public function _subfolder(): string
    {
        $settings = Plugin::$plugin->getSettings();
        $environment = $settings->getAssetsEnvironment();

        $fullPath = Plugin::$plugin->assetsPlatform->getStorageBaseDirectory();
        $fullPath .= trim($environment, '/') . '/';

        $trimmedSubfolder = trim(App::parseEnv($this->customSubfolder), '/');
        if (!empty($trimmedSubfolder)) {
            $fullPath .= $trimmedSubfolder . '/';
        }

        return $fullPath;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = ['cdnUrlPattern', 'validateCdnUrlPattern'];
        $rules[] = ['optimiseUrlPattern', 'validateOptimiseUrlPattern'];
        return $rules;
    }

    public function validateCdnUrlPattern($attribute)
    {
        $value = $this->$attribute;

        // Check if the value is an environment variable reference
        if (strpos($value, '$') === 0) {
            $value = App::parseEnv($value); // This will return the actual value of the environment variable
            if (strpos($value, '{{params}}') !== false) {
                $this->addError($attribute, "This environment variable contains the {{params}} placeholder, which isn't available for the CDN URL Pattern.");
            }
        } else if (strpos($value, '{{params}}') !== false) {
            $this->addError($attribute, "The {{params}} placeholder isn't available for the CDN URL Pattern.");
        }
    }

    public function validateOptimiseUrlPattern($attribute)
    {
        $value = $this->$attribute;

        // Check if the value is an environment variable reference
        if (strpos($value, '$') === 0) {
            $value = App::parseEnv($value); // This will return the actual value of the environment variable
            if (strpos($value, '{{params}}') === false) {
                $this->addError($attribute, "This environment variable doesn't contain the {{params}} placeholder, which is required for the Transform URL Pattern.");
            }
        } else if (strpos($value, '{{params}}') === false) {
            $this->addError($attribute, "The Transform URL Pattern must contain the {{params}} placeholder.");
        }
    }

    /**
     * Creates a Flysystem adapter instance based on the stored settings.
     *
     * @return FilesystemAdapter The Flysystem adapter.
     */
    protected function createAdapter(): FilesystemAdapter
    {
        $config = Plugin::$plugin->assetsPlatform->getS3ConfigArray();
        $client = static::client($config);
        return new AwsS3Adapter($client, $config['bucket'], $this->_subfolder());
    }

    protected static function client(array $config = []): S3Client
    {
        return new S3Client($config);
    }

    protected function visibility(): string
    {
        return Visibility::PUBLIC;
    }

    protected function invalidateCdnPath(string $path): bool
    {
        return true;
    }
}
