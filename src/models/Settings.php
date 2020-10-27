<?php

namespace servd\AssetStorage\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    public $injectCors = false;
    public $clearCachesOnSave = 'always';
    public $assetsEnvironmentOverwrite = ''; #Deprecated
    public $assetsEnvironment = '';
    public $projectSlug = '';
    public $securityKey = '';

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
            return Craft::parseEnv($this->assetsEnvironment);
        }
        if (!empty(getenv('SERVD_ASSETS_ENVIRONMENT'))) {
            return Craft::parseEnv(getenv('SERVD_ASSETS_ENVIRONMENT'));
        }
        $environment = getenv('ENVIRONMENT');
        if ('staging' == $environment || 'production' == $environment) {
            return $environment;
        }
        return 'local';
    }
}
