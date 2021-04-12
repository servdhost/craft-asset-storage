<?php

namespace servd\AssetStorage\StaticCache\Twig;

use servd\AssetStorage\StaticCache\Twig\DynamicNode;
use Twig\Parser;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

class DynamicTokenParser extends AbstractTokenParser
{
    /**
     * @return string
     */
    public function getTag(): string
    {
        return 'dynamic';
    }

    /**
     * @inheritdoc
     */
    public function parse(Token $token)
    {
        $lineno = $token->getLine();
        /* @var Parser $parser */
        $parser = $this->parser;
        $stream = $parser->getStream();

        $nodes = [];
        $attributes = [
            'template' => ''
        ];

        //if ($stream->test(Token::NAME_TYPE, 'using')) {
        //$stream->next();
        //$stream->expect(Token::NAME_TYPE, 'key');
        $attributes['template'] = $stream->expect(Token::STRING_TYPE)->getValue();
        //}

        $stream->expect(Token::BLOCK_END_TYPE);
        // $nodes['body'] = $parser->subparse([
        //     $this,
        //     'decideDynamicEnd',
        // ], true);
        // $stream->expect(Token::BLOCK_END_TYPE);

        return new DynamicNode($nodes, $attributes, $lineno, $this->getTag());
    }

    /**
     * @param Token $token
     * @return bool
     */
    public function decideDynamicEnd(Token $token): bool
    {
        return $token->test('enddynamic');
    }
}
