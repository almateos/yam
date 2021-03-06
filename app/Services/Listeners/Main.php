<?php
namespace Services\Listeners;

use Incube\Event\IEvent,
    Incube\Tools\Console,
    Incube\Event\AListener,
    DateTime,
    FilesystemIterator;

class Main extends AListener {

    protected static $_events = array(
        'Incube\Application\EventApplication' => array(
          'pre_main'  => 'init',
          'main'      => 'main',
          'post_main' => 'shutdown',
          ),
        );

    protected function _launchScript($name, $path, $resources) {
        Console::necho('Migrating '. $path . ' ...');
        if(file_exists($path)) {
            $functions = include_once($path);
            //$this->orm->getConnection()->beginTransaction();
            if(array_key_exists($name, $functions)) {
                $functions[$name]($resources);
            } else {
                Console::necho('function ' . $name . ' is missing');
                die;
            }
            $res = true;
        } else $res = false;
        Console::necho('');
        return $res;
    }

    protected function _create($migrationsPath, $resources) {
        if(!file_exists($migrationsPath . '/.yam')) {
            $this->_launchScript('up', $migrationsPath . '/create.php', $resources);
            file_put_contents($migrationsPath . '/.yam', 'create.php' . "\n", FILE_APPEND);
        } else {
            Console::necho('Database has already been created, please drop before re-creating again');
        }
    }

    protected function _help() {
        Console::necho('');
        Console::necho('usage: ./migration [opts] <command> [<args>]');
        Console::necho('');
        Console::necho('opts: (IMPORTANT: options are yet not implemented)');
        Console::necho('  --env     select the environment you wanna migrate in (usage: ./bin/yam --dev migrate');
        Console::necho('  -config_path=path/to/config_folder to change config folder path (default is path/to/project/app/etc');
        Console::necho('  -migrations_path=path/to/migrations_folder to change migration folder path (default is path/to/project/migrations');
        Console::necho('');
        Console::necho('command:');
        Console::necho('  list      List migration contained in the migration folder');
        Console::necho('  history   List older migrations');
        Console::necho('  migrate   Update your database to the last state (create dbs if not exists)');
        Console::necho('  create    Execute create.php::up() contained in the migration folder');
        Console::necho('  drop      Execute create.php::down() contained in the migration folder');
        Console::necho('');
    }

    protected function _listMigrations($path) {
        $migrationList = array();
        $fsIterator = new FilesystemIterator($path) ;
        foreach($fsIterator as $fileInfo) {
            list($fileName, $ext) = explode('.',$fileInfo->getFileName());
            if($fileInfo->isFile() && $ext == 'php') {
                $migrationList[] = $fileInfo->getFileName();
            }
        }
        return $migrationList;
    }

    public function init(IEvent $event) {
        //Console::necho("hello world");
        //Console::render_title("stuff:");
        //Console::necho('-lol catz');
    }

