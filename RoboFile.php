<?php

require_once 'vendor/autoload.php';
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = \Dotenv\Dotenv::create(__DIR__);
    $dotenv->load();
}

class RoboFile extends \Robo\Tasks
{

    use \Robo\Task\Development\loadTasks;
    use \Robo\Common\TaskIO;
    protected $config;

    public function __construct()
    {
        foreach ($_ENV as $option => $value) {
            if (substr($option, 0, 3) === 'OC_') {
                $option                = strtolower(substr($option, 3));
                $this->config[$option] = $value;
            }
        }
        $required = array('db_username', 'password', 'email', 'http_server');
        $missing  = array();
        foreach ($required as $config) {
            if (empty($this->config[$config])) {
                $missing[] = 'OC_' . strtoupper($config);
            }
        }
        if (!empty($missing)) {
            $this->printTaskError("<error> Missing " . implode(', ', $missing));
            $this->printTaskError("<error> See .env.sample ");
            die();
        }
    }

    public function setup()
    {
        $this->taskDeleteDir('www')->run();
        $this->taskFileSystemStack()
            ->mirror('vendor/opencart/opencart/upload', 'www')
            ->chmod('www', 0777, 0000, true)
        // ->touch('www/config.php')
        // ->touch('www/admin/config.php')
            ->run();
        $this->_exec(sprintf('mysql -u %1$s -p%2$s -Nse \'show tables\' %3$s | while read table; do mysql -u %1s -p%2$s -e "drop table $table" %3$s; done', $this->config['db_username'], $this->config['db_password'], $this->config['db_database']));
        $install = $this->taskExec('php')->arg('www/install/cli_install.php')->arg('install');
        foreach ($this->config as $option => $value) {
            $install->option($option, $value);
        }
        $install->run();
        $this->taskDeleteDir('www/install')->run();

        // $this->taskFileSystemStack()
        //     ->mirror('vqmod', 'www/vqmod')
        //     ->run();

        // $this->taskFileSystemStack()
        //     ->mirror('vqmod_manager/upload', 'www')
        //     ->run();

    }

    public function setuptest()
    {
        $this->taskDeleteDir('tests/phpcs')->run();
        $this->taskFileSystemStack()
            ->mirror('vendor/opencart/opencart/tests/phpcs', 'tests/phpcs')->run();
    }

    public function test()
    {
        if (!file_exists(__DIR__ . '/tests/phpcs/OpenCart/ruleset.xml')) {
            $this->setuptest();
        }
        $this->taskExec('phpcs')->arg('src')->arg('--standard=' . __DIR__ . '/tests/phpcs/OpenCart/ruleset.xml')->run();
    }

    public function server()
    {
        $port = parse_url($this->config['http_server'], PHP_URL_PORT);
        $port = (is_null($port)) ? 80 : $port;
        $host = parse_url($this->config['http_server'], PHP_URL_HOST);
        $this->taskServer($port)
            ->host($host)
            ->dir('www')
            ->run();
    }

    public function watch()
    {
        $this->dev();
        $this->taskWatch()
            ->monitor('composer.json', function () {
                $this->taskComposerUpdate()->run();
                $this->dev();
            })->monitor('src/', function () {
            $this->dev();
        })->run();
    }

    public function dev()
    {
        $this->taskFileSystemStack()->mirror('src/upload', 'www')->run();
    }

    public function copy()
    {
        // $this->taskCopyDir(['src/upload' => 'www'])->overrite()->run();
        $this->_exec('yes | cp -rf src/upload/* www');
    }

    private function depver($dep)
    {
        if ($composerLock = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'composer.lock')) {
            $json = json_decode($composerLock);
            foreach ($json->packages as $package) {
                if ($package->name === $dep) {
                    return $package->version;
                }
            }
            return "Not Found";
        }
        return "Not Installed";
    }

    public function build()
    {
        $this->taskDeleteDir('build')->run();
        $this->taskFileSystemStack()->mirror('src', 'build')->run();
    }

    public function clear()
    {
        $this->taskDeleteDir('build')->run();
        $this->taskDeleteDir('www')->run();
        $this->taskDeleteDir('bin')->run();
    }

    public function link()
    {

    }

    public function zip()
    {
        $this->_exec('zip -r  db-map-places.ocmod.zip build/upload/*');
    }

    public function vqmod()
    {
        $this->taskFileSystemStack()
            ->mirror('vqmod', 'www/vqmod')
            ->run();
    }
}