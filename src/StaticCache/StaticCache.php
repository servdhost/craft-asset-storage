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

class StaticCache extends Component
{

    public function init()
    {

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
                    'label' => Craft::t('servd-asset-storage', '(Deprecated)'),
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
            if (Craft::$app->getUser()->isGuest) {
                Craft::$app->response->cookies->remove('SERVD_LOGGED_IN_STATUS');
            } else {
                $domain = Craft::$app->getConfig()->getGeneral()->defaultCookieDomain;
                $expire = (int) time() + (3600 * 24 * 300);
                if (PHP_VERSION_ID >= 70300) {
                    setcookie('SERVD_LOGGED_IN_STATUS', '1', [
                        'expires' => $expire,
                        'path' => '/',
                        'domain' => $domain,
                        'secure' => false,
                        'httponly' => false,
                        'samesite' => null
                    ]);
                } else {
                    setcookie('SERVD_LOGGED_IN_STATUS', '1', $expire, '/', $domain, false, false);
                }
                $_COOKIE['SERVD_LOGGED_IN_STATUS'] = 1;
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

        // 2. Trigger a purge for all URLs which are linked to the tag

        $allUrlsToPurge = [];
        foreach ($updatedTags as $updatedTag) {
            $allUrlsToPurge = array_merge($tags->getUrlsForTag($updatedTag), $allUrlsToPurge);
        }
        $allUrlsToPurge = array_unique($allUrlsToPurge);
        if (sizeof($allUrlsToPurge) == 0) {
            return;
        }

        Craft::info(
            'Purging static cache for urls: ' .  implode(', ', $allUrlsToPurge),
            __METHOD__
        );

        Craft::$app->queue->push(new PurgeUrlsJob([
            'description' => 'Purge static cache',
            'urls' => $allUrlsToPurge,
            'triggers' => $updatedTags,
        ]));
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
            $redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'));
            $redis->select($redisDb);
            $redis->flushDb(true);
            $redis->close();
        } catch (Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
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
        }
    }
}
