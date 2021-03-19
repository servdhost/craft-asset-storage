<?php

namespace servd\AssetStorage\LocalDev;

use Craft;
use craft\base\Component;
use servd\AssetStorage\Plugin;
use servd\AssetStorage\Volume;

class LocalDev extends Component
{

    public function init()
    {
        $this->replaceLocalVolumes();
    }

    public function replaceLocalVolumes()
    {
        //Only do this is it is enabled in the plugin settings
        $settings = Plugin::$plugin->getSettings();
        if ($settings->useLocalVolumes !== '1') {
            return;
        }

        //If we're running in Servd, don't do anything
        if (!empty(getenv('SERVD_COMPONENT'))) {
            return;
        }

        $request = Craft::$app->request;

        if ($request->isConsoleRequest) {
            $isPC = false;
            foreach ($request->params as $p) {
                if (substr_count($p, 'project-config') > 0) {
                    $isPC = true;
                    break;
                }
            }
            if ($isPC) {
                return;
            }
        }

        //If this is a project config related action, do nothing so that we don't mess up the PC
        if ($request->isActionRequest) {
            if (in_array('project-config', $request->actionSegments)) {
                return;
            }
            if (in_array('volumes', $request->actionSegments)) {
                return;
            }
        }

        //If this is a cp request and we're looking specifically at a volume's settings
        if ($request->isCpRequest && strpos($request->fullPath, 'settings/assets') !== false) {
            return;
        }

        //Replace the Servd volume with a local one
        \Craft::$container->set(Volume::class, function ($container, $params, $config) {
            if (empty($config['id'])) {
                return new Volume($config);
            }

            return new \craft\volumes\Local([
                'id' => $config['id'],
                'uid' => $config['uid'],
                'name' => $config['name'],
                'handle' => $config['handle'],
                'hasUrls' => $config['hasUrls'],
                'url' => "@web/servd-volumes/{$config['handle']}",
                'path' => "@webroot/servd-volumes/{$config['handle']}",
                'sortOrder' => $config['sortOrder'],
                'dateCreated' => $config['dateCreated'],
                'dateUpdated' => $config['dateUpdated'],
                'fieldLayoutId' => $config['fieldLayoutId'],
            ]);
        });
    }
}
