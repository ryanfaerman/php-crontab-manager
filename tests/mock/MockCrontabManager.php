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
    private $mockcron;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->mockcron = tmpfile();
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
        fwrite($this->mockcron, $contents);
        fflush($this->mockcron);
        rewind($this->mockcron);
        $this->_setTempFile();
        return $this;
    }
    
    /**
     * (non-PHPdoc)
     * @see php\manager\crontab.CrontabManager::_replaceCronContents()
     */
    protected function _replaceCronContents()
    {
        return $this->_writeContents($this->cronContent);
    }
    
    /**
     * (non-PHPdoc)
     * @see php\manager\crontab.CrontabManager::listJobs()
     */
    public function listJobs()
    {
        $buff = '';
        while (!feof($this->mockcron)) {
            $buff .= fread($this->mockcron, 1024);
        }
        return $buff;
    }
}
