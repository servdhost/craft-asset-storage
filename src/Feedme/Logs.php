<?php

namespace servd\AssetStorage\Feedme;

use Craft;
use craft\base\Component;
use craft\feedme\Plugin;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use DateTime;

class Logs extends \craft\feedme\services\Logs
{
    private $redisCon;
    private $redisLogsKey;
    private $maxLogLines;
    private $maxLogRetentionSeconds;

    public function init()
    {
        parent::init();
        $redisDb = intval(getenv('REDIS_DB'));
        $redis = new \Redis();
        $redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'));
        $redis->select($redisDb);
        $this->redisCon = $redis;
        $this->redisLogsKey = 'feedme-logs';
        $this->maxLogLines = 4000;
        $this->maxLogRetentionSeconds = 3600 * 24 * 7 ;
    }

    public function log($method, $message, $params = [], $options = [])
    {
        $dateTime = new DateTime();
        $type = explode('::', $method)[1];
        $message = Craft::t('feed-me', $message, $params);

        if (!$this->_canLog($type)) {
            return;
        }

        if (Plugin::$feedName) {
            $message = Plugin::$feedName . ': ' . $message;
        }

        $options = array_merge([
            'date' => $dateTime->format('Y-m-d H:i:s'),
            'type' => $type,
            'message' => $message,
        ], $options);

        // If we're not explicitly sending a key for logging, check if we've started a feed.
        // If we have, our $stepKey variable will have a value and can use it here.
        if (!isset($options['key']) && Plugin::$stepKey) {
            $options['key'] = Plugin::$stepKey;
        }

        $options = Json::encode($options);

        //Push into a redis set with an associated timestamp
        $microtime = floor(microtime(true)*1000000);

        $this->redisCon->zadd($this->redisLogsKey, $microtime, $options);

        //Every 10 logs, we'll prune the set to keep it from growing too large
        if (mt_rand(0,100) <= 10) {
            $this->cleanLogs();
        }
    }

    private function cleanLogs() 
    {
        //Only keep a max of $this->maxLogLines lines
        $this->redisCon->zremrangebyrank($this->redisLogsKey, 0, -$this->maxLogLines);

        //Remove anything older than maxLogRetentionSeconds
        $timeNow = time();
        $deleteBefore = $timeNow - $this->maxLogRetentionSeconds;
        $deleteBeforeMicro = $deleteBefore . '000000';

        $this->redisCon->zremrangebyscore($this->redisLogsKey, '-inf', $deleteBeforeMicro);
    }

    public function getLogEntries($type = null)
    {
        $logEntries = [];

        //Just get everything from the redis set
        $allLines = $this->redisCon->zrange($this->redisLogsKey, 0, -1);

        foreach ($allLines as $line) {
            $json = Json::decode($line);

            if (!$json) {
                continue;
            }

            if ($type && $json['type'] !== $type) {
                continue;
            }

            if (isset($json['date'])) {
                $json['date'] = DateTime::createFromFormat('Y-m-d H:i:s', $json['date'])->format('Y-m-d H:i:s');
            }

            // Backward compatibility
            $key = $json['key'] ?? count($logEntries);

            if (isset($logEntries[$key])) {
                $logEntries[$key]['items'][] = $json;
            } else {
                $logEntries[$key] = $json;
            }
        }

        $logEntries = array_reverse($logEntries);

        return $logEntries;
    }


    private function _canLog($type)
    {
        $logging = Plugin::$plugin->service->getConfig('logging');

        // If logging set to false, don't log anything
        if ($logging === false) {
            return false;
        }

        if ($type === 'info' && $logging === 'error') {
            return false;
        }

        return true;
    }

    public function clear()
    {
        $this->redisCon->zremrangebyrank($this->redisLogsKey, 0, -1);
    }
}
