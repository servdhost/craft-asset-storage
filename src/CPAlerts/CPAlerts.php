<?php

namespace servd\AssetStorage\CPAlerts;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table;
use craft\events\RegisterCpAlertsEvent;
use craft\fs\Local;
use craft\helpers\App;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use craft\mail\transportadapters\Sendmail;
use servd\AssetStorage\models\Settings;
use servd\AssetStorage\Plugin;
use yii\base\Event;

class CPAlerts extends Component
{

    public function init(): void
    {
        $this->registerEventHandlers();
    }

    public function registerEventHandlers()
    {
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_ALERTS,
            function (RegisterCpAlertsEvent $event) {
                //Always show these alerts
                $event->alerts = array_merge($event->alerts, $this->checkForStorageFull());

                $settings = Plugin::$plugin->getSettings();
                if ($settings->suppressWarnings == '1') {
                    return;
                }

                //Conditionally show these alerts
                $event->alerts = array_merge($event->alerts, $this->checkForVolumeErrors());
                $event->alerts = array_merge($event->alerts, $this->checkForSettingsErrors());
                $event->alerts = array_merge($event->alerts, $this->checkForSendmail());
                $event->alerts = array_merge($event->alerts, $this->checkForAssetsVersion());
            }
        );
    }

    private function checkForAssetsVersion()
    {
        $messages = [];
        if(empty(Settings::$CURRENT_TYPE)){
            $messages[] = 'Unable to connect to Servd\'s Asset Platform. Check your \'Project Slug\' and \'Security Key\' then clear Craft\'s data cache';
        }
        return $messages;
    }

    private function checkForVolumeErrors()
    {
        $env = Craft::$app->getConfig()->env;
        if (!in_array($env, ['development', 'staging', 'production'])) {
            return [];
        }

        $messages = [];

        //If the project has a Local Folder volume in use
        $volumeService = Craft::$app->volumes;
        $fs = array_map(function ($v) {
            return $v->fs;
        }, $volumeService->getAllVolumes());
        $inUseLocalFs = array_filter($fs, function ($fs) {
            return is_a($fs, Local::class);
        });
    
        if (sizeof($inUseLocalFs) > 0) {
            $messages[] = 'You have an in-use Filesystem of type \'Local Folder\' which is not supported on Servd.' .
                ' ' . '<a class="go" href="' . UrlHelper::url('settings/assets') . '">Update</a>';
        }

        return $messages;
    }

    private function checkForSettingsErrors()
    {
        $messages = [];

        //If we aren't in staging or prod and the servd plugin hasn't been configured
        $env = Craft::$app->getConfig()->env;
        if (!in_array($env, ['development', 'staging', 'production'])) {
            $settings = Plugin::$plugin->getSettings();
            if (empty($settings->getProjectSlug()) || empty($settings->getSecurityKey())) {
                $messages[] = 'You have not set a Servd \'Project Slug\' or \'Security Key\' which are required during local development.' .
                    ' ' . '<a class="go" href="' . UrlHelper::url('settings/plugins/servd-asset-storage') . '">Update</a>';
            }
        }
        return $messages;
    }

    private function checkForStorageFull()
    {
        $messages = [];

        $usage = Plugin::$plugin->assetsPlatform->getCurrentUsagePercent();

        if ($usage > 1) {
            $messages[] = 'Your Servd Assets Platform storage is ' . (round($usage * 100)) . '% full.' .
                ' ' . '<a class="go" href="https://servd.host/docs/what-happens-if-i-exceed-my-assets-storage-limit">Help!</a>';
        }

        return $messages;
    }

    private function checkForSendmail()
    {
        $messages = [];

        $settings = App::mailSettings();
        if ($settings['transportType'] == Sendmail::class) {
            $messages[] = 'Sending email over sendmail is <a href="https://servd.host/docs/outbound-email" target="_blank">disabled on Servd</a>' .
                ' <a class="go" href="' . UrlHelper::url('settings/email') . '">Update</a>' .
                ' <a class="go" href="https://servd.host/docs/smtp">Use Servd SMTP</a>';
        }

        return $messages;
    }
}
