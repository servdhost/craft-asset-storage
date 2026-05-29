<?php

namespace servd\AssetStorage\StaticCache\Jobs;

use craft\queue\BaseJob;
class PurgeTagsJob extends BaseJob
{
    use Purger;

    public $tags;

    public function execute($queue): void
    {
        $numberOfTags = sizeof($this->tags);
        for ($i = 0; $i < $numberOfTags; $i++) {
            $this->setProgress($queue, $i / $numberOfTags);
            $this->purgeUrlsForTag($this->tags[$i]);
        }
    }

    protected function defaultDescription(): ?string
    {
        return 'Purge Servd static origin cache for tags';
    }
}
