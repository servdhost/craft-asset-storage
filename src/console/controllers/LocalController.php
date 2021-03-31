<?php

namespace servd\AssetStorage\console\controllers;

use Aws\S3\BatchDelete;
use Aws\S3\S3Client;
use Composer\Util\Platform;
use Craft;
use craft\console\Controller;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\App;
use craft\helpers\Console;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use servd\AssetStorage\Plugin;
use yii\console\ExitCode;
use mikehaertl\shellcommand\Command as ShellCommand;
use craft\errors\ShellCommandException;
use craft\helpers\FileHelper;
use craft\volumes\Local;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use servd\AssetStorage\Volume;

class LocalController extends Controller
{

    public $defaultAction = 'index';

    public $to;
    public $from;
    public $servdSlug;
    public $servdKey;
    public $skipBackup = false;

    private $leaveOpen = true;
    private $baseServdDomain = 'https://app.servd.host';
    private $baseRunnerDomain = 'https://runner.servd.host';

    const S3_BUCKET = 'cdn-assets-servd-host';

    public function options($actionID)
    {
        $options = parent::options($actionID);
        return array_merge($options, [
            'to',
            'from',
            'servdSlug',
            'servdKey',
            'skipBackup',
        ]);
    }

    public function optionAliases()
    {
        $a = parent::optionAliases();
        return array_merge($a, [
            't' => 'to',
            'f' => 'from',
            'sk' => 'servdSlug',
            'ss' => 'servdKey',
            'sb' => 'skipBackup',
        ]);
    }

    /******************
     * Actions
     *****************/

    public function actionIndex()
    {
        $this->stdout("Servd Local Dev\n\n", Console::BOLD);
        $this->stdout("The following commands allow you to sync your database and assets between your local and remote environments.\n\n");
    }

    /**
     * Pulls a database dump from a remote Servd environment into the local database.
     */
    public function actionPullDatabase()
    {
        if (!$this->checkOnlyLocal()) {
            return ExitCode::USAGE;
        }

        $exit = $this->requireFrom();
        if ($exit != 0) {
            return $exit;
        }

        //Test servd key and secret
        if (!$this->checkServdCreds()) return ExitCode::CONFIG;
        //Test local database connection details
        if (!$this->checkLocalDBCreds()) return ExitCode::CONFIG;

        //Enable external database access on Servd
        $remoteConfig = $this->enableRemoteDatabaseConnectivity($this->from);
        if ($remoteConfig === false) {
            return ExitCode::UNSPECIFIED_ERROR;
        }
        extract($remoteConfig);
        extract($this->getLocalDatabaseConnectionDetails());

        if (!$this->skipBackup) {
            //Backup the local database
            $this->backupDatabase();
        }

        //Final confirmation
        if ($this->interactive && !$this->confirm('Ready to import. Do you want to proceed?')) {
            $this->revertRemoteDatabaseConnectivity();
            return ExitCode::OK;
        }

        $this->stdout('Starting streaming database import' . PHP_EOL);

        $skipColStat = '';

        // Find out if mysqldump supports column-statistics
        $shellCommand = new ShellCommand();

        if (Platform::isWindows()) {
            $shellCommand->setCommand('mysqldump --help | findstr "column-statistics"');
        } else {
            $shellCommand->setCommand('mysqldump --help | grep "column-statistics"');
        }

        if (!function_exists('proc_open') && function_exists('exec')) {
            $shellCommand->useExec = true;
        }

        $success = $shellCommand->execute();

        // if there was output, then they're running mysqldump 8.x against a 5.x database.
        if ($success && $shellCommand->getOutput()) {
            $skipColStat .= ' --skip-column-statistics';
        }

        //Perform a direct stream from the remote db into the local
        $command = "mysqldump $skipColStat --no-tablespaces --add-drop-table --quick --single-transaction --compress -h $remoteHost --port $remotePort -u $remoteUser -p\"$remotePassword\" $remoteDatabase | mysql -h $localHost --port $localPort -u $localUser -p\"$localPassword\" $localDatabase";
        $this->runCommand($command);

        //Close external database access on Servd
        $this->revertRemoteDatabaseConnectivity();
        $this->stdout("Database pull complete." . PHP_EOL, Console::FG_GREEN);
    }

