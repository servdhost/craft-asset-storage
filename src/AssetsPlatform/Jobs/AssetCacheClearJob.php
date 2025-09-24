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
    public $path = null;
    public $subfolder = null;

    private $cacheClearUrl = 'https://app.servd.host/asset-platform-clear-cache';

    public function execute($queue): void
    {

        if(!empty($this->path)){
            $assetPath = $this->path;
            $subfolder = $this->subfolder;
        } else {
            $elements = Craft::$app->elements;
            $element = $elements->getElementById($this->elementUid);
    
            //Check that the element is an asset
            if(empty($element->path)){
                return;
            }
            $assetPath = $element->path;
            $subfolder = $element->volume->getFs()->_subfolder() ?? '';
        }

        $settings = Plugin::$plugin->getSettings();
        $slug = $settings->getProjectSlug();
        $environment = $settings->getAssetsEnvironment();
        $securityKey = $settings->getSecurityKey();

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

    protected function defaultDescription(): ?string
    {
        return 'Clear Servd CDN Cache';
    }
}
