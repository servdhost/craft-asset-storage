<?php

namespace servd\AssetStorage\RedisDebug;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use yii\log\Target;
use Opis\Closure;
use yii\debug\FlattenException;

class RedisLogTarget extends Target
{

    const REDIS_DATA_TTL = 60 * 60 * 24;

    /**
     * @var Module
     */
    public $module;
    /**
     * @var string
     */
    public $tag;

    private $redisCon;

    /**
     * @param \yii\debug\Module $module
     * @param array $config
     */
    public function __construct($module, $config = [])
    {
        parent::__construct($config);
        $this->module = $module;
        $this->tag = uniqid();

        $redisDb = intval(getenv('REDIS_DB'));
        $redis = new \Redis();
        $redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'));
        $redis->select($redisDb);
        $this->redisCon = $redis;
    }

    public function __destruct()
    {
        $this->redisCon->close();
        $this->redisCon = null;
    }

    /**
     * Exports log messages to a specific destination.
     * Child classes must implement this method.
     * @throws \yii\base\Exception
     */
    public function export()
    {
        $summary = $this->collectSummary();
        $redisKey = 'debugbar-tag-' . $this->tag;

        $data = [];
        $exceptions = [];
        foreach ($this->module->panels as $id => $panel) {
            try {
                $panelData = $panel->save();
                if ($id === 'profiling') {
                    $summary['peakMemory'] = $panelData['memory'];
                    $summary['processingTime'] = $panelData['time'];
                }
                $data[$id] = Closure\serialize($panelData);
            } catch (\Exception $exception) {
                $exceptions[$id] = new FlattenException($exception);
            }
        }
        $data['summary'] = $summary;
        $data['exceptions'] = $exceptions;

        $this->redisCon->set($redisKey, Closure\serialize($data), static::REDIS_DATA_TTL);
        $this->updateIndexFile($summary);
    }

    /**
     * Updates index file with summary log data
     *
     * @param array $summary summary log data
     * @throws \yii\base\InvalidConfigException
     */
    private function updateIndexFile($summary)
    {
        $redisKey = 'debugbar-manifest';
        $content = $this->redisCon->get($redisKey);

        if ($content === false) {
            $manifest = [];
        } else {
            $manifest = Closure\unserialize($content);
        }

        $manifest[$this->tag] = $summary;
        $this->gc($manifest);

        $this->redisCon->set($redisKey, Closure\serialize($manifest));
    }

    /**
     * Processes the given log messages.
     * This method will filter the given messages with [[levels]] and [[categories]].
     * And if requested, it will also export the filtering result to specific medium (e.g. email).
     * @param array $messages log messages to be processed. See [[\yii\log\Logger::messages]] for the structure
     * of each message.
     * @param bool $final whether this method is called at the end of the current application
     * @throws \yii\base\Exception
     */
    public function collect($messages, $final)
    {
        $this->messages = array_merge($this->messages, $messages);
        if ($final) {
            $this->export();
        }
    }

    /**
     * Removes obsolete data files
     * @param array $manifest
     */
    protected function gc(&$manifest)
    {
        if (count($manifest) > $this->module->historySize + 10) {
            $n = count($manifest) - $this->module->historySize;
            foreach (array_keys($manifest) as $tag) {
                $redisKey = 'debugbar-tag-' . $tag;
                $this->redisCon->del($redisKey);
                unset($manifest[$tag]);
                if (--$n <= 0) {
                    break;
                }
            }
            $this->removeStaleDataFiles($manifest);
        }
    }

    /**
     * Remove staled data files i.e. files that are not in the current index file
     * (may happen because of corrupted or rotated index file)
     *
     * @param array $manifest
     * @since 2.0.11
     */
    protected function removeStaleDataFiles($manifest)
    {
        //NOTE: No longer needed - redis TTL will clean up old request data
    }

    /**
     * Collects summary data of current request.
     * @return array
     */
    protected function collectSummary()
    {
        if (Yii::$app === null) {
            return [];
        }

        $request = Yii::$app->getRequest();
        $response = Yii::$app->getResponse();
        $summary = [
            'tag' => $this->tag,
            'url' => $request->getAbsoluteUrl(),
            'ajax' => (int) $request->getIsAjax(),
            'method' => $request->getMethod(),
            'ip' => $request->getUserIP(),
            'time' => $_SERVER['REQUEST_TIME_FLOAT'],
            'statusCode' => $response->statusCode,
            'sqlCount' => $this->getSqlTotalCount(),
        ];

        if (isset($this->module->panels['mail'])) {
            $mailFiles = $this->module->panels['mail']->getMessagesFileName();
            $summary['mailCount'] = count($mailFiles);
            $summary['mailFiles'] = $mailFiles;
        }

        return $summary;
    }

    /**
     * Returns total sql count executed in current request. If database panel is not configured
     * returns 0.
     * @return int
     */
    protected function getSqlTotalCount()
    {
        if (!isset($this->module->panels['db'])) {
            return 0;
        }
        $profileLogs = $this->module->panels['db']->getProfileLogs();

        # / 2 because messages are in couple (begin/end)

        return count($profileLogs) / 2;
    }
}
