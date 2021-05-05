<?php

namespace servd\AssetStorage\StaticCache\Twig;

use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Includes a template dynamically.
 *
 *   {% include 'header.html' %}
 *     Body
 *   {% include 'footer.html' %}
 *
 */
class IncludeTokenParser extends AbstractTokenParser
{

    public function parse(Token $token): Node
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();

        list($variables, $only, $ignoreMissing) = $this->parseArguments();

        return new IncludeNode($expr, $variables, $only, $ignoreMissing, $token->getLine(), $this->getTag());
    }

    protected function parseArguments()
    {
        $stream = $this->parser->getStream();

        $ignoreMissing = false;
        if ($stream->nextIf(/* Token::NAME_TYPE */5, 'ignore')) {
            $stream->expect(/* Token::NAME_TYPE */5, 'missing');

            $ignoreMissing = true;
        }

        $variables = null;
        if ($stream->nextIf(/* Token::NAME_TYPE */5, 'with')) {
            $variables = $this->parser->getExpressionParser()->parseExpression();
        }

        $only = false;
        if ($stream->nextIf(/* Token::NAME_TYPE */5, 'only')) {
            $only = true;
        }

        $stream->expect(/* Token::BLOCK_END_TYPE */3);

        return [$variables, $only, $ignoreMissing];
    }

    public function getTag(): string
    {
        return 'dynamicInclude';
    }
}
