<?php

namespace servd\AssetStorage\controllers;

use Craft;
use craft\web\Controller;
use servd\AssetStorage\AssetsPlatform\Jobs\AssetCacheClearJob;
use servd\AssetStorage\Plugin;
use servd\AssetStorage\StaticCache\Jobs\PurgeUrlsJob;
use servd\AssetStorage\StaticCache\Tags;

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

    public function actionPurgeTag()
    {
        $req = Craft::$app->getRequest();

        $entries = Craft::$app->entries;
        $entry = $entries->getEntryById($req->get('entryId'));
        if (is_null($entry)) {
            $this->redirect($req->getReferrer());
        }

        $tags = Plugin::$plugin->get('tags');

        $tag = Tags::ELEMENT_ID_PREFIX . $entry->getId();
        $allUrls = $tags->getUrlsForTag($tag);

        Craft::$app->queue->push(new PurgeUrlsJob([
            'description' => 'Purge static cache',
            'urls' => $allUrls,
        ]));

        Craft::$app->getSession()->setNotice('Cache clear job created');

        $this->redirect($req->getReferrer());
    }
}
