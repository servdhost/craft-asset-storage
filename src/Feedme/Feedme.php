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
        if ($settings->adjustFeedmeLogs) {
            $this->registerListeners();
        }
    }

    public function registerListeners()
    {
        Event::on(
            BaseApplication::class,
            BaseApplication::EVENT_BEFORE_REQUEST,
            function ($event) {
                /** @var \craft\feedme\Plugin $feedmePlugin */
                $feedmePlugin = Craft::$app->plugins->getPlugin('feed-me');

                if (empty($feedmePlugin)) {
                    return;
                }

                //Replace the feedme log component

                $newLog = new Logs();
                $feedmePlugin->setComponents([
                    'logs' => $newLog
                ]);
            }
        );
    }
}
