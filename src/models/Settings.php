<?php

namespace servd\AssetStorage\models;

use Craft;
use craft\base\Model;
use craft\fs\Local;
use craft\helpers\App;
use servd\AssetStorage\AssetsPlatform\AssetsPlatform;
use servd\AssetStorage\AssetsPlatform\Fs;

class Settings extends Model
{

    static $CURRENT_TYPE = null;

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
    public $fsMapsEnabled = false;
    public $fsMaps = [];
    public $imageAutoConversion = 'webp';
    public $transformSvgs = 'no';

    public function checkForType()
    {
        //Check if we know what version we're running, if not, try to find out
        $overrideEnv = App::env('SERVD_ASSETS_TYPE');
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

    public function rules(): array
    {
        $rules = [];
        if ($this->fsMapsEnabled) {
            $rules[] = ['fsMaps', 'required'];
            $rules[] = ['fsMaps', 'fsMapsValidation'];
        } else {
            $rules[] = ['fsMaps', 'fsMapsNull'];
        }
        return $rules;
    }

    public function fsMapsValidation($attribute)
    {
        //Check all servd fs are included
        $servdFs = $this->getServdFilesystems();
        $servdFsHandles = array_map(fn ($x) => $x->handle, $servdFs);
        $submittedKeys = array_keys($this->fsMaps);
        foreach ($servdFsHandles as $h) {
            if (!in_array($h, $submittedKeys)) {
                $this->addError($attribute, 'Some Servd Filesystems are missing');
                break;
            }
        }

        //Check all are set to a valid local fs
        $localFs = $this->getLocalFilesystems();
        $localFsHandles = array_map(fn ($x) => $x->handle, $localFs);
        $mappedTargets = array_values($this->fsMaps);
        foreach ($mappedTargets as $target) {
            if (!in_array($target, $localFsHandles)) {
                $this->addError($attribute, 'All Servd Filesystems must be linked to a valid Local Folder filesystem');
                return; //Prevent the next error showing up for 'none' selections.
            }
        }

        //Make sure the same local FS isn't used twice
        if (sizeof($mappedTargets) != sizeof(array_unique($mappedTargets))) {
            $this->addError($attribute, 'The same Local Folder Filesystem can\'t be mapped to multiple Servd Filesystems');
        }
    }

    public function fsMapsNull($attribute)
    {
        //Null out all of the fs maps by setting to 'none'
        $this->fsMaps = array_fill_keys(array_keys($this->fsMaps), 'none');
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
        if ('development' == $environment || 'staging' == $environment || 'production' == $environment) {
            return $environment;
        }
        return 'local';
    }

    public function getLocalFilesystems()
    {
        $fsService = \Craft::$app->fs;
        return array_filter($fsService->getAllFilesystems(), function ($fs) {
            return is_a($fs, Local::class);
        });
    }

    public function getServdFilesystems()
    {
        $fsService = \Craft::$app->fs;
        return array_filter($fsService->getAllFilesystems(), function ($fs) {
            return is_a($fs, Fs::class);
        });
    }

    public function getLocalFsAsOptions()
    {
        $fs = $this->getLocalFilesystems();
        $opt = ["none" => "None"];
        foreach ($fs as $f) {
            $opt[$f->handle] = $f->name;
        }
        return $opt;
    }
}
