<?php

namespace servd\AssetStorage\StaticCache;

use Craft;
use craft\base\Component;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Redis;

class Tags extends Component
{
    const TAG_PREFIX = 'svd-tag-';
    const URL_PREFIX = 'svd-url-';
    const SECTION_ID_PREFIX = 'sec';
    const ELEMENT_ID_PREFIX = 'el';
    const GLOBAL_SET_PREFIX = 'gs';
    const STRUCTURE_ID_PREFIX = 'st';
    const VOLUME_ID_PREFIX = 'vo';

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
        $urlMd5 = md5($url);

        $uniqueTags = $this->getAllTagsForCurrentRequest();

        try {
            $redis = $this->getRedisConnection();
            $redisBatch = $redis->multi(Redis::PIPELINE);
            foreach ($uniqueTags as $tag) {
                $redisBatch->sAdd(static::TAG_PREFIX . $tag, $url);
            }
            $redisBatch->sAddArray(static::URL_PREFIX . $urlMd5, $uniqueTags);
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
            $urls = $redis->sMembers(static::TAG_PREFIX . $tag);
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
                $callback($arr_mems);
            }
        } catch (Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }
    }

    public function clearTagsForUrl($url)
    {
        $url = $this->normaliseUrl($url);
        $urlMd5 = md5($url);
        try {
            $redis = $this->getRedisConnection();
            //Get all tags for the url
            $tags = $redis->sMembers(static::URL_PREFIX . $urlMd5);
            $redisBatch = $redis->multi(Redis::PIPELINE);
            foreach ($tags as $tag) {
                //Clear the tag -> url association
                $redisBatch->sRem(static::TAG_PREFIX . $tag, $url);
            }
            //Clear the url -> tags associations
            $redisBatch->unlink(static::URL_PREFIX . $urlMd5);
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