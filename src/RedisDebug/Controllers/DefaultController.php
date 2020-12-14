<?php

namespace servd\AssetStorage\RedisDebug\Controllers;

use yii\debug\controllers\DefaultController as ControllersDefaultController;
use Opis\Closure;
use yii\web\NotFoundHttpException;

class DefaultController extends ControllersDefaultController
{

    private $redisCon;
    private $_manifest;

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);

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
     * @param bool $forceReload
     * @return array
     */
    protected function getManifest($forceReload = false)
    {
        if ($this->_manifest === null || $forceReload) {

            $redisKey = 'debugbar-manifest';
            $content = $this->redisCon->get($redisKey);

            if ($content === false) {
                $this->_manifest = [];
            }

            $this->_manifest = array_reverse(Closure\unserialize($content), true);
        }

        return $this->_manifest;
    }

    /**
     * @param string $tag debug data tag.
     * @param int $maxRetry maximum numbers of tag retrieval attempts.
     * @throws NotFoundHttpException if specified tag not found.
     */
    public function loadData($tag, $maxRetry = 0)
    {
        // retry loading debug data because the debug data is logged in shutdown function
        // which may be delayed in some environment if xdebug is enabled.
        // See: https://github.com/yiisoft/yii2/issues/1504
        for ($retry = 0; $retry <= $maxRetry; ++$retry) {
            $manifest = $this->getManifest($retry > 0);
            if (isset($manifest[$tag])) {
                $redisKey = 'debugbar-tag-' . $tag;
                //$dataFile = $this->module->dataPath . "/$tag.data";
                $d = $this->redisCon->get($redisKey);
                if ($d === false) {
                    throw new NotFoundHttpException("Unable to find debug data tagged with '$tag'.");
                }

                $data = Closure\unserialize($d);
                $exceptions = $data['exceptions'];
                foreach ($this->module->panels as $id => $panel) {
                    if (isset($data[$id])) {
                        $panel->tag = $tag;
                        $panel->load(Closure\unserialize($data[$id]));
                    }
                    if (isset($exceptions[$id])) {
                        $panel->setError($exceptions[$id]);
                    }
                }
                $this->summary = $data['summary'];
                return;
            }
            sleep(1);
        }

        throw new NotFoundHttpException("Unable to find debug data tagged with '$tag'.");
    }
}
