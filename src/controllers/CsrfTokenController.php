<?php

namespace servd\AssetStorage\controllers;

use Craft;
use craft\web\Controller;

class CsrfTokenController extends Controller
{
    protected $allowAnonymous = true;

    public function actionGetToken()
    {
        $req = Craft::$app->getRequest();

        return $this->asJson([
            'token' => $req->getCsrfToken(),
            'name' => $req->csrfParam,
        ]);
    }
}
