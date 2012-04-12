<?php

namespace tests\php\manager\crontab\mock;

use php\manager\crontab\CrontabManager;


require_once dirname(dirname(__DIR__)) . '/src/CrontabManager.php';

/**
 * Crontab manager implementation
 *
 * @author Krzysztof Suszyński <k.suszynski@mediovski.pl>
 */
class MockCrontabManager extends CrontabManager
{
    
    /**
     * @var resource
     */
    public $mockcron;

    /**
     * @var string
     */
    public $mockcronname;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->mockcronname = tempnam(sys_get_temp_dir(), 'php-mcm');
        chmod($this->mockcronname, 0666);
    }

    public function __destruct()
    {
        if (is_file($this->mockcronname)) {
            unlink($this->mockcronname);
        }
        parent::__destruct();
    }
    
    /**
     * Sets initial contents
     * 
     * @param string $content
     * @return MockCrontabManager
     */
    public function setInitialContents($content)
    {
        return $this->_writeContents($content);
    }
    
    /**
     * Writes contents to tmp
     * 
     * @param string $contents
     * @return MockCrontabManager
     */
    protected function _writeContents($contents)
    {
        $this->mockcron = fopen($this->mockcronname, 'r+');
        ftruncate($this->mockcron, 0);
        rewind($this->mockcron);
        fwrite($this->mockcron, $contents);
        fflush($this->mockcron);
        rewind($this->mockcron);
        fclose($this->mockcron);
        $this->_setTempFile();
        return $this;
    }

    protected function _command()
    {
        $cmd = parent::_command();
        $cmd .= ' ' . $this->mockcronname;
        return $cmd;
    }

    /**
     * @return string
     */
    public function __mock_command()
    {
        return parent::_command();
    }
}
