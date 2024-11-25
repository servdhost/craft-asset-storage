<?php

namespace servd\AssetStorage\StaticCache;

use Craft;
use craft\base\Component;
use Exception;
use Redis;

class Tags extends Component
{
    const URL_HASH_LOOKUP = 'svd-url-lookup';
    const TAG_PREFIX = 'svd-t-';
    const URL_PREFIX = 'svd-u-';
    const SECTION_ID_PREFIX = 'sec';
    const ELEMENT_ID_PREFIX = 'el';
    const GLOBAL_SET_PREFIX = 'gs';
    const STRUCTURE_ID_PREFIX = 'st';
    const VOLUME_ID_PREFIX = 'vo';
    const SHORT_HASH_LENGTH = 10;

    const IGNORE_TAGS_FROM_CLASSES = [
        'craft\elements\MatrixBlock',
        'craft\elements\User'
    ];

    private static $redis = null;

    public $tags = [];

    public function addTagForCurrentRequest($tag)
    {
        $this->tags[] = $tag;
    }

    public function getAllTagsForCurrentRequest()
    {
        return array_unique($this->tags);
    }

    public function associateCurrentRequestTagsWithUrl($url)
    {
        Craft::beginProfile('Tags::associateCurrentRequestTagsWithUrl', __METHOD__);
        $url = $this->normaliseUrl($url);
        $urlHash = substr(md5($url), 0, static::SHORT_HASH_LENGTH);

        $uniqueTags = $this->getAllTagsForCurrentRequest();

        try {
            $redis = $this->getRedisConnection();
            $redisBatch = $redis->multi(Redis::PIPELINE);
            $redis->hSet(static::URL_HASH_LOOKUP, $urlHash, $url);

            foreach ($uniqueTags as $tag) {
                $redisBatch->sAdd(static::TAG_PREFIX . $tag, $urlHash);
            }
            $redisBatch->sAddArray(static::URL_PREFIX . $urlHash, $uniqueTags);
            $redisBatch->exec();
        } catch (Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }
        Craft::endProfile('Tags::associateCurrentRequestTagsWithUrl', __METHOD__);
        return $uniqueTags;
    }

    public function getUrlsForTag($tag)
    {
        try {
            $redis = $this->getRedisConnection();
            $urlHashes = $redis->sMembers(static::TAG_PREFIX . $tag);
            $urls = [];
            foreach ($urlHashes as $hash) {
                $urls[] = 'http://' . $redis->hGet(static::URL_HASH_LOOKUP, $hash);
            }
            return $urls;
        } catch (Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }
    }

    public function iterateUrlsForTag($tag, $callback)
    {
        try {
            $redis = $this->getRedisConnection();
            $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY); /* don't return empty results until we're done */
            $totalSetSize = $redis->scard(static::TAG_PREFIX . $tag);

            $counter = 0;
            $it = NULL;
            while (($arr_mems = $redis->sScan(static::TAG_PREFIX . $tag, $it)) && $counter < $totalSetSize) {
                $counter += sizeof($arr_mems);
                $callback(array_map(function($hash) use ($redis) {
                    return 'http://' . $redis->hGet(static::URL_HASH_LOOKUP, $hash);
                }, $arr_mems));
            }
        } catch (Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }
    }

    public function clearTagsForUrl($url)
    {
        $url = $this->normaliseUrl($url);
        $urlHash = substr(md5($url), 0, static::SHORT_HASH_LENGTH);

        try {
            $redis = $this->getRedisConnection();
            //Get all tags for the url
            $tags = $redis->sMembers(static::URL_PREFIX . $urlHash);
            $redisBatch = $redis->multi(Redis::PIPELINE);
            foreach ($tags as $tag) {
                //Clear the tag -> url association
                $redisBatch->sRem(static::TAG_PREFIX . $tag, $urlHash);
            }
            //Clear the url -> tags associations
            $redisBatch->unlink(static::URL_PREFIX . $urlHash);
            $redisBatch->hDel(static::URL_HASH_LOOKUP, $urlHash);
            $redisBatch->exec();
        } catch (Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }
    }

    private function getRedisConnection()
    {
        if (static::$redis === null) {
            $redisDb = intval(getenv('REDIS_STATIC_CACHE_DB'));
            static::$redis = new Redis();
            static::$redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'));
            static::$redis->select($redisDb);
        }
        return static::$redis;
    }

    private function normaliseUrl($url)
    {
        $url = str_ireplace('https://', '', $url);
        return str_ireplace('http://', '', $url);
    }
}
