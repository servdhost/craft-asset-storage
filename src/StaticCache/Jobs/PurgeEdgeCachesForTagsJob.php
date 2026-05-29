<?php

namespace servd\AssetStorage\StaticCache\Jobs;

use craft\queue\BaseJob;

class PurgeEdgeCachesForTagsJob extends BaseJob
{
    use Purger;

    public $tags;

    public function execute($queue): void
    {
        $this->purgeEdgeCacheForTags($this->tags);            
    }

    protected function defaultDescription(): ?string
    {
        return 'Purge Servd static edge caches for tags';
    }
}
