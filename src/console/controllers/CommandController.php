<?php

namespace servd\AssetStorage\console\controllers;

use Craft;
use craft\helpers\Console;
use yii\console\ExitCode;
use craft\console\Controller;
use Exception;

class CommandController extends Controller
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
            'environment',
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
            'e' => 'environment',
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
        $this->command = $this->command ?? implode(' ', func_get_args());

        $this->outputDebug("Checking a command has been provided");
        $exit = $this->requireCommand();
        if ($exit != ExitCode::OK) {
            $this->outputDebug("Exiting");
            return $exit;
        }

        $this->outputDebug("Checking an environment has been set");
        $exit = $this->requireEnvironment();
        if ($exit != ExitCode::OK) {
            $this->outputDebug("Exiting");
            return $exit;
        }

        // Test servd key and secret
        if (!$this->checkServdCreds()) {
            return ExitCode::CONFIG;
        }

        $this->command = trim(str_replace('./craft ', '', $this->command));

        if ($this->interactive && !$this->confirm('Ready to run "./craft '. $this->command . '" on ' . $this->environment . '. Do you want to proceed?')) {
            $this->outputDebug("Exiting");
            return ExitCode::OK;
        }

        $this->stdout('Running "./craft '. $this->command . '" on ' . $this->environment . PHP_EOL, Console::FG_GREEN);

        $http = Craft::createGuzzleClient();
        $result = $http->post($this->baseRunnerDomain . "/create-task", [
            'json' => [
                'task' => 'run_a_command',
                'project_slug' => $this->servdSlug,
                'token' => $this->servdKey,
                'task_data' => [
                    'environment' => $this->environment,
                    'command' => $this->command,
                ]
            ]
        ]);
        $body = json_decode((string) $result->getBody(), true);

        if (empty($body)) {
            throw new Exception("Error whilst creating a command task");
        }

        if ($body['status'] != 'success') {
            throw new Exception("Error whilst creating a command task: " . $body['message']);
        }

        if (boolval($this->wait)) {
            $this->pollAndPrintOutput($body['uuid']);
            $this->stdout('Command complete' . PHP_EOL, Console::FG_GREEN);
        } else {
            $this->stdout('Command successfully triggered' . PHP_EOL, Console::FG_GREEN);
        }

        return ExitCode::OK;
    }

    /******************
     * Private Functions
     *****************/

    private function requireCommand()
    {
        if ($this->interactive && empty($this->command)) {
            $this->command = $this->stdin('What Craft command would you like to run?');
        }
        if (empty($this->command)) {
            $this->stderr('A command must be provided as the first argument' . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        return ExitCode::OK;
    }

    private function requireEnvironment()
    {
        if (empty($this->environment)) {
            if ($this->interactive) {
                $this->environment = $this->select('Which environment would you like to run the command on?', [
                    'development' => 'Development',
                    'staging' => 'Staging',
                    'production' => 'Production'
                ]);
            } else {
                $this->stderr('--environment must be set to a valid environment. [development|staging|production]' . PHP_EOL, Console::FG_RED);
                return ExitCode::USAGE;
            }
        } else {
            if (!in_array($this->environment, ['development', 'staging', 'production'], true)) {
                $this->stderr('--environment must be set to a valid environment. [development|staging|production]' . PHP_EOL, Console::FG_RED);
                return ExitCode::USAGE;
            }
        }
        return ExitCode::OK;
    }

    private function pollAndPrintOutput($taskId)
    {
        sleep(2);
        $ready = false;
        $count = 0;
        while (!$ready && $count < 300) {
            $http = Craft::createGuzzleClient();
            $result = $http->post($this->baseRunnerDomain . '/get-task', [
                'json' => [
                    "project_slug" => $this->servdSlug,
                    "token" => $this->servdKey,
                    "uuid" => $taskId
                ]
            ]);
            $body = json_decode((string)$result->getBody(), true);
            $this->outputDebug(var_export($body['task'], true));

            if ($body['task'] != null && $body['task']['result'] != null) {
                $stdout = $body['task']['result']['stdout'];
                if ($stdout) {
                    $this->stdout($stdout);
                }
                $stderr = $body['task']['result']['stderr'];
                if ($stderr) {
                    $this->stderr($stderr . PHP_EOL, Console::FG_RED);
                }
            }

            if ($body['task']['status'] == 'complete') {
                $ready = true;
            } else {
                sleep(2);
                $count++;
            }
        }

        if (!$ready) {
            $this->stderr("Gave up waiting for the task runner to respond" . PHP_EOL, Console::FG_RED);
            return false;
        }
        return true;
    }
}
