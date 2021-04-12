<?php

namespace servd\AssetStorage\StaticCache\Twig;

use Craft;
use craft\helpers\StringHelper;
use Twig\Compiler;
use Twig\Node\Node;

class DynamicNode extends Node
{
    /**
     * @var int
     */
    private static $_nodeCount = 1;

    /**
     * @inheritdoc
     */
    public function compile(Compiler $compiler)
    {
        $n = self::$_nodeCount++;
        $template = $this->hasAttribute('template') ? $this->getAttribute('template') : null;
        $request = Craft::$app->getRequest();

        //$compiler->render($template);
        $compiler
            ->write('$this->loadTemplate(')
            ->subcompile($this->getNode('expr'))
            ->raw(', ')
            ->repr($this->getTemplateName())
            ->raw(', ')
            ->repr($this->getTemplateLine())
            ->raw(')');

        //Collect the body node tree and store it to be processed later by the dynamic request
        //TODO we'll need to store the request info too, and maybe the surrounding context 
        //(if a var is set in the twig above the dynamic we'll need to access it)

        //$body = $this->getNode('body');

        // $cacheKeyForBodyNode = 'servd-dy-' . $key;

        // $cacheService = Craft::$app->getCache();
        // $cacheService->set($cacheKeyForBodyNode, serialize($body), 3000); //TODO duration

        // //Insert some JS to make the dynamic call
        // $compiler->write("echo '<div class=\"servd-dynamic-content\" data-key=");
        // $compiler->write($key);
        // $compiler->write("></div>';");


        //            ->subcompile($this->getNode('body'))

    }
}
