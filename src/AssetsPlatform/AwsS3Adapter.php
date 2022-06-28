<?php

namespace servd\AssetStorage\AssetsPlatform;

use League\Flysystem\AwsS3V3\AwsS3V3Adapter as OriginalAwsS3Adapter;
use Aws\S3\S3ClientInterface;
use League\Flysystem\Config;
use Throwable;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\MimeTypeDetector;
use League\Flysystem\PathPrefixer;
use League\Flysystem\VisibilityConverter;

class AwsS3Adapter extends OriginalAwsS3Adapter
{
    public const EXTRA_METADATA_FIELDS = [
        'Metadata',
        'StorageClass',
        'ETag',
        'VersionId',
    ];

    public function __construct(
        S3ClientInterface $client,
        string $bucket,
        string $prefix = '',
        VisibilityConverter $visibility = null,
        MimeTypeDetector $mimeTypeDetector = null,
        array $options = [],
        bool $streamReads = true
    ) {
        parent::__construct($client, $bucket, $prefix, $visibility, $mimeTypeDetector, $options, $streamReads);
        $this->servdPrefixer = new PathPrefixer($prefix);
        $this->servdClient = $client;
        $this->servdBucket = $bucket;
        $this->servdPrefix = $prefix;
        $this->servdMimeTypeDetector = $mimeTypeDetector;
        $this->servdOptions = $options;
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->servdClient->copy(
                $this->servdBucket,
                $this->servdPrefixer->prefixPath($source),
                $this->servdBucket,
                $this->servdPrefixer->prefixPath($destination),
                'public-read',
                $this->createOptionsFromConfig($config)['params']
            );
        } catch (Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    protected function upload(string $path, $body, Config $config): void
    {
        $key = $this->servdPrefixer->prefixPath($path);
        $options = $this->createOptionsFromConfig($config);
        $acl = 'public-read';
        $shouldDetermineMimetype = $body !== '' && ! array_key_exists('ContentType', $options['params']);

        if ($shouldDetermineMimetype && $mimeType = $this->servdMimeTypeDetector->detectMimeType($key, $body)) {
            $options['params']['ContentType'] = $mimeType;
        }

        try {
            $this->servdClient->upload($this->servdBucket, $key, $body, $acl, $options);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, '', $exception);
        }
    }


    private function createOptionsFromConfig(Config $config): array
    {
        $config = $config->withDefaults($this->servdOptions);
        $options = ['params' => []];

        if ($mimetype = $config->get('mimetype')) {
            $options['params']['ContentType'] = $mimetype;
        }

        foreach (static::AVAILABLE_OPTIONS as $option) {
            $value = $config->get($option, '__NOT_SET__');

            if ($value !== '__NOT_SET__') {
                $options['params'][$option] = $value;
            }
        }

        foreach (static::MUP_AVAILABLE_OPTIONS as $option) {
            $value = $config->get($option, '__NOT_SET__');

            if ($value !== '__NOT_SET__') {
                $options[$option] = $value;
            }
        }

        return $options;
    }
}
