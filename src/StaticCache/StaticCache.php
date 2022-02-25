<?php

namespace servd\AssetStorage\StaticCache;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use Exception;
use Redis;
use servd\AssetStorage\Plugin;
use yii\base\ErrorException;
use yii\base\Event;
use craft\base\Element;
use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use craft\events\DeleteTemplateCachesEvent;
use craft\events\ElementEvent;
use craft\events\ElementStructureEvent;
use craft\events\MoveElementEvent;
use craft\events\PopulateElementEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\SectionEvent;
use craft\events\TemplateEvent;
use craft\helpers\ElementHelper;
use craft\services\Elements;
use craft\services\Sections;
use craft\services\Structures;
use craft\services\TemplateCaches;
use craft\utilities\ClearCaches;
use craft\web\Application;
use craft\web\UrlManager;
use craft\web\View;
use servd\AssetStorage\StaticCache\Jobs\PurgeUrlsJob;
use servd\AssetStorage\StaticCache\Jobs\PurgeTagJob;
use servd\AssetStorage\StaticCache\Twig\Extension;
use yii\base\View as BaseView;
use yii\web\View as WebView;

class StaticCache extends Component
{

    public static $esiBlocks = [];
    public static $dynamicBlocksAdded = false;

    public function init()
    {
        $this->registerTwigExtension();

        // If we aren't running on Servd, this component does nothing
        if (!extension_loaded('redis')) {
            return;
        }

        // If static caching is disabled, this component does nothing
        if (getenv('SERVD_CACHE_ENABLED') !== 'true') {
            return;
        }

        if (
            empty(getenv('REDIS_STATIC_CACHE_DB'))
            || empty(getenv('REDIS_HOST'))
            || empty(getenv('REDIS_PORT'))
        ) {
            return;
        }

        $this->registerLoggedInHandlers();
        $this->registerEventHandlers();
        $this->registerFrontendEventHandlers();
        $this->registerElementUpdateHandlers();
        $this->hookCPSidebarTemplate();
    }

