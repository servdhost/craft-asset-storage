<?php

namespace servd\AssetStorage\controllers;

use Craft;
use craft\web\Controller;
use servd\AssetStorage\AssetsPlatform\Jobs\AssetCacheClearJob;
use servd\AssetStorage\StaticCache\Jobs\PurgeUrlsJob;

class StaticCacheController extends Controller
{
    protected $allowAnonymous = false;

    public function actionPurgeCache()
    {
        $req = Craft::$app->getRequest();

        $entries = Craft::$app->entries;
        $entry = $entries->getEntryById($req->get('entryId'));
        if (is_null($entry)) {
            $this->redirect($req->getReferrer());
        }

        $entryUrl = $entry->getUrl();

        Craft::$app->queue->push(new PurgeUrlsJob([
            'description' => 'Purge static cache',
            'urls' => [$entryUrl],
        ]));

        Craft::$app->getSession()->setNotice('Cache clear job created');

        $this->redirect($req->getReferrer());
    }
}