    /**
     * Pushes a local database dump to a remote Servd environment.
     */
    public function actionPushDatabase()
    {
        if (!$this->checkOnlyLocal()) {
            return ExitCode::USAGE;
        }

        $exit = $this->requireTo();
        if ($exit != 0) {
            return $exit;
        }

        //Test servd key and secret
        if (!$this->checkServdCreds()) return ExitCode::CONFIG;
        //Test local database connection details
        if (!$this->checkLocalDBCreds()) return ExitCode::CONFIG;

        //Enable external database access on Servd
        $remoteConfig = $this->enableRemoteDatabaseConnectivity($this->to);
        if ($remoteConfig === false) {
            return ExitCode::UNSPECIFIED_ERROR;
        }
        extract($remoteConfig);
        extract($this->getLocalDatabaseConnectionDetails());

        //Final confirmation
        if ($this->interactive && !$this->confirm('Ready to export. Do you want to proceed?')) {
            $this->revertRemoteDatabaseConnectivity();
            return ExitCode::OK;
        }

        $this->stdout('Starting streaming database export' . PHP_EOL);

        //Perform a direct stream from the remote db into the local
        $command = "mysqldump --no-tablespaces --add-drop-table --quick --single-transaction -h $localHost --port $localPort -u $localUser -p\"$localPassword\" $localDatabase | mysql --compress -h $remoteHost --port $remotePort -u $remoteUser -p\"$remotePassword\" $remoteDatabase";
        $this->runCommand($command);

        //Close external database access on Servd
        $this->revertRemoteDatabaseConnectivity();
        $this->stdout("Database push complete." . PHP_EOL, Console::FG_GREEN);
    }

    /**
     * Syncs a remote env on the Servd Asset Platform with the 'local' env.
     */
    public function actionPullAssets()
    {
        if (!$this->checkOnlyLocal()) {
            return ExitCode::USAGE;
        }

        $exit = $this->requireFrom();
        if ($exit != 0) {
            return $exit;
        }

        $useLocalVolumes = false;
        if (Craft::$app->getIsInstalled(true)) {
            $settings = Plugin::$plugin->getSettings();
            $useLocalVolumes = $settings->useLocalVolumes;
        }

        $this->checkServdCreds();

        if ($this->interactive && !$this->confirm('Ready. This will replace all of your existing \'local\' assets. Continue?')) {
            return ExitCode::OK;
        }

        if ($useLocalVolumes) {
            //If the plugin is set up to use local volumes we need to pull all of the assets down into those
            $this->stdout("Starting assets sync from '$this->from' to local filesystem" . PHP_EOL);
            $query = (new Query())
                ->select([
                    'id', 'name', 'handle', 'url', 'type', 'settings', 'uid'
                ])
                ->from([Table::VOLUMES])
                ->orderBy(['sortOrder' => SORT_ASC]);
            $query->where(['type' => Volume::class]);
            $results = $query->all();

            foreach ($results as $v) {
                $this->stdout("Syncing volume '" . $v['handle'] . "'" . PHP_EOL);
                $s = json_decode($v['settings'], true);
                $remotePath = "s3://cdn-assets-servd-host/$this->servdSlug/$this->from/" . $s['customSubfolder'];
                $localPath = FileHelper::normalizePath(Craft::parseEnv("@webroot/servd-volumes/" . $v['handle'] . '/'));
                if (!is_dir($localPath)) {
                    mkdir($localPath, 0775, true);
                }
                $this->syncS3Down($remotePath, $localPath, "$this->servdSlug/$this->from/" . $s['customSubfolder']);
            }
            $this->stdout("Sync complete." . PHP_EOL, Console::FG_GREEN);
        } else {
            //If not, we only need to perform a clone from one remote directory into another
            $this->stdout("Starting assets clone task from '$this->from' to 'local'" . PHP_EOL);
            $result = $this->cloneAssets($this->from, 'local');
            if (!$result) {
                $this->stderr("Clone task failed or timed out. Please check the Servd dashboard for task logs." . PHP_EOL, Console::FG_RED);
            } else {
                $this->stdout("Clone complete." . PHP_EOL, Console::FG_GREEN);
            }
        }
    }