    private function registerTwigExtension()
    {

        // Add in our Twig extension
        $extension = new Extension();
        Craft::$app->view->registerTwigExtension($extension);

        //Don't add JS to CP requests or CLI commands
        if (!Craft::$app->request->getIsSiteRequest()) {
            return;
        }

        Event::on(WebView::class, WebView::EVENT_END_BODY, function () {
            $view = Craft::$app->getView();

            if (!static::$dynamicBlocksAdded) {
                return;
            }

            $view = Craft::$app->getView();
            $ajaxUrl = '/' . Craft::$app->getConfig()->getGeneral()->actionTrigger . '/servd-asset-storage/dynamic-content/get-content';

            $view->registerJs('
                function insertBlocks(blocks)
                {
                    var allChildrenOnPage = [];
                    for(var i = 0; i < blocks.length; i++){
                        var rBlock = blocks[i];
                        var dBlock = document.getElementById(rBlock.id);
                        var placeholder = document.createElement("div");
                        placeholder.insertAdjacentHTML("afterbegin", rBlock.html);
                        var allChildren = [];
                        for (var j = 0; j < placeholder.childNodes.length; j++) {
                            allChildren.push(placeholder.childNodes[j]);
                            allChildrenOnPage.push(placeholder.childNodes[j]);
                        }
                        for(var node of allChildren){
                            dBlock.parentNode.insertBefore(node, dBlock);
                        }
                        dBlock.parentNode.removeChild(dBlock);    
                    }
                    
                    return allChildrenOnPage;
                }
                function pullDynamic() {
                    var injectedContent = document.getElementById("SERVD_DYNAMIC_BLOCKS");
                    if(injectedContent){
                        var parsedContent = JSON.parse(injectedContent.innerHTML);
                        let insertedBlocks = insertBlocks(parsedContent.blocks);
                        window.dispatchEvent( new CustomEvent("servd.dynamicloaded", {detail: {blocks: insertedBlocks}}) );
                        return;
                    }

                    var dynamicBlocks = document.getElementsByClassName("dynamic-block");
                    var len = dynamicBlocks.length;
                    var allBlocks = [];
                    for (var i=0; i<len; i++) {
                        var block = dynamicBlocks[i];
                        var blockId = block.id;
                        var template = block.getAttribute("data-template");
                        var args = block.getAttribute("data-args");
                        var siteId = block.getAttribute("data-site");
                        allBlocks.push({
                            id: blockId,
                            template: template,
                            args: args,
                            siteId: siteId
                        });
                    }

                    if(allBlocks.length > 0){
                        var xhr = new XMLHttpRequest();
                        xhr.onload = function () {
                            if (xhr.status >= 200 && xhr.status <= 299) {
                                var responseContent = JSON.parse(xhr.response);
                                let insertedBlocks = insertBlocks(responseContent.blocks);
                                window.dispatchEvent( new CustomEvent("servd.dynamicloaded", {detail: {blocks: insertedBlocks}}) );
                            }
                        }
                        xhr.open("POST", "' . $ajaxUrl . '", );
                        xhr.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
                        xhr.send(JSON.stringify(allBlocks));
                    } else {
                        window.dispatchEvent( new CustomEvent("servd.dynamicloaded", {detail: {blocks: []}}) );
                    }
                }
                setTimeout(pullDynamic, 50);
            ', View::POS_END);

            if (sizeof(static::$esiBlocks) == 0) {
                return;
            }

            $allBlocks = serialize(static::$esiBlocks);
            $compressedData = urlencode(base64_encode(gzcompress($allBlocks)));

            $esiUrl = '/' . Craft::$app->getConfig()->getGeneral()->actionTrigger . '/servd-asset-storage/dynamic-content/get-content';
            $esiUrl .= '?blocks=' . $compressedData;

            $headers = \Craft::$app->getResponse()->getHeaders();
            if (!$headers->has('Surrogate-Control')) {
                $headers->add('Surrogate-Control', 'content="ESI/1.0"');
            }
            if (version_compare(Craft::$app->getVersion(), '3.5', '>=')) { //registerHtml only available in 3.5+
                $view->registerHtml('<script id="SERVD_DYNAMIC_BLOCKS" type="application/json"><esi:include src="' . $esiUrl . '" /></script>');
            } else {
                // ???
            }
        });
    }

    private function registerEventHandlers()
    {

        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function (RegisterCacheOptionsEvent $event) {
                $event->options[] = [
                    'key' => 'servd-static-cache',
                    'label' => Craft::t('servd-asset-storage', 'Servd Static Cache'),
                    'action' => function () {
                        $this->clearStaticCache();
                    },
                ];

                /***
                 * Deprecated cli key
                 */
                $event->options[] = [
                    'key' => 'servd-asset-storage',
                    'label' => Craft::t('servd-asset-storage', 'Servd Static Cache (Deprecated)'),
                    'action' => function () {
                        $this->clearStaticCache();
                    },
                ];
            }
        );
    }

    private function registerFrontendEventHandlers()
    {
        if (\Craft::$app instanceof \craft\console\Application) {
            return false;
        }

        // HTTP request object
        $request = \Craft::$app->getRequest();

        if (
            $request->getIsCpRequest() || $request->getIsLivePreview() ||
            $request->getIsActionRequest() || !$request->getIsGet() ||
            ($request->headers['x-servd-cache'] ?? '0') !== '1'
        ) {
            return false;
        }

        Event::on(ElementQuery::class, ElementQuery::EVENT_AFTER_POPULATE_ELEMENT, function (PopulateElementEvent $event) {

            if (in_array(get_class($event->element), Tags::IGNORE_TAGS_FROM_CLASSES)) {
                return;
            }

            $tags = Plugin::$plugin->get('tags');

            if ($event->element instanceof \craft\elements\GlobalSet) {
                $tags->addTagForCurrentRequest(Tags::GLOBAL_SET_PREFIX . $event->element->handle);
            }

            $elementTags = $this->getTagsFromElementPopulateEvent($event);
            foreach ($elementTags as $tag) {
                $tags->addTagForCurrentRequest($tag);
            }
        });

        Event::on(View::class, View::EVENT_AFTER_RENDER_PAGE_TEMPLATE, function (TemplateEvent $event) {

            // Only store tags for pages which return a 200 response
            $response = \Craft::$app->getResponse();
            $responseCode = $response->statusCode;
            if ($responseCode !== 200) {
                return;
            }

            //Associate collected tags with the url
            Craft::beginProfile('StaticCache::Event::View::EVENT_AFTER_RENDER_PAGE_TEMPLATE', __METHOD__);

            $request = \Craft::$app->getRequest();
            $url = $request->getHostInfo() . $request->getUrl();
            if (getenv('SERVD_CACHE_INCLUDE_GET') === 'false') {
                $url = preg_replace('/\?.*/', '', $url);
            }
            $tags = Plugin::$plugin->get('tags')->associateCurrentRequestTagsWithUrl($url);
            Craft::info(
                'Associated the url: ' . $url . ' with tags: ' .  implode(', ', $tags),
                __METHOD__
            );
            Craft::endProfile('StaticCache::Event::View::EVENT_AFTER_RENDER_PAGE_TEMPLATE', __METHOD__);
        });
    }

