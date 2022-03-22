<?php

namespace servd\AssetStorage\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;

class Settings extends Model
{

    public $injectCors = false;
    public $clearCachesOnSave = 'always';
    public $cacheClearMode = 'full';
    public $assetsEnvironmentOverwrite = ''; // Deprecated
    public $assetsEnvironment = '';
    public $projectSlug = '';
    public $securityKey = '';
    public $suppressWarnings = false;
    public $useLocalVolumes = false; // Removed in Craft 4 (kept to prevent model hydration errors)
    public $disableDynamic = false;
    public $disableTransforms = false;
    public $adjustFeedmeLogs = false;

    public function rules(): array
    {
        return [
            //[['injectCors'], 'required'],
            // ...
        ];
    }

    public function getProjectSlug()
    {
        if (!empty($this->projectSlug)) {
            return App::parseEnv($this->projectSlug);
        }
        return getenv('SERVD_PROJECT_SLUG');
    }

    public function getSecurityKey()
    {
        if (!empty($this->securityKey)) {
            return App::parseEnv($this->securityKey);
        }
        return getenv('SERVD_SECURITY_KEY');
    }

    public function getAssetsEnvironment()
    {
        if (!empty($this->assetsEnvironment)) {
            $overwrite = App::parseEnv($this->assetsEnvironment);
            if (strlen($overwrite) > 0 && substr($overwrite, 0, 1) != '$') {
                return $overwrite;
            }
        }
        if (!empty(getenv('SERVD_ASSETS_ENVIRONMENT'))) {
            return App::parseEnv(getenv('SERVD_ASSETS_ENVIRONMENT'));
        }
        $environment = getenv('ENVIRONMENT');
        if ('staging' == $environment || 'production' == $environment) {
            return $environment;
        }
        return 'local';
    }
}