    /**
     * Syncs the 'local' env on the Servd Asset Platform with a remote env.
     */
    public function actionPushAssets()
    {
        if (!$this->checkOnlyLocal()) {
            return ExitCode::USAGE;
        }

        $exit = $this->requireTo();
        if ($exit != 0) {
            return $exit;
        }

        $useLocalVolumes = false;
        if (Craft::$app->getIsInstalled(true)) {
            $settings = Plugin::$plugin->getSettings();
            $useLocalVolumes = $settings->useLocalVolumes;
        }

        $this->checkServdCreds();

        if ($this->interactive && !$this->confirm("Ready. This will replace all of your existing '$this->to' assets. Continue?")) {
            return ExitCode::OK;
        }

        if ($useLocalVolumes) {
            //If the plugin is set up to use local volumes we need to pull all of the assets down into those
            $this->stdout("Starting assets sync from local filesystem to '$this->to'" . PHP_EOL);
            $query = (new Query())
                ->select([
                    'id', 'name', 'handle', 'url', 'type', 'settings', 'uid'
                ])
                ->from([Table::VOLUMES])
                ->orderBy(['sortOrder' => SORT_ASC]);
            $query->where(['type' => Volume::class]);
            $results = $query->all();

            foreach ($results as $v) {
                $this->stdout("Syncing volume '" . $v['handle'] . "'" . PHP_EOL);
                $s = json_decode($v['settings'], true);
                $remotePath = "s3://cdn-assets-servd-host/$this->servdSlug/$this->to/" . $s['customSubfolder'];
                $localPath = FileHelper::normalizePath(Craft::parseEnv("@webroot/servd-volumes/" . $v['handle'] . '/'));
                if (!is_dir($localPath)) {
                    mkdir($localPath, 0775, true);
                }
                $this->syncS3Up($localPath, $remotePath, "$this->servdSlug/$this->to/" . $s['customSubfolder']);
                $this->stdout("Sync complete." . PHP_EOL, Console::FG_GREEN);
            }
        } else {
            //If not, we only need to perform a clone from one remote directory into another
            $this->stdout("Starting assets clone task from 'local' to '$this->to'" . PHP_EOL);
            $result = $this->cloneAssets('local', $this->to);
            if (!$result) {
                $this->stderr("Clone task failed or timed out. Please check the Servd dashboard for task logs." . PHP_EOL, Console::FG_RED);
            } else {
                $this->stdout("Clone complete." . PHP_EOL, Console::FG_GREEN);
            }
        }
    }


    /******************
     * Private Functions
     *****************/

    private function checkOnlyLocal()
    {
        $ok = !in_array(getenv('ENVIRONMENT'), ['staging', 'production']);
        if (!$ok) {
            $this->stderr("You should only run local dev commands in a local dev environment." . PHP_EOL, Console::FG_RED);
        }
        return $ok;
    }

    private function requireTo()
    {
        //Check --to is set properly
        if (empty($this->to)) {
            if ($this->interactive) {
                $this->to = $this->select('Which environment would you like to push to?', [
                    'staging' => 'Staging',
                    'production' => 'Production'
                ]);
            } else {
                $this->stderr('--to must be set to a target environment. [staging|production]' . PHP_EOL, Console::FG_RED);
                return ExitCode::USAGE;
            }
        } else {
            if (!in_array($this->to, ['staging', 'production'], true)) {
                $this->stderr('--to must be set to a target environment. [staging|production]' . PHP_EOL, Console::FG_RED);
                return ExitCode::USAGE;
            }
        }
        return 0;
    }