    private function registerLoggedInHandlers()
    {
        Event::on(Application::class, Application::EVENT_INIT, function () {
            $user = Craft::$app->getUser();
            if ($user->isGuest) {
                Craft::$app->response->cookies->remove('SERVD_LOGGED_IN_STATUS');
            } else {
                $cookieValue = $user->checkPermission('accessCp') ? '1' : '2';
                $domain = Craft::$app->getConfig()->getGeneral()->defaultCookieDomain;
                $expire = (int) time() + (3600 * 24 * 300);
                if (PHP_VERSION_ID >= 70300) {
                    setcookie('SERVD_LOGGED_IN_STATUS', $cookieValue, [
                        'expires' => $expire,
                        'path' => '/',
                        'domain' => $domain,
                        'samesite' => null
                    ]);
                } else {
                    setcookie('SERVD_LOGGED_IN_STATUS', $cookieValue, $expire, '/', $domain, false, false);
                }
                $_COOKIE['SERVD_LOGGED_IN_STATUS'] = $cookieValue;
            }
        });
    }

    private function getTagsFromElementPopulateEvent(PopulateElementEvent $event)
    {
        $tags = [];

        $databaseRow = $event->row;
        if (!is_array($databaseRow)) {
            return;
        }

        $props = [
            'id'          => Tags::ELEMENT_ID_PREFIX,
            'sectionId'   => Tags::SECTION_ID_PREFIX,
            'structureId' => Tags::STRUCTURE_ID_PREFIX
        ];

        foreach (array_keys($props) as $prop) {
            if (isset($databaseRow[$prop]) && !is_null($databaseRow[$prop])) {
                $tags[] = $props[$prop] . $databaseRow[$prop];
            }
        }
        return $tags;
    }


