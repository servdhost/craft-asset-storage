<?php

namespace servd\AssetStorage\CsrfInjection;

use Craft;
use craft\base\Component;
use craft\web\View;
use servd\AssetStorage\Plugin;

class CsrfInjection extends Component
{

    public function init()
    {
        $settings = Plugin::$plugin->getSettings();
        if ($settings->injectCors) {
            $this->injectCSRFTokenScript();
        }
    }

    private function injectCSRFTokenScript()
    {
        $view = Craft::$app->getView();

        $csrfTokenName = Craft::$app->getConfig()->getGeneral()->csrfTokenName;

        if (!Craft::$app->getRequest()->getIsCpRequest()) {
            $url = '/' . Craft::$app->getConfig()->getGeneral()->actionTrigger . '/servd-asset-storage/csrf-token/get-token';
            $view->registerJs('
                window.SERVD_CSRF_TOKEN_NAME = "' . $csrfTokenName . '";
                function injectCSRF() {
                    var inputs = document.getElementsByName(window.SERVD_CSRF_TOKEN_NAME);
                    var len = inputs.length;
                    if (len > 0) {
                        var xhr = new XMLHttpRequest();
                        xhr.onload = function () {
                            if (xhr.status >= 200 && xhr.status <= 299) {
                                var tokenInfo = JSON.parse(this.responseText);
                                window.csrfTokenValue = tokenInfo.token;
                                window.csrfTokenName = tokenInfo.name;
                                for (var i=0; i<len; i++) {
                                    inputs[i].setAttribute("value", tokenInfo.token);
                                }
                            }
                        };
                        xhr.open("GET", "' . $url . '");
                        xhr.send();
                    }
                }
                setTimeout(injectCSRF, 200);
            ', View::POS_END);
        }
    }
}
