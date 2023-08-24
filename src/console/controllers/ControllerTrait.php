<?php

namespace servd\AssetStorage\console\controllers;

use Craft;
use craft\helpers\Console;
use servd\AssetStorage\Plugin;

trait ControllerTrait
{
    public $verbose = false;
    public $servdSlug;
    public $servdKey;

    protected $baseServdDomain = 'https://app.servd.host';
    protected $baseRunnerDomain = 'https://runner.servd.host';

    protected function outputDebug($message)
    {
        if($this->verbose){
            $this->stdout($message . PHP_EOL);
        }
    }

    protected function pollUntilTaskFinished($slug, $taskId, $securityKey, $maxWait = 600)
    {
        sleep(2);
        $ready = false;
        $count = 0;
        while (!$ready && $count < $maxWait / 2) {
            $guz = Craft::createGuzzleClient();
            $result = $guz->post($this->baseRunnerDomain . '/get-task', [
                'json' => [
                    "project_slug" => $slug,
                    "token" => $securityKey,
                    "uuid" => $taskId
                ]
            ]);
            $body = json_decode((string)$result->getBody(), true);
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

    protected function checkServdCreds()
    {
        //Ensure the project has access to some Servd credentials
        //If the project is installed these might be in the plugin settings

        if (empty($this->servdSlug) || empty($this->servdKey)) {
            if (Craft::$app->getIsInstalled(true)) {
                $settings = Plugin::$plugin->getSettings();
                $this->servdSlug = $settings->getProjectSlug();
                $this->servdKey = $settings->getSecurityKey();
            }
        }

        //If not they might be in environment variables
        if (empty($this->servdSlug) || empty($this->servdKey)) {
            $this->servdSlug = getenv('SERVD_PROJECT_SLUG');
            $this->servdKey = getenv('SERVD_SECURITY_KEY');
        }

        //If neither we can ask for them
        if (empty($this->servdSlug) || empty($this->servdKey)) {
            $this->stdout('Could not reliably determine a Servd project slug and security key automatically.' . PHP_EOL, Console::FG_YELLOW);

            if (!$this->interactive) {
                //No way to determine Servd creds
                $this->stderr('Please use the --servdSlug and --servdKey flags to provide authentication credentials.' . PHP_EOL, Console::FG_RED);
                return false;
            }

            $this->servdSlug = $this->prompt('Servd project slug:', [
                'default' => $this->servdSlug ?: null,
                'validator' => function (string $input): bool {
                    if (empty($input)) {
                        $this->stderr('Please supply a project slug.' . PHP_EOL, Console::FG_RED);
                        return false;
                    }
                    return true;
                },
            ]);

            $this->servdKey = $this->prompt('Servd project security key:', [
                'default' => $this->servdKey ?: null,
                'validator' => function (string $input): bool {
                    if (empty($input)) {
                        $this->stderr('Please supply a security key.' . PHP_EOL, Console::FG_RED);
                        return false;
                    }
                    return true;
                },
            ]);
        } else {
            $this->stdout('Servd project slug and key found' . PHP_EOL, Console::FG_GREEN);
        }

        return true;
    }
}