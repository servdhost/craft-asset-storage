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

        $totalLength = sizeof($this->urls);
        foreach ($this->urls as $i => $url) {
            echo "Purging static cache for URL: " . $url;
            $this->setProgress($queue, $i / $totalLength);
            //Perform the purge
            try {
                Ledge::purgeUrl($url);
                $tags->clearTagsForUrl($url);
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
