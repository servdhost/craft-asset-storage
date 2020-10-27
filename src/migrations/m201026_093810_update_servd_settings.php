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
 * m201026_093810_update_servd_settings migration.
 */
class m201026_093810_update_servd_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Place migration code here...
        $projectSlug = null;
        $securityKey = null;

        $schemaVersion = Craft::$app->projectConfig
            ->get('plugins.servd-asset-storage.schemaVersion', true);

        $plugin = Craft::$app->getPlugins()->getPlugin('servd-asset-storage');
        if ($plugin === null) {
            throw new \Exception('Plugin not found');
        }

        $currentSettings = $plugin->getSettings();

        //Find all Servd volumes and update their subfolder field
        $query = (new Query())
            ->select([
                'id',
                'dateCreated',
                'dateUpdated',
                'name',
                'handle',
                'hasUrls',
                'url',
                'sortOrder',
                'fieldLayoutId',
                'type',
                'settings',
                'uid'
            ])
            ->from([Table::VOLUMES])
            ->orderBy(['sortOrder' => SORT_ASC]);
        $query->where(['type' => Volume::class]);
        $results = $query->all();

        foreach ($results as $v) {
            $s = json_decode($v['settings'], true);
            $s['customSubfolder'] = $s['subfolder'] ?? null;
            unset($s['subfolder']);
            $this->update(
                Table::VOLUMES,
                [
                    'settings' => json_encode($s),
                ],
                [
                    'id' => $v['id'],
                ]
            );
        }

        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        foreach ($volumes as $volume) {
            if (get_class($volume) == Volume::class) {
                $projectSlug = $volume->projectSlug;
                $securityKey = $volume->securityKey;
                if (version_compare($schemaVersion, '2.0.0', '<')) {
                    Craft::$app->getVolumes()->saveVolume($volume);
                }
            }
        }

        $settings = [
            'injectCors' => $currentSettings['injectCors'] ?? null,
            'clearCachesOnSave' => $currentSettings['clearCachesOnSave'] ?? null,
            'assetsEnvironmentOverwrite' => $currentSettings['assetsEnvironmentOverwrite'] ?? null,
            'assetsEnvironment' => $currentSettings['assetsEnvironmentOverwrite'] ?? null, //Copy over from deprecated
            'projectSlug' => $projectSlug,
            'securityKey' => $securityKey,
        ];

        //Add them to the plugin settings
        if (version_compare($schemaVersion, '2.0.0', '<')) {
            Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201026_093810_update_servd_settings cannot be reverted.\n";
        return false;
    }
}
