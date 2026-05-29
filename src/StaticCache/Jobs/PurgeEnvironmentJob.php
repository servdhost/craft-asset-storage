<?php

namespace servd\AssetStorage\StaticCache\Jobs;

use Craft;
use Exception;
use Redis;
use craft\queue\BaseJob;

class PurgeEnvironmentJob extends BaseJob
{
    use Purger;

    public function execute($queue): void
    {
        try {
            $redisDb = intval(getenv('REDIS_STATIC_CACHE_DB'));
            $redis = new Redis();

            //Clear out content
            $redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'), 5);
            $redis->select($redisDb);
            $redis->flushDb(true);
            $redis->close();
        } catch (Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }

        try {
            //Clear out metadata - ledge stores cached redirects here
            $qlessHost = str_ireplace('-redis.', '-redis-qless.', getenv('REDIS_HOST'));
            $redis->connect($qlessHost, getenv('REDIS_PORT'), 5);
            $redis->select($redisDb);
            $redis->flushDb(true);
            $redis->close();
        } catch (Exception $e) {
            //Do nothing - this is expected most of the time
        }
    }

    protected function defaultDescription(): ?string
    {
        return 'Purge Servd static origin cache';
    }
}
