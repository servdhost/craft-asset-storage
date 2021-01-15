<?php

namespace servd\AssetStorage\controllers;

use Craft;
use craft\web\Controller;
use servd\AssetStorage\AssetsPlatform\Jobs\AssetCacheClearJob;

class AssetsPlatformController extends Controller
{
    protected $allowAnonymous = false;

    public function actionClearCache()
    {
        $req = Craft::$app->getRequest();

        $elements = Craft::$app->elements;
        $element = $elements->getElementById($req->get('elementUid'));
        $element->markAsDirty();
        $elements->saveElement($element);

        Craft::$app->queue->push(new AssetCacheClearJob([
            'description' => 'Clear cache for asset',
            'elementUid' => $req->get('elementUid'),
        ]));

        Craft::$app->getSession()->setNotice('Cache clear job created');

        $this->redirect($req->getReferrer());
    }
}
