<?php

namespace servd\AssetStorage\CPAlerts;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table;
use craft\events\RegisterCpAlertsEvent;
use craft\helpers\App;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use craft\mail\transportadapters\Sendmail;
use craft\volumes\Local;
use servd\AssetStorage\Plugin;
use yii\base\Event;

class CPAlerts extends Component
{

    public function init()
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
            }
        );
    }

    private function checkForVolumeErrors()
    {
        $messages = [];

        //If the project has a Local Folder volume in use

        //Query the DB directly so that we aren't hydrating models that we don't need
        $query = (new Query())
            ->select(['id', 'type'])
            ->from([Table::VOLUMES])
            ->where(['type' => Local::class, 'dateDeleted' => null]);
        $count = $query->count();

        if ($count > 0) {
            $messages[] = 'You have an Assets Volume defined of type \'Local Folder\' which is not supported on Servd.' .
                ' ' . '<a class="go" href="' . UrlHelper::url('settings/assets') . '">Update</a>';
        }

        return $messages;
    }

    private function checkForSettingsErrors()
    {
        $messages = [];

        //If we aren't in staging or prod and the servd plugin hasn't been configured
        $env = Craft::$app->getConfig()->env;
        if (!in_array($env, ['staging', 'production'])) {
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
            $messages[] = 'Your mail settings are currently configured to use sendmail which is not available on Servd' .
                ' ' . '<a class="go" href="' . UrlHelper::url('settings/email') . '">Update</a>';
        }

        return $messages;
    }
}
