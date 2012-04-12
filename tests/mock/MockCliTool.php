<?php

namespace tests\php\manager\crontab\mock;

use php\manager\crontab\CliTool;


require_once dirname(dirname(__DIR__)) . '/src/CliTool.php';
require_once __DIR__ . '/MockCrontabManager.php';

class MockCliTool extends CliTool
{
    public static $out = array();
    public static $err = array();
    /**
     * @var MockCrontabManager
     */
    public $manager;

    public function __construct()
    {
        $_SERVER['argv'][0] = 'cronman';
        parent::__construct();
        $this->_createManager();
    }

    protected function _createManager()
    {
        if ($this->manager) {
            return $this->manager;
        }
        $content = file_get_contents(dirname(__DIR__) . '/resources/cronfile.txt');

        $this->manager = new MockCrontabManager();
        $this->manager->setInitialContents($content);
        $this->manager->crontab = 'php ' . __DIR__ . '/crontab.php';
        $this->manager->user = null;
        return $this->manager;
    }

    protected function _out($message)
    {
        array_push(self::$out, $message);
    }

    protected function _err($message)
    {
        array_push(self::$err, $message);
    }

    /**
     * @param null|string $sudo
     */
    public function setSudo($sudo)
    {
        $this->_sudo = $sudo;
    }

    /**
     * @param string $targetFile
     */
    public function setTargetFile($targetFile)
    {
        $this->_targetFile = $targetFile;
    }

    private static $instance;

    /**
     * @static
     * @return MockCliTool
     */
    protected static function _instantinate()
    {
        if (!self::$instance instanceof MockCliTool) {
            self::$instance = new MockCliTool();
        }
        return self::$instance;
    }

    /**
     * @static
     * @return MockCliTool
     */
    public static function getInstance()
    {
        return self::_instantinate();
    }

    public static function clearInstance()
    {
        unset(self::$instance);
    }
}