    /** @param IEvent $event */
    public function main(IEvent $event) {

        $target = $event->getTarget();

        $odm =  $target->get_resource('odm');
        $orm =  $target->get_resource('orm');
        $args = $target->get_resource('argv');
        $config = $target->get_resource('configs')->get('application');
        $cmd = array_shift($args);
        $test = array();
        $migrationsPath = $config->get('migrations_path');
        if(empty($migrationsPath)) trigger_error('Please provide migration folder path either in the config file or directly in command line', E_USER_ERROR);
        if(!file_exists($migrationsPath)) trigger_error('Migration folder not found at: ' . $migrationsPath, E_USER_ERROR);
        $migrationList = $this->_listMigrations($migrationsPath);
        $executedList = file_exists($migrationsPath . '/.yam') ? explode("\n", trim(file_get_contents($migrationsPath . '/.yam'))) : $executedList = array();

        switch($cmd) {
            // migrate to last version
            case 'migrate':
                $upToDate = false;
                if(count($executedList) === 0) $this->_create($migrationsPath, $target->get_resources());
                $param = count($args) ? array_shift($args) : null;
                switch($param) {
                    // execute next migration
                    case 'up':
                        $ms = array_diff($migrationList, $executedList);
                        if(count($ms) > 0){ 
                            $m = array_shift($ms); 
                            $this->_launchScript('up', $migrationsPath . '/' . $m, $target->get_resources());
                            file_put_contents($migrationsPath . '/.yam', $m . "\n", FILE_APPEND);
                        } else $upToDate = true;
                        break;
                    // revert last migration
                    case 'down':
                            $m = array_pop($executedList);
                            $this->_launchScript('down', $migrationsPath . '/' . $m, $target->get_resources());
                            file_put_contents($migrationsPath . '/.yam', implode("\n", $executedList) . "\n");
                            if(count($executedList) === 0 ) unlink($migrationsPath . '/.yam');
                        break;
                    // apply all migration
                    case '':
                        $ms = array_diff($migrationList, $executedList);
                        if(count($ms) > 0) { 
                            foreach($ms as $m) {
                                $this->_launchScript('up', $migrationsPath . '/' . $m, $target->get_resources());
                                file_put_contents($migrationsPath . '/.yam', $m . "\n", FILE_APPEND);
                            }
                        } else $upToDate = true;
                        break;
                    default:
                        Console::necho('Invalid args: ' . $param);
                        $this->_help();
                            //if(isset
                }
                if($upToDate) Console::necho('Already up to date, nothing to migrate');
                break;
            case 'list':
                foreach($migrationList as $m) {
                    $data = explode('_', $m);
                    if(count($data) === 2) {
                        list($timestamp, $migrationName) = $data;
                        $date = new DateTime();
                        $date->setTimestamp($timestamp);
                        Console::necho('# ' . $date->format('Y-m-d H:i') . ' -> ' . $migrationName);
                    }
                }
                break;
            case 'history':
                if(count($executedList) === 0) {
                    Console::necho('No history');
                } else {
                    foreach($executedList as $m) {
                        $data = explode('_', $m);
                        if(count($data) === 2) {
                            list($timestamp, $migrationName) = $data;
                            $date = new DateTime();
                            $date->setTimestamp($timestamp);
                            Console::necho('- ' . $date->format('Y-m-d H:i') . ' -> ' . $migrationName);
                        } else {
                            Console::necho('- ' . $m);
                        }
                    }
                }
                break;
            case 'status':
            case 'current':
                if(count($executedList) > 0) {
                    $data = end($executedList);
                    $data = explode('_', $data);
                    if(count($data) > 1) {
                        list($timestamp, $migrationName) = $data;
                        $date = new DateTime();
                        $date->setTimestamp($timestamp);
                        $dateStr = $date->format('Y-m-d H:i') . ' -> ' ;
                    } else { 
                        $migrationName = array_shift($data);
                        $dateStr = '';
                    }
                    Console::necho('Current revision: ' . $dateStr . $migrationName);
                } else {
                    Console::necho('No revision');
                }
                break;
            case 'create':
                $this->_create($migrationsPath, $target->get_resources());
                break;
            case 'drop':
                if(file_exists($migrationsPath . '/.yam')) {
                    $this->_launchScript('down', $migrationsPath . '/create.php', $target->get_resources());
                    unlink($migrationsPath . '/.yam');
                    Console::necho($migrationsPath . '/.yam has been droped');
                } else {
                    Console::necho('No revision file found (.yam is absent from the migration folder)');
                }
                break;
            case 'new':
                $param = count($args) ? array_shift($args) : null;
                if(is_null($param)) {
                    Console::necho('Please provide the name of migration');
                } else {
                    $date = new DateTime();
                    $content = file_get_contents(ROOT_PATH . '/app/example_migration.php');
                    $filename = $migrationsPath . '/' . $date->getTimestamp() . '_' . $param . '.php';
                    file_put_contents($filename, $content);
                }

                break;
            default:
                $this->_help();
        }
    }

    public function shutdown(IEvent $event) {
        //Console::necho('Shutdown ...');
    }
}
