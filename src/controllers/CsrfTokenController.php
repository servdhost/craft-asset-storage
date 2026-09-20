<?php

namespace servd\AssetStorage\controllers;

use Craft;
use craft\web\Controller;
use craft\helpers\App;
use servd\AssetStorage\Plugin;
use yii\web\NotFoundHttpException;

class CsrfTokenController extends Controller
{
    protected $allowAnonymous = true;

    public function actionGetToken()
    {
        $settings = Plugin::$plugin->getSettings();
        if (!App::env('SERVD_CACHE_ENABLED') || !$settings->injectCors) {
            throw new NotFoundHttpException();
        }

        $req = Craft::$app->getRequest();

        return $this->asJson([
            'token' => $req->getCsrfToken(),
            'name' => $req->csrfParam,
        ]);
    }
}
