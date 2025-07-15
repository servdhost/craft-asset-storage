<?php

namespace servd\AssetStorage\StaticCache\Jobs;

use craft\queue\BaseJob;

class PurgeTagJob extends BaseJob
{
    use TagPurger;

    public $tag;
    public $triggers;

    public function execute($queue): void
    {
        $this->purgeUrlsForTag($this->tag);
        
        if (getenv('SERVD_EDGE_CACHING') == 'true') {
            $this->clearEdgeCache([$this->tag]);
        }
    }

    protected function defaultDescription(): ?string
    {
        return 'Purge static cache by tag';
    }
}
