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
use servd\AssetStorage\volumeDriver\AwsS3Adapter;

class Volume extends FlysystemVolume
{
    const S3_BUCKET = 'cdn.assets-servd.host';
    const S3_REGION = 'eu-west-1';
    const CACHE_KEY_PREFIX = 'servdassets.';
    const CACHE_DURATION_SECONDS = 3600 * 24;
    public $subfolder = '';
    public $projectSlug = '';
    public $securityKey = '';
    public $makeUploadsPublic = true;
    public static $bucketName = 'cdn.assets-servd.host';
    public $optimisePrefix = '';

    protected $isVolumeLocal = false;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
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
        $fullPath = $this->_getProjectSlug() . '/';

        $environment = getenv('ENVIRONMENT');
        if ('staging' == $environment || 'production' == $environment) {
            $fullPath .= $environment . '/';
        } else {
            $fullPath .= 'local/';
        }

        $trimmedSubfolder = rtrim(Craft::parseEnv($this->subfolder), '/');
        if (!empty($trimmedSubfolder)) {
            $fullPath .= $trimmedSubfolder . '/';
        }

        return $fullPath;
    }

    protected function createAdapter()
    {
        $config = $this->_getConfigArray();

        $client = static::client($config);

        return new AwsS3Adapter($client, static::$bucketName, $this->_subfolder());
    }

    protected static function client(array $config = []): S3Client
    {
        return new S3Client($config);
    }

    protected function visibility(): string
    {
        return AdapterInterface::VISIBILITY_PUBLIC;
        //return $this->makeUploadsPublic ? AdapterInterface::VISIBILITY_PUBLIC : AdapterInterface::VISIBILITY_PRIVATE;
    }

    private function _getProjectSlug()
    {
        if (!empty($this->projectSlug)) {
            return Craft::parseEnv($this->projectSlug);
        }

        return getenv('SERVD_PROJECT_SLUG');
    }

    private function _getSecurityKey()
    {
        if (!empty($this->securityKey)) {
            return Craft::parseEnv($this->securityKey);
        }

        return getenv('SERVD_SECURITY_KEY');
    }

    private function _getConfigArray()
    {
        $projectSlug = $this->_getProjectSlug();
        $securityKey = $this->_getSecurityKey();

        return self::_buildConfigArray($projectSlug, $securityKey);
    }

    private static function _buildConfigArray($projectSlug, $securityKey)
    {
        $config = [
            'region' => static::S3_REGION,
            'version' => 'latest',
        ];

        $credentials = [];
        $tokenKey = static::CACHE_KEY_PREFIX . md5($projectSlug);
        if (Craft::$app->cache->exists($tokenKey)) {
            $credentials = Craft::$app->cache->get($tokenKey);
        } else {
            //Grab tokens from token service
            $credentials = self::_getSecurityToken($projectSlug, $securityKey);
            Craft::$app->cache->set($tokenKey, $credentials, static::CACHE_DURATION_SECONDS);
        }

        $config['credentials'] = $credentials;
        if (isset($credentials['backblaze']) && $credentials['backblaze'] == true) {
            $config['endpoint'] = 'https://s3.eu-central-003.backblazeb2.com';
            static::$bucketName = 'cdn-assets-servd-host';
        }

        $client = Craft::createGuzzleClient();
        $config['http_handler'] = new GuzzleHandler($client);

        return $config;
    }

    private static function _getSecurityToken($projectSlug, $securityKey)
    {
        $securityTokenUrl = getenv('SECURITY_TOKEN_URL');
        if (empty($securityTokenUrl)) {
            $securityTokenUrl = 'https://app.servd.host/create-assets-token';
        }

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

        return $res['credentials'];
    }
}
