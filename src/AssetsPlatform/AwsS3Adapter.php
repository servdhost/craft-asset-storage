<?php

namespace servd\AssetStorage\AssetsPlatform;

use Aws\Exception\AwsException;
use League\Flysystem\AwsS3v3\AwsS3Adapter as OriginalAwsS3Adapter;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Craft;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use servd\AssetStorage\Plugin;
use servd\AssetStorage\Volume;

class AwsS3Adapter extends OriginalAwsS3Adapter
{

    protected function upload($path, $body, Config $config)
    {
        $config->set('ACL', 'public-read');
        /** @var Stream $body */
        return $this->wrapCredsErrorHandling(function () use ($path, $body, $config) {
            return parent::upload($path, $body, $config);
        });
    }

    public function delete($path)
    {
        return $this->wrapCredsErrorHandling(function () use ($path) {
            return parent::delete($path);
        });
    }

    public function deleteDir($dirname)
    {
        return $this->wrapCredsErrorHandling(function () use ($dirname) {
            return parent::deleteDir($dirname);
        });
    }

    public function has($path)
    {
        return $this->wrapCredsErrorHandling(function () use ($path) {
            return parent::has($path);
        });
    }

    protected function retrievePaginatedListing(array $options)
    {
        return $this->wrapCredsErrorHandling(function () use ($options) {
            return parent::retrievePaginatedListing($options);
        });
    }

    public function getMetadata($path)
    {
        return $this->wrapCredsErrorHandling(function () use ($path) {
            return parent::getMetadata($path);
        });
    }


    public function getRawVisibility($path)
    {
        return $this->wrapCredsErrorHandling(function () use ($path) {
            return parent::getRawVisibility($path);
        });
    }

    public function copy($path, $newpath)
    {
        return $this->wrapCredsErrorHandling(function () use ($path, $newpath) {
            $command = $this->s3Client->getCommand(
                'copyObject',
                [
                    'Bucket'     => $this->bucket,
                    'Key'        => $this->applyPathPrefix($newpath),
                    'CopySource' => S3Client::encodeKey($this->bucket . '/' . $this->applyPathPrefix($path)),
                    //Ignore ACL because we don't use it and it breaks backblaze
                    //'ACL'        => $this->getRawVisibility($path) === AdapterInterface::VISIBILITY_PUBLIC
                    //    ? 'public-read' : 'private',
                ] + $this->options
            );

            try {
                $this->s3Client->execute($command);
            } catch (S3Exception $e) {
                //If it's access denied throw it so that the wrapper block handles it
                if ($e->getAwsErrorCode() == 'AccessDenied') {
                    throw $e;
                }
                return false;
            }

            return true;
        });
    }

    // protected function readObject($path)
    // {
    //     return $this->wrapCredsErrorHandling(function () use ($path) {

    //         $options = [
    //             'Bucket' => $this->bucket,
    //             'Key'    => $this->applyPathPrefix($path),
    //         ] + $this->options;

    //         if ($this->streamReads && !isset($options['@http']['stream'])) {
    //             $options['@http']['stream'] = true;
    //         }

    //         $command = $this->s3Client->getCommand('getObject', $options + $this->options);

    //         try {
    //             /** @var Result $response */
    //             $response = $this->s3Client->execute($command);
    //         } catch (S3Exception $e) {
    //             if ($e->getAwsErrorCode() == 'AccessDenied') {
    //                 throw $e;
    //             }
    //             return false;
    //         }

    //         return $this->normalizeResponse($response->toArray(), $path);
    //     });
    // }

    protected function doesDirectoryExist($location)
    {
        return $this->wrapCredsErrorHandling(function () use ($location) {
            // Maybe this isn't an actual key, but a prefix.
            // Do a prefix listing of objects to determine.
            $command = $this->s3Client->getCommand(
                'listObjects',
                [
                    'Bucket'  => $this->bucket,
                    'Prefix'  => rtrim($location, '/') . '/',
                    'MaxKeys' => 1,
                ]
            );

            try {
                $result = $this->s3Client->execute($command);
                return $result['Contents'] || $result['CommonPrefixes'];
            } catch (S3Exception $e) {
                if ($e->getAwsErrorCode() == 'AccessDenied') {
                    throw $e;
                }
                if (in_array($e->getStatusCode(), [403, 404], true)) {
                    return false;
                }
                throw $e;
            }
        });
    }

    public function setVisibility($path, $visibility)
    {
        return $this->wrapCredsErrorHandling(function () use ($path, $visibility) {
            $command = $this->s3Client->getCommand(
                'putObjectAcl',
                [
                    'Bucket' => $this->bucket,
                    'Key'    => $this->applyPathPrefix($path),
                    'ACL'    => $visibility === AdapterInterface::VISIBILITY_PUBLIC ? 'public-read' : 'private',
                ]
            );

            try {
                $this->s3Client->execute($command);
            } catch (S3Exception $exception) {
                if ($exception->getAwsErrorCode() == 'AccessDenied') {
                    throw $exception;
                }
                return false;
            }

            return compact('path', 'visibility');
        });
    }

    private function wrapCredsErrorHandling($next)
    {
        try {
            return $next();
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() == 'AccessDenied') {
                //Refresh creds and retry
                $config = Plugin::$plugin->assetsPlatform->getS3ConfigArray(true);
                $client = new S3Client($config);
                $this->s3Client = $client;
                return $next(); // Do not catch for a second time, allow to bubble up
            }
            throw $e;
        }
    }
}
