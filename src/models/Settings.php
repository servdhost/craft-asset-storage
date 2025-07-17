<?php

namespace servd\AssetStorage\models;

use Craft;
use craft\base\Model;
use servd\AssetStorage\AssetsPlatform\AssetsPlatform;

class Settings extends Model
{

    static $CURRENT_TYPE = null;

    public $injectCors = false;
    public $clearCachesOnSave = 'always';
    public $cacheClearMode = 'full';
    public $assetsEnvironmentOverwrite = ''; #Deprecated
    public $assetsEnvironment = '';
    public $projectSlug = '';
    public $securityKey = '';
    public $suppressWarnings = false;
    public $useLocalVolumes = false;
    public $disableDynamic = false;
    public $disableTransforms = false;
    public $adjustFeedmeLogs = false;
    public $imageAutoConversion = 'webp';

    public function checkForType()
    {
        //Check if we know what version we're running, if not, try to find out
        $overrideEnv = getenv('SERVD_ASSETS_TYPE');
        if (!empty($overrideEnv)) {
            self::$CURRENT_TYPE = $overrideEnv;
            return;
        }
        $type = Craft::$app->cache->get(AssetsPlatform::CACHE_KEY_TYPE) ?? null;
        if (empty($type)) {
            //Try to find out
            $lastCheck = Craft::$app->cache->get('servdassets.lastcheck');
            if (!empty($lastCheck)) {
                //We already checked recently and it must have failed
                return;
            }
            if (empty($this->getProjectSlug() || empty($this->getSecurityKey()))) {
                // Can't ask Servd, just leave it null for a while I guess
            } else {
                $lastCheck = Craft::$app->cache->set('servdassets.lastcheck', 'true', 300);
                try {
                    $ap = new AssetsPlatform();
                    $ap->getStorageInfoFromServd();
                    $type = Craft::$app->cache->get(AssetsPlatform::CACHE_KEY_TYPE) ?? null;
                    self::$CURRENT_TYPE = $type;
                } catch (\Exception $e) {
                    //Failed to get details about the current asset platform version
                }
            }
        } else {
            self::$CURRENT_TYPE = $type;
        }
    }

    public function rules()
    {
        return [
            //[['injectCors'], 'required'],
            // ...
        ];
    }

    public function getProjectSlug()
    {
        if (!empty($this->projectSlug)) {
            return Craft::parseEnv($this->projectSlug);
        }
        return getenv('SERVD_PROJECT_SLUG');
    }

    public function getSecurityKey()
    {
        if (!empty($this->securityKey)) {
            return Craft::parseEnv($this->securityKey);
        }
        return getenv('SERVD_SECURITY_KEY');
    }

    public function getAssetsEnvironment()
    {
        if (!empty($this->assetsEnvironment)) {
            $overwrite = Craft::parseEnv($this->assetsEnvironment);
            if (strlen($overwrite) > 0 && substr($overwrite, 0, 1) != '$') {
                return $overwrite;
            }
        }
        if (!empty(getenv('SERVD_ASSETS_ENVIRONMENT'))) {
            return Craft::parseEnv(getenv('SERVD_ASSETS_ENVIRONMENT'));
        }
        $environment = getenv('ENVIRONMENT');
        if ('development' == $environment || 'staging' == $environment || 'production' == $environment) {
            return $environment;
        }
        return 'local';
    }
}
