<?php

namespace servd\AssetStorage\StaticCache\Jobs;

use craft\queue\BaseJob;

class PurgeEdgeCachesForTagJob extends BaseJob
{
    use Purger;

    public $tag;

    public function execute($queue): void
    {
        $this->purgeEdgeCacheForTags([$this->tag]);
    }

    protected function defaultDescription(): ?string
    {
        return 'Purge Servd static edge caches for tag';
    }
}
