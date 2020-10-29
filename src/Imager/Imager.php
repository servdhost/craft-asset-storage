<?php

namespace servd\AssetStorage\Imager;

use Craft;
use craft\base\Component;
use yii\base\Event;

class Imager extends Component
{

    public function init()
    {
        if (!class_exists('\spacecatninja\imagerx\ImagerX')) {
            return;
        }
        $this->registerListeners();
    }

    public function registerListeners()
    {
        Event::on(
            \spacecatninja\imagerx\ImagerX::class,
            \spacecatninja\imagerx\ImagerX::EVENT_REGISTER_EXTERNAL_STORAGES,
            static function (\spacecatninja\imagerx\events\RegisterExternalStoragesEvent $event) {
                $event->storages['servd'] = ImagerStorage::class;
            }
        );

        Event::on(
            \spacecatninja\imagerx\ImagerX::class,
            \spacecatninja\imagerx\ImagerX::EVENT_REGISTER_TRANSFORMERS,
            static function (\spacecatninja\imagerx\events\RegisterTransformersEvent $event) {
                $event->transformers['servd'] = ImagerTransformer::class;
            }
        );
    }
}
