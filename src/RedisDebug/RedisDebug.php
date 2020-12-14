<?php

namespace servd\AssetStorage\RedisDebug;

use Craft;
use craft\base\Component;
use yii\base\Application as BaseApplication;
use yii\base\Event;

class RedisDebug extends Component
{
    public function init()
    {
        if (!extension_loaded('redis')) {
            return;
        }

        if (
            empty(getenv('REDIS_HOST'))
            || empty(getenv('REDIS_PORT'))
            || empty(getenv('REDIS_DB'))
        ) {
            return;
        }

        $this->registerListeners();
    }

    public function registerListeners()
    {
        Event::on(
            BaseApplication::class,
            BaseApplication::EVENT_BEFORE_REQUEST,
            function ($event) {
                /** @var \craft\debug\Module $debugModule */
                $debugModule = Craft::$app->getModule('debug');
                if (empty($debugModule)) {
                    return;
                }
                // Replace the default debug bar implementation's log storage
                $debugModule->logTarget = Craft::$app->getLog()->targets['debug'] = new RedisLogTarget($debugModule);
                // Shim the controllers with custom ones (because the log target's implementation has leaked into them)
                $debugModule->controllerNamespace = 'servd\AssetStorage\RedisDebug\Controllers';
            }
        );
    }
}
