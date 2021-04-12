<?php

namespace servd\AssetStorage\StaticCache\Twig;

use craft\web\twig\nodevisitors\EventTagAdder;
use craft\web\twig\nodevisitors\EventTagFinder;
use craft\web\twig\nodevisitors\GetAttrAdjuster;
use craft\web\twig\nodevisitors\Profiler;
use craft\web\View;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\Environment as TwigEnvironment;

class Extension extends AbstractExtension //implements GlobalsInterface
{
    /**
     * @inheritdoc
     */
    public function getTokenParsers(): array
    {
        return [
            new DynamicTokenParser()
        ];
    }
}
