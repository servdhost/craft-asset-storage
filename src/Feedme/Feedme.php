<?php

namespace servd\AssetStorage\Feedme;

use Craft;
use craft\base\Component;
use yii\base\Application as BaseApplication;
use yii\base\Event;
use servd\AssetStorage\Plugin;

class Feedme extends Component
{
    public function init()
    {

        $settings = Plugin::$plugin->getSettings();
        if ($settings->adjustFeedme) {
            $this->registerListeners();
        }
    }

    public function registerListeners()
    {
        Event::on(
            BaseApplication::class,
            BaseApplication::EVENT_BEFORE_REQUEST,
            function ($event) {
                /** @var \craft\debug\Module $debugModule */
                $feedmePlugin = Craft::$app->getPlugin('feed-me');
                if (empty($feedmePlugin)) {
                    return;
                }

                var_dump('loaded');
                exit;
                // Replace the default debug bar implementation's log storage
                //$debugModule->logTarget = Craft::$app->getLog()->targets['debug'] = new RedisLogTarget($debugModule);
                // Shim the controllers with custom ones (because the log target's implementation has leaked into them)
                //$debugModule->controllerNamespace = 'servd\AssetStorage\RedisDebug\Controllers';
            }
        );
    }
}
