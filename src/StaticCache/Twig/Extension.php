<?php

namespace servd\AssetStorage\StaticCache\Twig;

use Twig\Extension\AbstractExtension;

class Extension extends AbstractExtension
{
    /**
     * @inheritdoc
     */
    public function getTokenParsers(): array
    {
        return [
            new IncludeTokenParser()
        ];
    }
}
