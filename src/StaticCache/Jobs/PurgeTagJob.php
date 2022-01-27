<?php

namespace servd\AssetStorage\StaticCache\Jobs;

use Craft;
use craft\queue\BaseJob;
use Exception;
use GuzzleHttp\Client;
use servd\AssetStorage\Plugin;
use servd\AssetStorage\StaticCache\Ledge;

class PurgeTagJob extends BaseJob
{
    public $tag;
    public $triggers;

    public function execute($queue)
    {
        $tags = Plugin::$plugin->get('tags');

        $tags->iterateUrlsForTag($this->tag, function ($urls) use ($tags) {
            try {
                Ledge::purgeUrls($urls);
                foreach ($urls as $url) {
                    $tags->clearTagsForUrl($url);
                }
            } catch (\Exception $e) {
                throw new Exception("Failed to purge all urls: " . $e->getMessage());
            }
        });
    }

    protected function defaultDescription()
    {
        return 'Purge static cache by tag';
    }
}
