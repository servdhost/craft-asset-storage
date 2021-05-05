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
        $this->requireCpRequest();
        $req = Craft::$app->getRequest();

        $entries = Craft::$app->entries;
        $sites = Craft::$app->sites;
        $urls = [];

        foreach ($sites->allSiteIds as $siteId) {
            $siteEntry = $entries->getEntryById($req->get('entryId'), $siteId);
            if (!is_null($siteEntry)) {
                $urls[] = $siteEntry->getUrl();
            }
        }

        if (sizeof($urls) == 0) {
            return $this->redirect($req->getReferrer());
        }

        Craft::$app->queue->push(new PurgeUrlsJob([
            'description' => 'Purge static cache',
            'urls' => $urls,
        ]));

        Craft::$app->getSession()->setNotice('Cache clear job created');

        return $this->redirect($req->getReferrer());
    }

    public function actionPurgeTag()
    {
        $this->requireCpRequest();
        $req = Craft::$app->getRequest();

        $entries = Craft::$app->entries;
        $entry = $entries->getEntryById($req->get('entryId'));
        if (is_null($entry)) {
            return $this->redirect($req->getReferrer());
        }

        $tags = Plugin::$plugin->get('tags');

        $tag = Tags::ELEMENT_ID_PREFIX . $entry->getId();
        $allUrls = $tags->getUrlsForTag($tag);

        Craft::$app->queue->push(new PurgeUrlsJob([
            'description' => 'Purge static cache',
            'urls' => $allUrls,
        ]));

        Craft::$app->getSession()->setNotice('Cache clear job created');

        return $this->redirect($req->getReferrer());
    }
}
