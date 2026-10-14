<?php

namespace servd\AssetStorage\StaticCache\Jobs;

use craft\queue\BaseJob;
use servd\AssetStorage\Plugin;

class PurgeTagJob extends BaseJob
{
    public $tag;
    public $triggers;

    public function execute($queue): void
    {
        Plugin::$plugin->staticCache->purgeUrlsForTag($this->tag);
        
        if (getenv('SERVD_EDGE_CACHING') == 'true') {
            Plugin::$plugin->staticCache->clearEdgeCaches([$this->tag]);
        }
    }

    protected function defaultDescription(): ?string
    {
        return 'Purge static cache by tag';
    }
}
