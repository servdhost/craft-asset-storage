<?php

namespace servd\AssetStorage\Feedme;

use Craft;
use craft\base\Component;
use craft\feedme\Plugin;
use craft\helpers\App;
use craft\helpers\FileHelper;

class Logs extends \craft\feedme\services\Logs
{

    public function log($method, $message, $params = [], $options = [])
    {
        $type = explode('::', $method)[1];
        if (!$this->_canLog($type)) {
            return;
        }

        parent::log($method, $message, $params, $options);

        $message = Craft::t('feed-me', $message, $params);

        if (Plugin::$feedName) {
            $message = Plugin::$feedName . ': ' . $message;
        }

        $key = $options['key'] ?? null;
        if (!isset($options['key']) && Plugin::$stepKey) {
            $key = Plugin::$stepKey;
        }

        Craft::$type('feed-me: ' . (empty($key) ? '' : $key . ' ') . $message);
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
}
