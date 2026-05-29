<?php

namespace servd\AssetStorage\controllers;

use Craft;
use craft\web\Controller;
use craft\helpers\Queue;
use servd\AssetStorage\StaticCache\Jobs\PurgeEdgeCachesForTagJob;
use servd\AssetStorage\StaticCache\Jobs\PurgeEdgeCachesForUrlsJob;
use servd\AssetStorage\StaticCache\Jobs\PurgeTagJob;
use servd\AssetStorage\StaticCache\Jobs\PurgeUrlsJob;
use servd\AssetStorage\StaticCache\StaticCache;
use servd\AssetStorage\StaticCache\Tags;

class StaticCacheController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

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

        if (getenv('SERVD_CACHE_ENABLED') == 'true') {
            Queue::push(new PurgeUrlsJob(['urls' => $urls]), StaticCache::purgePriority());
        }

        if (getenv('SERVD_EDGE_CACHING') == 'true') {
            Queue::push(new PurgeEdgeCachesForUrlsJob(['urls' => $urls]), StaticCache::purgePriority());
        }

        Craft::$app->getSession()->setNotice('Cache clear job created');

        return $this->redirect($req->getReferrer());
    }

    public function actionPurgeTag()
    {
        $this->requireCpRequest();
        $req = Craft::$app->getRequest();

        if (!empty($req->get('productId'))) {
            $products = Craft::$app->plugins->getPlugin('commerce')->products;
            $product = $products->getProductById($req->get('productId'));
            if (is_null($product)) {
                return $this->redirect($req->getReferrer());
            }
            $tag = Tags::ELEMENT_ID_PREFIX . $product->getId();
        } else {
            $entries = Craft::$app->entries;
            $entry = $entries->getEntryById($req->get('entryId'));
            if (is_null($entry)) {
                return $this->redirect($req->getReferrer());
            }
            $tag = Tags::ELEMENT_ID_PREFIX . $entry->getId();
        }

        if (getenv('SERVD_CACHE_ENABLED') == 'true') {
            Queue::push(new PurgeTagJob(['tag' => $tag]), StaticCache::purgePriority());
        }

        if (getenv('SERVD_EDGE_CACHING') == 'true') {
            Queue::push(new PurgeEdgeCachesForTagJob(['tag' => $tag]), StaticCache::purgePriority());
        }

        Craft::$app->getSession()->setNotice('Cache clear job created');

        return $this->redirect($req->getReferrer());
    }
}
