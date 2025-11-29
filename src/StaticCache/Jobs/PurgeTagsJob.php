<?php

namespace servd\AssetStorage\StaticCache\Jobs;

use craft\queue\BaseJob;
use servd\AssetStorage\Plugin;

class PurgeTagsJob extends BaseJob
{
    public $tags;
    public $triggers;

    public function execute($queue): void
    {
        $numberOfTags = sizeof($this->tags);

        for ($i = 0; $i < $numberOfTags; $i++) {
            $this->setProgress($queue, $i / $numberOfTags);
            Plugin::$plugin->staticCache->purgeUrlsForTag($this->tags[$i]);
        }
        
        if (getenv('SERVD_EDGE_CACHING') == 'true') {
            Plugin::$plugin->staticCache->clearEdgeCaches($this->tags);            
        }
    }

    protected function defaultDescription(): ?string
    {
        return 'Purge static cache by tags';
    }
}
