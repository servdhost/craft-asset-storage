<?php

namespace servd\AssetStorage\StaticCache\Jobs;

use Exception;
use craft\queue\BaseJob;

class PurgeEdgeCachesForUrlsJob extends BaseJob
{
    use Purger;

    public $urls;

    public function execute($queue): void
    {
        $chunks = array_chunk($this->urls, 50);
        $totalLength = sizeof($chunks);

        foreach ($chunks as $i => $chunk) {
            $this->setProgress($queue, $i / $totalLength);
            try {
                $this->purgeEdgeCacheForUrls($chunk);
            } catch (Exception $e) {
                throw new Exception("Failed to purge all urls: " . $e->getMessage());
            }
        }
    }

    protected function defaultDescription(): ?string
    {
        return 'Purge Servd static edge caches for URLs';
    }
}
