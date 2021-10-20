<?php

namespace servd\AssetStorage\controllers;

use Craft;
use craft\web\Controller;

class DynamicContentController extends Controller
{
    protected $allowAnonymous = true;
    public $enableCsrfValidation = false;

    public function actionGetContent()
    {
        $req = Craft::$app->getRequest();

        $seomatic = Craft::$app->plugins->getPlugin('seomatic');
        if (!empty($seomatic)) {
            $seomatic::$plugin->settings->renderEnabled = false;
        }

        if ($req->isPost) {
            //Ajax requests arrive via POST and contain multiple blocks
            $blocks = json_decode($req->getRawBody(), true);

            $response = ['blocks' => []];

            foreach ($blocks as $block) {
                $id = $block['id'];
                $siteId = $block['siteId'];
                $template = base64_decode($block['template']);
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
            $blocks = unserialize(gzuncompress(base64_decode($req->getQueryParam('blocks'))));

            $response = ['blocks' => []];
            foreach ($blocks as $block) {
                $id = $block['id'];
                $siteId = $block['siteId'];
                $template = $block['template'];
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
                if (isset($el['type']) && isset($el['id'])) {
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
