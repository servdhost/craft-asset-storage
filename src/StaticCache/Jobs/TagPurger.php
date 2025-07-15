<?php

namespace servd\AssetStorage\StaticCache\Jobs;

use Exception;
use servd\AssetStorage\Plugin;
use servd\AssetStorage\StaticCache\Ledge;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

trait TagPurger
{
    protected $cacheClearUrl = 'https://app.servd.host/clear-edge-cache';

    protected function purgeUrlsForTag($tag): void
    {
        $tags = Plugin::$plugin->get('tags');
        $tags->iterateUrlsForTag($tag, function ($urls) use ($tags) {
            try {
                Ledge::purgeUrls($urls);
                foreach ($urls as $url) {
                    $tags->clearTagsForUrl($url);
                }
            } catch (Exception $e) {
                throw new Exception("Failed to purge all urls: " . $e->getMessage());
            }
        });
    }

    private function clearEdgeCache(array $tags)
    {
        $settings = Plugin::$plugin->getSettings();

        $url = $this->cacheClearUrl;
        if (!empty(getenv('SERVD_EDGE_CACHE_CLEAR_URL'))) {
            $url = getenv('SERVD_EDGE_CACHE_CLEAR_URL');
        }

        $tagsList = array_map(function($t) {
            return getenv('SERVD_PROJECT_SLUG') . '-env-' . getenv('ENVIRONMENT') . '-' . $t;
        }, $tags);

        try {
            $client = new Client();
            $client->post($url, [
                'json' => [
                    'slug' => $settings->getProjectSlug(),
                    'environment' => $settings->getAssetsEnvironment(),
                    'key' => $settings->getSecurityKey(),
                    'tags' => implode(',', $tagsList)
                ]
            ]);
        } catch (GuzzleException $e) {
            throw new Exception("Failed to contact Servd edge cache clear endpoint: " . $e->getMessage());
        }
    }
}
