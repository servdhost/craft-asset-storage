<?php
/**
 * @link https://servd.host/
 * @copyright Copyright (c) Bit Breakfast Ltd.
 * @license MIT
 */

namespace servd\AssetStorage;

use Aws\CloudFront\CloudFrontClient;
use Aws\CloudFront\Exception\CloudFrontException;
use Aws\Credentials\Credentials;
use Aws\Handler\GuzzleV6\GuzzleHandler;
use Aws\Rekognition\RekognitionClient;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Aws\Sts\StsClient;
use Craft;
use craft\base\FlysystemVolume;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\helpers\ArrayHelper;
use craft\helpers\Assets;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
use DateTime;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\AdapterInterface;

class Volume extends FlysystemVolume
{

    const S3_BUCKET = 'cdn.assets-servd.host';
    const S3_REGION = 'eu-west-1';
    const CACHE_KEY_PREFIX = 'servdassets.';
    const CACHE_DURATION_SECONDS = 3600 * 24;

    public static function displayName(): string
    {
        return 'Servd Asset Storage';
    }

    protected $isVolumeLocal = false;
    public $subfolder = '';
    public $projectSlug = '';
    public $securityKey = '';
    public $makeUploadsPublic = true;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // $behaviors['parser'] = [
        //     'class' => EnvAttributeParserBehavior::class,
        //     'attributes' => [
        //         'keyId',
        //         'secret',
        //         'subfolder',
        //     ],
        // ];
        return $behaviors;
    }

    public function rules()
    {
        $rules = parent::rules();
        //$rules[] = [['bucket', 'region'], 'required'];

        return $rules;
    }

    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('servd-asset-storage/volumeSettings', [
            'volume' => $this
        ]);
    }

    public function getRootUrl()
    {
        if (($rootUrl = parent::getRootUrl()) !== false) {
            $rootUrl .= $this->_subfolder();
        }
        return $rootUrl;
    }

    protected function createAdapter()
    {
        $config = $this->_getConfigArray();

        $client = static::client($config);

        return new AwsS3Adapter($client, static::S3_BUCKET, $this->_subfolder());
    }

    protected static function client(array $config = []): S3Client
    {
        return new S3Client($config);
    }

    private function _subfolder(): string
    {
        $fullPath = $this->_getProjectSlug() . '/';

        $environment = getenv('ENVIRONMENT');
        if ($environment == 'stage' || $environment == 'prod') {
            $fullPath .= $environment . '/';
        } else {
            $fullPath .= 'local/';
        }

        if (!empty($this->subfolder)) {
            $fullPath .= rtrim(Craft::parseEnv($this->subfolder), '/') . '/';
        }

        return $fullPath;
    }

    private function _getProjectSlug()
    {
        if(!empty($this->projectSlug)){
            return Craft::parseEnv($this->projectSlug);
        }
        return getenv('SERVD_PROJECT_SLUG');
    }

    private function _getSecurityKey()
    {
        if(!empty($this->securityKey)){
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
            'version' => 'latest'
        ];

        $tokenKey = static::CACHE_KEY_PREFIX . md5($projectSlug);
        if (Craft::$app->cache->exists($tokenKey)) {
            $cached = Craft::$app->cache->get($tokenKey);
            $config['credentials'] = $cached;
        } else {
            //Grab tokens from token service
            $credentials = self::_getSecurityToken($projectSlug, $securityKey);
            Craft::$app->cache->set($tokenKey, $credentials, static::CACHE_DURATION_SECONDS);
            $config['credentials'] = $credentials;
        }

        $client = Craft::createGuzzleClient();
        $config['http_handler'] = new GuzzleHandler($client);

        return $config;
    }

    private static function _getSecurityToken($projectSlug, $securityKey)
    {

        $securityTokenUrl = getenv('SECURITY_TOKEN_URL');
        if (empty($securityTokenUrl)) {
            $securityTokenUrl = 'https://servd.host/create-assets-token';
        }

        $client = Craft::createGuzzleClient();
        $response = $client->post( $securityTokenUrl , [
            'headers' => [
                'Accept'     => 'application/json',
            ],
            'form_params' => [
                'slug' => $projectSlug,
                'key' => $securityKey
            ]
        ]);
        $res = json_decode($response->getBody(), true);
        return $res['credentials'];
        // return [
        //     "key"    => "ASIAWAH5TOD4KBGQMQVS",
        //     "secret" => "w1EhWnXM2S4c1q5tnlshlSv9frBHY+geyMJkqXU7",
        //     "token"  => "FQoGZXIvYXdzEF8aDAywfwvhOGHxRhA3UyKyA/r81aToL9vzVkv3M9UZjGvOxptNvpyeJPQJ0w3gqzsmh1jlaCMzKcm1kgAx2hnmkjVfp0rV+E7hMGjptU8qAph/nXKf4zjC41/j/1TFbbAxQJS50X4VUhDa0VMR6CHKdI5Wa09Rf1N9FVmdvz85BlgLCiODpxUddBTSbtp7hfBB9TN6JQquVKzoww/gThB8nwjiocFw5q4ENtTGwDz8dltE587ebkBNLlhiylaWAfB8YZb6B4EZDsDPmT06gYNt5WswBpH0PZF2AxHnZcrafXBxFlNVLHTs/coJ9gykA8Nu+M62og69NYenKTYiVZzrrC3uPq/04KVd7vXk8gQmb4zYZJa7niQiGizTfXm2tAJC3nDqK4Ta0X7Jnx/Thl8BBKQgKzlnReMZtVUm2BZakCr19DQpsy6aWoCFjl+UvJ6mKhVeMWA2DM/BMUfO8lArmSeLdXVTjh+Oqyiqp2mE7f0DIRtcJ1x1XTxZWA4+47tbP5xYiB2AkesQSLJMRC4xoH9QiSB/K80S4dEyEvhkpGAuLQvxUxF7MyawwjoPGpt+Iw4zbbwDpxZavrWmZ2AZqg2dKLnM5OgF"
        // ];
    }

    protected function visibility(): string {
        return $this->makeUploadsPublic ? AdapterInterface::VISIBILITY_PUBLIC : AdapterInterface::VISIBILITY_PRIVATE;
    }
}
