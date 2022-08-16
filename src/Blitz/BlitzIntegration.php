<?php

namespace servd\AssetStorage\Blitz;

use Craft;
use craft\base\Component;
use yii\base\Event;
use putyourlightson\blitz\helpers\CachePurgerHelper;
use craft\events\RegisterComponentTypesEvent;

class BlitzIntegration extends Component
{

    public function init()
    {
        if (!class_exists('\putyourlightson\blitz\Blitz')) {
            return;
        }
        $this->registerListeners();
    }

    public function registerListeners()
    {
        Event::on(CachePurgerHelper::class, 
            CachePurgerHelper::EVENT_REGISTER_PURGER_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = CachePurger::class;
            }
        );

    }
}