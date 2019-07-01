<?php

namespace servd\AssetStorage;

use craft\base\Element;
use craft\elements\Asset;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Volumes;
use yii\base\Event;

class Plugin extends \craft\base\Plugin
{
    public $schemaVersion = '1.0';

    public function init()
    {
        parent::init();

        Event::on(Volumes::class, Volumes::EVENT_REGISTER_VOLUME_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Volume::class;
        });
    }
}
