<?php

namespace servd\AssetStorage\AssetsPlatform\Jobs;

use Craft;
use craft\queue\BaseJob;
use Exception;
use GuzzleHttp\Client;
use servd\AssetStorage\Plugin;

class AssetCacheClearJob extends BaseJob
{
    public $elementUid;
    private $cacheClearUrl = 'https://app.servd.host/asset-platform-clear-cache';

    public function execute($queue)
    {
        $elements = Craft::$app->elements;
        $element = $elements->getElementById($this->elementUid);

        $settings = Plugin::$plugin->getSettings();
        $slug = $settings->getProjectSlug();
        $environment = $settings->getAssetsEnvironment();
        $assetPath = $element->path;
        $securityKey = $settings->getSecurityKey();
        $subfolder = $element->volume->_subfolder() ?? '';

        $assetPath = '/' . trim($subfolder, '/') . '/' . trim($assetPath, '/');

        $data = [
            'slug' => $slug,
            'assetPath' => $assetPath,
            'key' => $securityKey
        ];

        $url = $this->cacheClearUrl;
        if (!empty(getenv('SERVD_ASSET_CACHE_CLEAR_URL'))) {
            $url = getenv('SERVD_ASSET_CACHE_CLEAR_URL');
        }

        try {
            $client = new Client();
            $response = $client->post($url, [
                'json' => $data
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new Exception("Failed to contact Servd cache clear endpoint: " . $e->getMessage());
        }
    }

    protected function defaultDescription()
    {
        return 'Clear Servd CDN Cache';
    }
}
