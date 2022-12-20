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

class Fs extends FlysystemFs
{
    const S3_BUCKET = 'cdn-assets-servd-host';
    public $customSubfolder = '';
    public $makeUploadsPublic = true;
    public $optimisePrefix = ''; //DEPRECATED
    public $projectSlug = '';
    public $securityKey = '';
    public $cdnUrlPattern = '';
    public $optimiseUrlPattern = '';

    public $subfolder = ''; //Required for compatibility with Imager-X + Imgix

    public function __construct()
    {
        $this->subfolder = $this->_subfolder();
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
        $base = 'https://cdn2.assets-servd.host/';
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

    /**
     * Creates a Flysystem adapter instance based on the stored settings.
     *
     * @return FilesystemAdapter The Flysystem adapter.
     */
    protected function createAdapter(): FilesystemAdapter
    {
        $config = Plugin::$plugin->assetsPlatform->getS3ConfigArray();
        $client = static::client($config);
        return new AwsS3Adapter($client, AssetsPlatform::S3_BUCKET, $this->_subfolder());
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
