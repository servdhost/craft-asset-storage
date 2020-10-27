<?php

namespace servd\AssetStorage\AssetsPlatform;

use League\Flysystem\AwsS3v3\AwsS3Adapter as OriginalAwsS3Adapter;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use League\Flysystem\Config;

class AwsS3Adapter extends OriginalAwsS3Adapter
{

    public function copy($path, $newpath)
    {
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
            return false;
        }

        return true;
    }

    protected function upload($path, $body, Config $config)
    {
        $config->set('ACL', 'public-read');
        return parent::upload($path, $body, $config);
    }
}