    private function registerElementUpdateHandlers()
    {

        $settings = Plugin::$plugin->getSettings();
        if (
            $settings->clearCachesOnSave == 'never' || //We never clear the cache
            (!Craft::$app->getRequest()->getIsCpRequest() && $settings->clearCachesOnSave == 'control-panel') //Only clear on CP updates and this isn't one of them
        ) {
            return;
        }

        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, function ($event) {
            $this->handleUpdateEvent($event);
        });
        Event::on(Element::class, Element::EVENT_AFTER_MOVE_IN_STRUCTURE, function ($event) {
            $this->handleUpdateEvent($event);
        });
        Event::on(Elements::class, Elements::EVENT_AFTER_DELETE_ELEMENT, function ($event) {
            $this->handleUpdateEvent($event);
        });
        Event::on(Structures::class, Structures::EVENT_AFTER_MOVE_ELEMENT, function ($event) {
            $this->handleUpdateEvent($event);
        });
        Event::on(Sections::class, Sections::EVENT_AFTER_SAVE_SECTION, function ($event) {
            $this->handleUpdateEvent($event);
        });
    }

    private function handleUpdateEvent(Event $event)
    {

        $settings = Plugin::$plugin->getSettings();

        //Full cache clear
        if ($settings->cacheClearMode == 'full') {
            if (!isset($event->element)) {
                return;
            }
            $element = $event->element;
            if (
                Element::STATUS_ENABLED == $element->getStatus()
                || Entry::STATUS_LIVE == $element->getStatus()
            ) {
                $this->clearStaticCache($event);
            }
            return;
        }

        //Otherwise it's tag based invalidation

        $tags = Plugin::$plugin->get('tags');

        // 1. Collect tags from the element

        $updatedTags = $this->getTagsFromElementUpdateEvent($event);
        if (sizeof($updatedTags) == 0) {
            return;
        }

        Craft::info(
            'Purging static cache for tags: ' .  implode(', ', $updatedTags),
            __METHOD__
        );

        foreach ($updatedTags as $tag) {
            \craft\helpers\Queue::push(new PurgeTagJob([
                'description' => 'Purge static cache by tag',
                'tag' => $tag
            ]), 1025);
        }
    }

    private function getTagsFromElementUpdateEvent($event)
    {
        $tags = [];

        if ($event instanceof ElementEvent) {

            //Back out under specific circumstances
            if (in_array(get_class($event->element), Tags::IGNORE_TAGS_FROM_CLASSES)) {
                return [];
            }
            if (ElementHelper::isDraftOrRevision($event->element)) {
                return [];
            }
            if (property_exists($event->element, 'resaving') && $event->element->resaving === true) {
                return [];
            }


            if ($event->element instanceof \craft\elements\GlobalSet && is_string($event->element->handle)) {
                $tags[] = Tags::GLOBAL_SET_PREFIX . $event->element->handle;
            } elseif ($event->element instanceof \craft\elements\Asset && $event->isNew) {
                // Required if a new asset is created in case anything has looped over the volume contents 
                $tags[] = Tags::VOLUME_ID_PREFIX . (string)$event->element->volumeId;
            } else {
                // Required if an entry is activated added to a section. 
                // Needs to refresh any index pages which may have looped the section contents
                if (isset($event->element->sectionId)) {
                    $tags[] = Tags::SECTION_ID_PREFIX . $event->element->sectionId;
                }
                if (!$event->isNew) {
                    $tags[] = Tags::ELEMENT_ID_PREFIX . $event->element->getId();
                }
            }
        }

        if ($event instanceof SectionEvent) {
            $tags[] = Tags::SECTION_ID_PREFIX . $event->section->id;
        }

        if ($event instanceof MoveElementEvent or $event instanceof ElementStructureEvent) {
            $tags[] = Tags::STRUCTURE_ID_PREFIX . $event->structureId;
        }

        return $tags;
    }


    public function clearStaticCache(Event $event = null)
    {
        //Clear the cache
        $this->clearRedisBasedCache();
    }

    private function clearRedisBasedCache()
    {
        try {
            $redisDb = intval(getenv('REDIS_STATIC_CACHE_DB'));
            $redis = new Redis();

            //Clear out content
            $redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'), 5);
            $redis->select($redisDb);
            $redis->flushDb(true);
            $redis->close();
        } catch (Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }

        try {
            //Clear out metadata - ledge stores cached redirects here
            $qlessHost = str_ireplace('-redis.', '-redis-qless.', getenv('REDIS_HOST'));
            $redis->connect($qlessHost, getenv('REDIS_PORT'), 5);
            $redis->select($redisDb);
            $redis->flushDb(true);
            $redis->close();
        } catch (Exception $e) {
            //Do nothing - this is expected most of the time
        }
    }

    private function hookCPSidebarTemplate()
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsCpRequest() && !$request->getIsConsoleRequest()) {

            Craft::$app->view->hook('cp.entries.edit.details', function (array &$context) {
                $settings = Plugin::$plugin->getSettings();
                $entry = $context['entry'];
                $url = $entry->getUrl();
                if (!empty($url)) {
                    return Craft::$app->view->renderTemplate('servd-asset-storage/cp-extensions/static-cache-clear.twig', [
                        'entryId' => $entry->id,
                        'showTagPurge' => $settings->cacheClearMode == 'tags'
                    ]);
                }
                return '';
            });

            Craft::$app->view->hook('cp.commerce.product.edit.details', function (array &$context) {
                $settings = Plugin::$plugin->getSettings();
                $product = $context['product'];
                $url = $product->getUrl();
                if (!empty($url)) {
                    return Craft::$app->view->renderTemplate('servd-asset-storage/cp-extensions/static-cache-clear.twig', [
                        'productId' => $product->id,
                        'showTagPurge' => $settings->cacheClearMode == 'tags'
                    ]);
                }
                return '';
            });
        }
    }
}
