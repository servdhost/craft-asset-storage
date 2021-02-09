<?php

namespace servd\AssetStorage\StaticCache\Jobs;

use Craft;
use craft\queue\BaseJob;
use Exception;
use GuzzleHttp\Client;
use servd\AssetStorage\Plugin;
use servd\AssetStorage\StaticCache\Ledge;

class PurgeUrlsJob extends BaseJob
{
    public $urls;
    public $triggers;

    public function execute($queue)
    {
        $tags = Plugin::$plugin->get('tags');

        $chunks = array_chunk($this->urls, 50);
        $totalLength = sizeof($chunks);

        foreach ($chunks as $i => $chunk) {
            $this->setProgress($queue, $i / $totalLength);
            try {
                Ledge::purgeUrls($chunk);
                foreach ($chunk as $url) {
                    $tags->clearTagsForUrl($url);
                }
            } catch (\Exception $e) {
                throw new Exception("Failed to purge all urls: " . $e->getMessage());
            }
        }
    }

    protected function defaultDescription()
    {
        return 'Purge static cache';
    }
}
