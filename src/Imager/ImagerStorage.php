<?php

namespace servd\AssetStorage\Imager;

use Craft;
use spacecatninja\imagerx\externalstorage\ImagerStorageInterface;
use craft\helpers\FileHelper;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use servd\AssetStorage\AssetsPlatform\AssetsPlatform;
use servd\AssetStorage\Plugin;
use spacecatninja\imagerx\models\ConfigModel;
use spacecatninja\imagerx\services\ImagerService;

class ImagerStorage implements ImagerStorageInterface
{

    public static function upload(string $file, string $uri, bool $isFinal, array $settings): bool
    {
        /** @var ConfigModel $settings */
        $config = ImagerService::getConfig();

        $clientConfig = Plugin::$plugin->assetsPlatform->getS3ConfigArray();

        try {
            $s3 = new S3Client($clientConfig);
        } catch (\InvalidArgumentException $e) {
            Craft::error('Invalid configuration of S3 Client: ' . $e->getMessage(), __METHOD__);
            return false;
        }

        $baseUri = Plugin::$plugin->assetsPlatform->getStorageBaseDirectory();

        if (isset($settings['folder']) && $settings['folder'] !== '') {
            $baseUri .= FileHelper::normalizePath($settings['folder'] . '/');
        }
        $uri = $baseUri . $uri;

        // Always use forward slashes for S3
        $uri = str_replace('\\', '/', $uri);

        // Dont start with forward slashes
        $uri = ltrim($uri, '/');

        $opts = $settings['requestHeaders'] ?? [];
        $cacheDuration = $isFinal ? $config->cacheDurationExternalStorage : $config->cacheDurationNonOptimized;

        if (!isset($opts['Cache-Control'])) {
            $opts['CacheControl'] = 'max-age=' . $cacheDuration . ', must-revalidate';
        }

        $opts = array_merge($opts, [
            'Bucket' => AssetsPlatform::S3_BUCKET,
            'Key' => $uri,
            'Body' => fopen($file, 'rb'),
            //'ACL' => $visibility,
            //'StorageClass' => self::getAWSStorageClass($settings['storageType'] ?? 'standard'),
        ]);

        try {
            $s3->putObject($opts);
        } catch (S3Exception $e) {
            Craft::error('An error occured while uploading to Servd: ' . $e->getMessage(), __METHOD__);
            return false;
        }

        return true;
    }
}
