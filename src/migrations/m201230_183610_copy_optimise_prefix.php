<?php

namespace servd\AssetStorage\migrations;

use Craft;
use craft\db\Command;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\ArrayHelper;
use servd\AssetStorage\Volume;

/**
 * m201230_183610_copy_optimise_prefix migration.
 */
class m201230_183610_copy_optimise_prefix extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Place migration code here...
        $schemaVersion = Craft::$app->projectConfig
            ->get('plugins.servd-asset-storage.schemaVersion', true);

        $allowAdminChanges = Craft::$app->getConfig()->getGeneral()->allowAdminChanges;

        // If allowAdminChanges is false (using PC) and the yaml has already been updated, back out and
        // allow PC sync to apply the changes
        // NOTE: This will allow an error to occur if the migration is attempted for the very first time
        // on an $allowAdminChanges=false environment, but we _want_ that error to occur because
        // it tells users that they should be performing this migration in local dev first
        // NOTE: Multi-developer local development will end up running the migration multiple times even though
        // it shouldn't because the results are held in PC and merged between devs. Don't know what to do about that.
        if (!$allowAdminChanges && version_compare($schemaVersion, '2.0.5', '>=')) {
            return;
        }

        $plugin = Craft::$app->getPlugins()->getPlugin('servd-asset-storage');
        if ($plugin === null) {
            throw new \Exception('Plugin not found');
        }

        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        foreach ($volumes as $volume) {
            if (get_class($volume) == Volume::class) {
                if (!empty($volume->optimisePrefix)) {
                    if (!empty($volume->customSubfolder)) {
                        $volume->optimiseUrlPattern = rtrim($volume->optimisePrefix, '/') . '/{{projectSlug}}/{{environment}}/{{subfolder}}/{{filePath}}{{params}}';
                    } else {
                        $volume->optimiseUrlPattern = rtrim($volume->optimisePrefix, '/') . '/{{projectSlug}}/{{environment}}/{{filePath}}{{params}}';
                    }
                }
                if (substr_count($volume->url, 'cdn2.assets-servd.host') == 0) {
                    if (!empty($volume->customSubfolder)) {
                        $volume->cdnUrlPattern = rtrim($volume->url, '/') . '/{{projectSlug}}/{{environment}}/{{subfolder}}/{{filePath}}';
                    } else {
                        $volume->cdnUrlPattern = rtrim($volume->url, '/') . '/{{projectSlug}}/{{environment}}/{{filePath}}';
                    }
                }
                Craft::$app->getVolumes()->saveVolume($volume);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201230_183610_copy_optimise_prefix cannot be reverted.\n";
        return false;
    }
}
