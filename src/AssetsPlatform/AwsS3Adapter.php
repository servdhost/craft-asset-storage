<?php

namespace servd\AssetStorage\AssetsPlatform;

use League\Flysystem\AwsS3V3\AwsS3V3Adapter as OriginalAwsS3Adapter;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use League\Flysystem\Config;
use Throwable;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToWriteFile;

class AwsS3Adapter extends OriginalAwsS3Adapter
{

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->client->copy(
                $this->bucket,
                $this->prefixer->prefixPath($source),
                $this->bucket,
                $this->prefixer->prefixPath($destination),
                'public-read',
                $this->createOptionsFromConfig($config)['params']
            );
        } catch (Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    protected function upload(string $path, $body, Config $config): void
    {
        $key = $this->prefixer->prefixPath($path);
        $options = $this->createOptionsFromConfig($config);
        $acl = 'public-read';
        $shouldDetermineMimetype = $body !== '' && ! array_key_exists('ContentType', $options['params']);

        if ($shouldDetermineMimetype && $mimeType = $this->mimeTypeDetector->detectMimeType($key, $body)) {
            $options['params']['ContentType'] = $mimeType;
        }

        try {
            $this->client->upload($this->bucket, $key, $body, $acl, $options);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, '', $exception);
        }
    }


    private function createOptionsFromConfig(Config $config): array
    {
        $config = $config->withDefaults($this->options);
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
