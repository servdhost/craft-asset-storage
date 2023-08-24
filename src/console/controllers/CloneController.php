<?php

namespace servd\AssetStorage\console\controllers;

use Craft;
use craft\helpers\Console;
use yii\console\ExitCode;
use craft\console\Controller;
use Exception;

class CloneController extends Controller
{
    use ControllerTrait;

    public $defaultAction = 'index';

    public $to;
    public $from;
    public $database = false;
    public $assets = false;
    public $bundle = false;
    public $newEnvVars = false;
    public $wait = false;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        return array_merge($options, [
            'to',
            'from',
            'database',
            'assets',
            'bundle',
            'newEnvVars',
            'servdSlug',
            'servdKey',
            'verbose',
            'wait'
        ]);
    }

    public function optionAliases()
    {
        $a = parent::optionAliases();
        return array_merge($a, [
            't' => 'to',
            'f' => 'from',
            'd' => 'database',
            'a' => 'assets',
            'b' => 'bundle',
            'ev' => 'newEnvVars',
            'sk' => 'servdSlug',
            'ss' => 'servdKey',
            'v' => 'verbose',
            'w' => 'wait'
        ]);
    }

    /******************
     * Actions
     *****************/

    public function actionIndex()
    {
        $this->outputDebug("Checking a 'from' environment has been set");
        $exit = $this->requireFrom();
        if ($exit != ExitCode::OK) {
            $this->outputDebug("Exiting");
            return $exit;
        }

        $this->outputDebug("Checking a 'to' environment has been set");
        $exit = $this->requireTo();
        if ($exit != ExitCode::OK) {
            $this->outputDebug("Exiting");
            return $exit;
        }

        $this->outputDebug("Checking a 'database' option has been set");
        $exit = $this->requireDatabase();
        if ($exit != ExitCode::OK) {
            $this->outputDebug("Exiting");
            return $exit;
        }

        $this->outputDebug("Checking a 'assets' option has been set");
        $exit = $this->requireAssets();
        if ($exit != ExitCode::OK) {
            $this->outputDebug("Exiting");
            return $exit;
        }

        $this->outputDebug("Checking a 'bundle' option has been set");
        $exit = $this->requireBundle();
        if ($exit != ExitCode::OK) {
            $this->outputDebug("Exiting");
            return $exit;
        }

        $this->outputDebug("Checking a 'newEnvVars' option has been set");
        $exit = $this->requireNewEnvVars();
        if ($exit != ExitCode::OK) {
            $this->outputDebug("Exiting");
            return $exit;
        }

        // One clone option needs to be selected
        $this->outputDebug("Checking if one clone option is selected");
        if (!$this->database && !$this->assets && !$this->bundle && !$this->newEnvVars) {
            $this->outputDebug("Exiting");
            $this->stderr('No clone options were selected. At least one of the database, assets, bundle or newEnvVar options needs to be selected.' . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        // Test servd key and secret
        if (!$this->checkServdCreds()) {
            return ExitCode::CONFIG;
        }

        $this->stdout('Starting import from ' . $this->from . ' to ' . $this->to . PHP_EOL, Console::FG_GREEN);

        $guz = Craft::createGuzzleClient();
        $result = $guz->post($this->baseRunnerDomain . "/create-task", [
            'json' => [
                'task' => 'clone_environment',
                'project_slug' => $this->servdSlug,
                'token' => $this->servdKey,
                'task_data' => [
                    'from' => $this->from,
                    'to' => $this->to,
                    'database' => $this->database,
                    'assets' => $this->assets,
                    'bundle' => $this->bundle,
                    'envVars' => $this->newEnvVars
                ]
            ]
        ]);
        $body = json_decode((string) $result->getBody(), true);

        if (empty($body)) {
            throw new Exception("Error whilst creating a clone environment task");
        }

        if ($body['status'] != 'success') {
            throw new Exception("Error whilst creating a clone environment task: " . $body['message']);
        }

        if (boolval($this->wait)) {
            $this->pollUntilTaskFinished($this->servdSlug, $body['uuid'], $this->servdKey);
        }

        return ExitCode::OK;
    }

    /******************
     * Private Functions
     *****************/

    private function requireTo()
    {
        //Check --to is set properly
        if (empty($this->to)) {
            if ($this->interactive) {
                $this->to = $this->select('Which environment would you like to import to?', [
                    'development' => 'Development',
                    'staging' => 'Staging',
                    'production' => 'Production'
                ]);
            } else {
                $this->stderr('--to must be set to a target environment. [development|staging|production]' . PHP_EOL, Console::FG_RED);
                return ExitCode::USAGE;
            }
        } else {
            if (!in_array($this->to, ['development', 'staging', 'production'], true)) {
                $this->stderr('--to must be set to a target environment. [development|staging|production]' . PHP_EOL, Console::FG_RED);
                return ExitCode::USAGE;
            }
        }
        return ExitCode::OK;
    }

    private function requireFrom()
    {
        if (empty($this->from)) {
            if ($this->interactive) {
                $this->from = $this->select('Which environment would you like to import from?', [
                    'development' => 'Development',
                    'staging' => 'Staging',
                    'production' => 'Production',
                ]);
            } else {
                $this->stderr('--from must be set to a target environment. [development|staging|production]' . PHP_EOL, Console::FG_RED);
                return ExitCode::USAGE;
            }
        } else {
            if (!in_array($this->from, ['development', 'staging', 'production'], true)) {
                $this->stderr('--from must be set to a target environment. [development|staging|production]' . PHP_EOL, Console::FG_RED);
                return ExitCode::USAGE;
            }
        }
        return ExitCode::OK;
    }

    private function requireDatabase()
    {
        if (empty($this->database) && $this->interactive) {
            $this->database = $this->confirm('Import the database?');
        }
        return ExitCode::OK;
    }

    private function requireAssets()
    {
        if (empty($this->assets) && $this->interactive) {
            $this->assets = $this->confirm('Import the assets?');
        }
        return ExitCode::OK;
    }

    private function requireBundle()
    {
        if (empty($this->bundle) && $this->interactive) {
            $this->bundle = $this->confirm('Import and deploy the selected bundle?');
        }
        return ExitCode::OK;
    }

    private function requireNewEnvVars()
    {
        if (empty($this->newEnvVars) && $this->interactive) {
            $this->newEnvVars = $this->confirm('Import any new environment variables?');
        }
        return ExitCode::OK;
    }
}
