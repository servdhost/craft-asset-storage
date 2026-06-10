<?php

namespace servd\AssetStorage\StaticCache\Jobs;

use Exception;
use servd\AssetStorage\Plugin;
use servd\AssetStorage\StaticCache\Ledge;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

trait Purger
{
    protected $cacheClearUrl = 'https://app.servd.host/clear-edge-caches';

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

    private function purgeEdgeCacheForEnvironment()
    {
        return $this->sendEdgeCacheRequest();
    }

    private function purgeEdgeCacheForTags(array $tags)
    {
        return $this->sendEdgeCacheRequest(['tags' => implode(',', $tags)]);
    }

    private function purgeEdgeCacheForUrls(array $urls)
    {
        return $this->sendEdgeCacheRequest(['urls' => implode(',', $this->normalizeUrlsForPurge($urls))]);
    }

    private function normalizeUrlsForPurge(array $urls): array
    {
        foreach ($urls as $url) {
            $urlParts = parse_url($url);
            if (empty($urlParts['path']) || $urlParts['path'] == '/') {
                continue;
            }
            $query = isset($urlParts['query']) ? '?' . $urlParts['query'] : '';
            $base = $urlParts['scheme'] . '://' . $urlParts['host'];
            if (substr($urlParts['path'], -1) == '/') {
                $urls[] = $base . substr($urlParts['path'], 0, -1) . $query;
            } else {
                $urls[] = $base . $urlParts['path'] . '/' . $query;
            }
        }
        return array_unique($urls);
    }

    private function sendEdgeCacheRequest($payload = [])
    {
        $settings = Plugin::$plugin->getSettings();

        $url = $this->cacheClearUrl;
        if (!empty(getenv('SERVD_EDGE_CACHE_CLEAR_URL'))) {
            $url = getenv('SERVD_EDGE_CACHE_CLEAR_URL');
        }

        try {
            $client = new Client();
            $client->post($url, [
                'json' => array_merge($payload, [
                    'slug' => $settings->getProjectSlug(),
                    'environment' => getenv('ENVIRONMENT'),
                    'key' => $settings->getSecurityKey()
                ])
            ]);
        } catch (GuzzleException $e) {
            throw new Exception("Failed to contact Servd edge cache clear endpoint: " . $e->getMessage());
        }
    }
}
