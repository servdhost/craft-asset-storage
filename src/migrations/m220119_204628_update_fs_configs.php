<?php

namespace servd\AssetStorage\migrations;

use Craft;
use craft\db\Migration;
use craft\services\ProjectConfig;
use servd\AssetStorage\AssetsPlatform\Fs;

/**
 * m220119_204627_update_fs_configs migration.
 */
class m220119_204628_update_fs_configs extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Don't make the same changes twice
        $schemaVersion = Craft::$app->getProjectConfig()->get('plugins.servd-asset-storage.schemaVersion', true);
        if (version_compare($schemaVersion, '3.0.0', '>=')) {
            return true;
        }

        // Just re-run the install migration
        
        $projectConfig = Craft::$app->getProjectConfig();
        $fsConfigs = $projectConfig->get(ProjectConfig::PATH_FS) ?? [];

        foreach ($fsConfigs as $uid => $config) {
            if (
                $config['type'] == 'servd\AssetStorage\Volume' &&
                isset($config['settings']) &&
                is_array($config['settings'])
            ) {
                $config['type'] = Fs::class;
                $projectConfig->set(sprintf('%s.%s', ProjectConfig::PATH_FS, $uid), $config);
            }
        }


        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220119_204628_update_fs_configs cannot be reverted.\n";
        return false;
    }
}