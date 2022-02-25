<?php

namespace servd\AssetStorage\controllers;

use Craft;
use craft\web\Controller;
use servd\AssetStorage\AssetsPlatform\Jobs\AssetCacheClearJob;
use servd\AssetStorage\Plugin;
use servd\AssetStorage\StaticCache\Jobs\PurgeTagJob;
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
            if (!empty($req->get('productId'))) {
                $products = Craft::$app->plugins->getPlugin('commerce')->products;
                $siteProduct = $products->getProductById($req->get('productId'), $siteId);
                if (!is_null($siteProduct)) {
                    $urls[] = $siteProduct->getUrl();
                }
            } else {
                $siteEntry = $entries->getEntryById($req->get('entryId'), $siteId);
                if (!is_null($siteEntry)) {
                    $urls[] = $siteEntry->getUrl();
                }
            }
        }

        if (sizeof($urls) == 0) {
            return $this->redirect($req->getReferrer());
        }

        \craft\helpers\Queue::push(new PurgeUrlsJob([
            'description' => 'Purge static cache by url',
            'urls' => $urls,
        ]), 1025);

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

        $tag = Tags::ELEMENT_ID_PREFIX . $entry->getId();

        \craft\helpers\Queue::push(new PurgeTagJob([
            'description' => 'Purge static cache by tag',
            'tag' => $tag
        ]), 1025);

        Craft::$app->getSession()->setNotice('Cache clear job created');

        return $this->redirect($req->getReferrer());
    }
}
