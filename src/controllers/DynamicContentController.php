<?php

namespace servd\AssetStorage\controllers;

use Craft;
use craft\web\Controller;
use Twig\Compiler;
use Twig\Environment;
use Twig\Node\BodyNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\TokenStream;

class DynamicContentController extends Controller
{
    protected $allowAnonymous = true;

    public function actionGetContent()
    {
        $req = Craft::$app->getRequest();

        $key = $req->getQueryParam('key');

        $cacheKeyForBodyNode = 'servd-dy-' . $key;

        $cacheService = Craft::$app->getCache();
        $cached = $cacheService->get($cacheKeyForBodyNode);
        $nodeTree = unserialize($cached);

        $source = $nodeTree->getSourceContext();
        $node = new ModuleNode(new BodyNode([$nodeTree]), null, new Node([]), new Node([]), new Node([]), [], $source);

        $twig = Craft::$app->getView()->getTwig();
        $compiled = $twig->compile($node);


        $twig->getCache(false)->write('atest', $compiled);
        $twig->getCache(false)->load('atest');


        $html = $twig->render('atest');

        return $html;
        // var_dump($compiled);
        // exit;
        // ob_start();
        // eval($compiled);
        // $content = ob_get_clean();
        //return $content;
    }
}
