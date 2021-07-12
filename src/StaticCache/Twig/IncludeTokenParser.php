<?php

namespace servd\AssetStorage\StaticCache\Twig;

use Twig\Node\Node;
use Twig\Node\PrintNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Includes a template dynamically.
 *
 *   {% dynamicInclude 'loginState.html' %}
 *
 */
class IncludeTokenParser extends AbstractTokenParser
{

    public function parse(Token $token): Node
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();

        list($variables, $only, $ignoreMissing, $defaultBody) = $this->parseArguments($token);

        return new IncludeNode($expr, $variables, $only, $ignoreMissing, $defaultBody, $token->getLine(), $this->getTag());
    }

    protected function parseArguments($token)
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

        $defaultBody = null;
        // Check if the next token is 'placeholder'
        if ($stream->nextIf(/* Token::NAME_TYPE */5, 'placeholder')) {
            //If so we want to subparse the remaining temaplate to find an end tag and capture
            //everything inbetween

            $stream->expect(/* Token::BLOCK_END_TYPE */3);
            $defaultBody = $this->parser->subparse([$this, 'decideDynamicEnd'], true);
            $stream->expect(/* Token::BLOCK_END_TYPE */3);
        } else {
            // No 'placeholder' so we just expect the tag to be closed
            $stream->expect(/* Token::BLOCK_END_TYPE */3);
        }

        return [$variables, $only, $ignoreMissing, $defaultBody];
    }

    public function getTag(): string
    {
        return 'dynamicInclude';
    }

    public function decideDynamicEnd(Token $token): bool
    {
        return $token->test('endDynamicInclude');
    }
}
