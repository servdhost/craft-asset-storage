<?php

namespace servd\AssetStorage\StaticCache\Jobs;

use craft\queue\BaseJob;

class PurgeEdgeCachesForEnvironmentJob extends BaseJob
{
    use Purger;

    public function execute($queue): void
    {
        $this->purgeEdgeCacheForEnvironment();
    }

    protected function defaultDescription(): ?string
    {
        return 'Purge Servd static edge caches';
    }
}