    private function requireFrom()
    {
        //Check --from is set properly
        if (empty($this->from)) {
            if ($this->interactive) {
                $this->from = $this->select('Which environment would you like to pull from?', [
                    'staging' => 'Staging',
                    'production' => 'Production'
                ]);
            } else {
                $this->stderr('--from must be set to a target environment. [staging|production]' . PHP_EOL, Console::FG_RED);
                return ExitCode::USAGE;
            }
        } else {
            if (!in_array($this->from, ['staging', 'production'], true)) {
                $this->stderr('--from must be set to a target environment. [staging|production]' . PHP_EOL, Console::FG_RED);
                return ExitCode::USAGE;
            }
        }
        return 0;
    }

    private function checkServdCreds()
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
            $this->stdout('Servd project slug and key found' . PHP_EOL);
        }

        return true;
    }

    private function checkLocalDBCreds()
    {
        $dbConfig = App::dbConfig();
        if ($dbConfig['driverName'] != 'mysql') {
            $this->stderr('Only mysql databases can be synced to and from Servd' . PHP_EOL);
            return false;
        }
        $dbValid = Craft::$app->getIsDbConnectionValid();
        if (!$dbValid) {
            $this->stderr('There was a problem connecting to the local database' . PHP_EOL);
            return false;
        }
        return true;
    }


    private function enableRemoteDatabaseConnectivity($environment)
    {

        $this->stdout('Enabling remote database connectivity' . PHP_EOL, Console::FG_GREEN);

        //localhost/enable-remote-database-connectivity
        $guz = Craft::createGuzzleClient();
        $result = $guz->post($this->baseServdDomain . '/enable-remote-database-connectivity', [
            'json' => [
                "slug" => $this->servdSlug,
                "key" => $this->servdKey,
                "environment" => $environment
            ]
        ]);

        $body = json_decode((string)$result->getBody(), true);

        if (empty($body)) {
            throw new Exception("Error whilst contacting Servd to enable database access");
        }

        if ($body['status'] != 'success') {
            $this->stderr('Error whilst contacting Servd to enable database access' . PHP_EOL, Console::FG_RED);
            $this->stderr($body['message'] . PHP_EOL, Console::FG_RED);
            if (isset($body['errors'])) {
                array_walk($body['errors'], function ($el, $key) {
                    $this->stderr($key . ':' . PHP_EOL, Console::FG_RED);
                    foreach ($el as $m) {
                        $this->stderr($m . PHP_EOL, Console::FG_RED);
                    }
                    $this->stderr(PHP_EOL);
                });
            }
            return false;
        }

        $this->leaveOpen = $body['leave-open'];

        if (!empty($body['task-id'])) {
            //Poll for success
            $ready = $this->pollUntilTaskFinished($this->servdSlug, $body['task-id'], $body['security-token'], 100);
            if (!$ready) {
                return false;
            }
        }

        return [
            'remoteHost' => $body['host'],
            'remotePort' => $body['port'],
            'remoteUser' => $body['user'],
            'remotePassword' => $body['password'],
            'remoteDatabase' => $body['database'],
        ];
    }

    private function revertRemoteDatabaseConnectivity()
    {
        //Do nothing if it was open anyway
        if ($this->leaveOpen) {
            return;
        }

        $this->stdout('Reverting remote database connectivity' . PHP_EOL, Console::FG_GREEN);

        $guz = Craft::createGuzzleClient();
        $result = $guz->post($this->baseServdDomain . '/disable-remote-database-connectivity', [
            'json' => [
                "slug" => $this->servdSlug,
                "key" => $this->servdKey,
            ]
        ]);
    }

    private function getLocalDatabaseConnectionDetails()
    {
        $dbConfig = App::dbConfig();
        $dsn = $dbConfig['dsn'];
        return [
            'localHost' => Db::parseDsn($dsn, 'host'),
            'localPort' => empty(Db::parseDsn($dsn, 'port')) ? '3306' : Db::parseDsn($dsn, 'port'),
            'localUser' => $dbConfig['username'],
            'localPassword' => $dbConfig['password'],
            'localDatabase' => Db::parseDsn($dsn, 'dbname'),
        ];
    }

    private function backupDatabase()
    {
        $this->stdout('Starting local database backup' . PHP_EOL, Console::FG_GREEN);
        $path = Craft::$app->getDb()->backup();
        $this->stdout("Local database backed up to $path" . PHP_EOL);
    }

    private function runCommand($command)
    {
        $shellCommand = new ShellCommand();
        $shellCommand->setCommand($command);
        // If we don't have proc_open, maybe we've got exec
        if (!function_exists('proc_open') && function_exists('exec')) {
            $shellCommand->useExec = true;
        }

        $success = $shellCommand->execute();
        if (!$success) {
            $this->stderr($shellCommand->getStdErr() . PHP_EOL, Console::FG_RED);
        } else {
            $this->stdout($shellCommand->getOutput() . PHP_EOL);
        }

        return $success;
    }

    private function pollUntilTaskFinished($slug, $taskId, $securityKey, $maxWait = 600)
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
            $this->stderr("Gave up waiting for remote database to become accessible" . PHP_EOL, Console::FG_RED);
            return false;
        }
        return true;
    }

    private function cloneAssets($from, $to)
    {
        $guz = Craft::createGuzzleClient();
        $result = $guz->post($this->baseServdDomain . '/clone-assets', [
            'json' => [
                "slug" => $this->servdSlug,
                "key" => $this->servdKey,
                "from" => $from,
                "to" => $to
            ]
        ]);
        $body = json_decode((string)$result->getBody(), true);

        if (empty($body)) {
            throw new Exception("Error whilst contacting Servd to clone assets");
        }

        if ($body['status'] != 'success') {
            $this->stderr('Error whilst contacting Servd to clone assets' . PHP_EOL, Console::FG_RED);
            $this->stderr($body['message'] . PHP_EOL, Console::FG_RED);
            if (isset($body['errors'])) {
                array_walk($body['errors'], function ($el, $key) {
                    $this->stderr($key . ':' . PHP_EOL, Console::FG_RED);
                    foreach ($el as $m) {
                        $this->stderr($m . PHP_EOL, Console::FG_RED);
                    }
                    $this->stderr(PHP_EOL);
                });
            }
            return false;
        }

        if (!empty($body['task-id'])) {
            //Poll for success
            $ready = $this->pollUntilTaskFinished($this->servdSlug, $body['task-id'], $body['security-token'], 600);
            if (!$ready) {
                return false;
            }
        }

        return true;
    }

    private function syncS3Down($source, $dest, $fullS3Prefix)
    {
        try {
            $config = Plugin::$plugin->assetsPlatform->getS3ConfigArray($this->servdSlug, $this->servdKey);
        } catch (\Exception $e) {
            $this->stderr('Failed to fetch Servd Asset Platform credentials: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
            return;
        }

        //$config['debug'] = true;
        $client = new \Aws\S3\S3Client($config);

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dest));
        $existingFiles    = [];
        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                $existingFiles[$file->getPathname()] = 1;
            }
        }

        $toDownload = $client
            ->getPaginator('ListObjects', [
                'Prefix' => $fullS3Prefix,
                'Bucket' => static::S3_BUCKET
            ])
            ->search('Contents[]');
        $toDownload = \Aws\filter($toDownload, function ($obj) {
            return substr($obj['Key'], -1, 1) !== '/'; //Remove directories
        });
        //Hijack iterator to remove from all files list so that we only end up with ones to delete
        $toDownload = \Aws\map($toDownload, function ($obj) use ($fullS3Prefix, $dest, &$existingFiles) {
            $sansPrefix = str_ireplace($fullS3Prefix, "", $obj['Key']);
            $localPath = $dest . '/' . $sansPrefix;
            unset($existingFiles[$localPath]);
            return $obj;
        });
        $toDownload = \Aws\filter($toDownload, function ($obj) use ($fullS3Prefix, $dest) {
            $sansPrefix = str_ireplace($fullS3Prefix, "", $obj['Key']);
            $localPath = $dest . '/' . $sansPrefix;
            if (!file_exists($localPath)) {
                return true;
            }
            return md5_file($localPath) != trim($obj['ETag'], '"');
        });
        $toDownload = \Aws\map($toDownload, function ($obj) {
            return "s3://cdn-assets-servd-host/" . $obj['Key'];
        });

        $manager = new \Aws\S3\Transfer($client, $toDownload, $dest, [
            'base_dir' => $source,
            'before' => function (\Aws\Command &$command) use ($client, $source, $dest, $fullS3Prefix, $existingFiles) {
                $this->stdout("Downloading " . $command['Key'] . PHP_EOL);
            }
        ]);
        $manager->transfer();

        //Delete anything that wasn't included in the S3 file list
        foreach (array_keys($existingFiles) as $file) {
            $this->stdout("Deleting " . $file . PHP_EOL, Console::FG_YELLOW);
            unlink($file);
        }
    }

    private function syncS3Up($source, $dest, $fullS3Prefix)
    {
        try {
            $config = Plugin::$plugin->assetsPlatform->getS3ConfigArray($this->servdSlug, $this->servdKey);
        } catch (\Exception $e) {
            $this->stderr($e->getMessage() . PHP_EOL, Console::FG_RED);
            return;
        }
        //$config['debug'] = true;
        $client = new \Aws\S3\S3Client($config);

        $remoteFiles = [];

        $toDelete = $client
            ->getPaginator('ListObjects', [
                'Prefix' => $fullS3Prefix,
                'Bucket' => static::S3_BUCKET
            ])
            ->search('Contents[]');
        $toDelete = \Aws\filter($toDelete, function ($obj) {
            return substr($obj['Key'], -1, 1) !== '/'; //Remove directories
        });
        //Hijack to make a list of etags
        $toDelete = \Aws\map($toDelete, function ($obj) use (&$remoteFiles) {
            $remoteFiles[$obj['Key']] = trim($obj['ETag'], '"');
            return $obj;
        });
        $toDelete = \Aws\filter($toDelete, function ($obj) use ($fullS3Prefix, $source) {
            $sansPrefix = str_ireplace($fullS3Prefix, "", $obj['Key']);
            $localPath = $source . '/' . $sansPrefix;
            return !file_exists($localPath);
        });
        $toDelete = \Aws\map($toDelete, function ($obj) {
            $this->stdout("Deleting " . $obj['Key'] . PHP_EOL, Console::FG_YELLOW);
            return $obj;
        });

        //Delete anything on the remote which is not present on the local
        $batchDelete = BatchDelete::fromIterator($client, static::S3_BUCKET, $toDelete);
        $batchDelete->delete();

        $localFileIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source));

        $localFileIterator = \Aws\filter($localFileIterator, function ($file) use ($fullS3Prefix, $source, $remoteFiles) {
            if ($file->isDir()) {
                return false;
            }
            $localFilePath = trim(str_ireplace($source, '', $file->getPathname()), "\\/");
            $s3Key = $fullS3Prefix . $localFilePath;
            return !array_key_exists($s3Key, $remoteFiles) || $remoteFiles[$s3Key] != md5_file($file->getPathname());
        });

        $manager = new \Aws\S3\Transfer($client, $localFileIterator, $dest, [
            'base_dir' => $source,
            'before' => function (\Aws\Command $command) use ($client, $source, $dest, $fullS3Prefix) {
                $this->stdout("Uploading " . $command['Key'] . PHP_EOL);
            }
        ]);
        $manager->transfer();
    }
}
