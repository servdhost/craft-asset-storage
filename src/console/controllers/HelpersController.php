<?php

namespace servd\AssetStorage\console\controllers;

use Craft;
use craft\helpers\Console;
use yii\console\ExitCode;
use craft\console\Controller;
use Exception;
use craft\db\Table;
use craft\db\Query;


class HelpersController extends Controller
{
    use ControllerTrait;

    public $defaultAction = 'index';

    public $environment;
    public $command;
    public $wait = true;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        return array_merge($options, [
            
        ]);
    }

    public function optionAliases()
    {
        $a = parent::optionAliases();
        return array_merge($a, [
            
        ]);
    }

    /******************
     * Actions
     *****************/

    public function actionIndex()
    {
        
        $this->stdout('Run helpers/convert-filename-accents to convert any utf8 modifier accent characters in filenames with the local database to absolute accented characters' . PHP_EOL);

        return ExitCode::OK;
    }

    public function actionConvertFilenameAccents()
    {
        //For all assets
        $query = (new Query())
        ->select(['id', 'filename'])
        ->from([Table::ASSETS])
        ->all();

        $this->stdout("Found " . count($query) . " assets" . PHP_EOL);

        if ($this->interactive && !$this->confirm('This will adjust the filenames of assets directly in the database. **Take a database backup.** Do you want to continue?')) {
            return ExitCode::OK;
        }

        foreach($query as $row) {
            $id = $row['id'];
            $filename = $row['filename'];

            $adjusted = \Normalizer::normalize($filename, \Normalizer::FORM_C); 

            if($adjusted !== $filename) {
                $this->stdout("Updating asset $id from $filename to $adjusted" . PHP_EOL);
                Craft::$app->getDb()->createCommand()
                ->update(Table::ASSETS, ['filename' => $adjusted], ['id' => $id])
                ->execute();
            }
        }
    }
}
