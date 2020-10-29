<?php

namespace servd\AssetStorage\ImageOptimize;

use Craft;
use nystudio107\imageoptimize\services\Optimize;
use craft\events\RegisterComponentTypesEvent;
use craft\base\Component;
use yii\base\Event;

class ImageOptimize extends Component
{

    public function init()
    {
        if (!class_exists('\nystudio107\imageoptimize\ImageOptimize')) {
            return;
        }
        $this->registerListeners();
    }

    public function registerListeners()
    {
        Event::on(
            Optimize::class,
            Optimize::EVENT_REGISTER_IMAGE_TRANSFORM_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = ImageOptimizeTransformer::class;
            }
        );
    }
}
