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

        //The yaml has already been updated so do nothing, craft will take care of syncing the changes from PC over
        if (version_compare($schemaVersion, '2.0.5', '>=')) {
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
