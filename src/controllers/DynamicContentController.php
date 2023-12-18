<?php

namespace servd\AssetStorage\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Request;

class DynamicContentController extends Controller
{
    protected array|bool|int $allowAnonymous = true;
    public $enableCsrfValidation = false;

    public function actionGetContent()
    {
        /** @var Request $req */
        $req = Craft::$app->getRequest();
        if ($req->getIsCpRequest()) {
            return $this->asFailure('Not to be used with control panel requests');
        }

        $seomatic = Craft::$app->plugins->getPlugin('seomatic');
        if (!empty($seomatic)) {
            $seomatic::$plugin->settings->renderEnabled = false;
        }

        if ($req->isPost) {
            //Ajax requests arrive via POST and contain multiple blocks
            $blocks = json_decode($req->getRawBody(), true);

            $response = ['blocks' => []];

            foreach ($blocks as $block) {
                if (!isset($block['id']) || !isset($block['siteId']) || !isset($block['template']) || !isset($block['args'])) {
                    Craft::warning('Invalid dynamic block request - required parameter missing', 'servd-asset-storage');
                    continue;
                }
                $id = $block['id'];
                $siteId = $block['siteId'];
                $template = base64_decode($block['template']);
                $template = Craft::$app->getSecurity()->validateData($template);
                if (empty($template)) {
                    Craft::warning('Invalid dynamic block request - supplied template failed security check: ' . $block['template'] , 'servd-asset-storage');
                    continue;
                }
                $args = unserialize(gzuncompress(base64_decode($block['args'])));
                $args = $this->rehydrateArgs($args);

                Craft::$app->getSites()->setCurrentSite($siteId);
                $output = Craft::$app->getView()->renderPageTemplate($template, $args);
                $response['blocks'][] = [
                    'id' => $id,
                    'html' => $output
                ];
            }

            return $this->asJson($response);
        } else {
            //ESI can only use get requests and only contain a single block

            //Make sure the request has a blocks query param
            $blocks = $req->getQueryParam('blocks');
            if (empty($blocks)) {
                return $this->asFailure('No blocks specified');
            }

            $blocks = unserialize(gzuncompress(base64_decode($blocks)));

            $response = ['blocks' => []];
            foreach ($blocks as $block) {
                if (!isset($block['id']) || !isset($block['siteId']) || !isset($block['template']) || !isset($block['args'])) {
                    Craft::warning('Invalid dynamic block request - required parameter missing', 'servd-asset-storage');
                    continue;
                }
                $id = $block['id'];
                $siteId = $block['siteId'];
                $template = Craft::$app->getSecurity()->validateData($block['template']);
                if (empty($template)) {
                    Craft::warning('Invalid dynamic block request - supplied template failed security check: ' . $block['template'], 'servd-asset-storage');
                    continue;
                }
                $args = $block['args'];
                $args = $this->rehydrateArgs($args);

                Craft::$app->getSites()->setCurrentSite($siteId);
                $output = Craft::$app->getView()->renderPageTemplate($template, $args);
                $response['blocks'][] = [
                    'id' => $id,
                    'html' => $output
                ];
            }

            $resp = $this->asJson($response);
            $resp->formatters[\yii\web\Response::FORMAT_JSON] = [
                'class' => 'yii\web\JsonResponseFormatter',
                'encodeOptions' => JSON_UNESCAPED_UNICODE
            ];
            return $resp;
        }
    }

    private function rehydrateArgs($a)
    {
        $hydrated = [];
        $elements = \Craft::$app->getElements();
        foreach ($a as $key => $el) {
            if (is_scalar($el) || is_bool($el) || is_null($el)) {
                $hydrated[$key] = $el;
                continue;
            }
            if (is_array($el)) {
                if (isset($el['servdShouldHydrate']) && isset($el['type']) && isset($el['id'])) {
                    $hydrated[$key] = $elements->getElementById($el['id'], $el['type']);
                } else {
                    $hydrated[$key] = $this->rehydrateArgs($el);
                }
                continue;
            }
        }
        return $hydrated;
    }
}
