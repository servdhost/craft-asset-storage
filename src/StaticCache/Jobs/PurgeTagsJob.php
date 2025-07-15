<?php

namespace servd\AssetStorage\StaticCache\Jobs;

use craft\queue\BaseJob;

class PurgeTagsJob extends BaseJob
{
    use TagPurger;

    public $tags;
    public $triggers;

    public function execute($queue): void
    {
        $numberOfTags = sizeof($this->tags);

        for ($i = 0; $i < $numberOfTags; $i++) {
            $this->setProgress($queue, $i / $numberOfTags);
            $this->purgeUrlsForTag($this->tags[$i]);
        }
        
        if (getenv('SERVD_EDGE_CACHING') == 'true') {
            $this->clearEdgeCache($this->tags);            
        }
    }

    protected function defaultDescription(): ?string
    {
        return 'Purge static cache by tags';
    }
}
