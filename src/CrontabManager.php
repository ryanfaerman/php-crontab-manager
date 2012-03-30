<?
/**
 * @author Ryan Faerman <ryan.faerman@gmail.com>
 * @author Krzysztof Suszyński <k.suszynski@mediovski.pl>
 * @version 0.2
 * @package php.manager.crontab
 *
 * Copyright (c) 2009 Ryan Faerman <ryan.faerman@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 */

namespace php\manager\crontab;
 
/**
 * Crontab manager implementation
 * 
 * @author Krzysztof Suszyński <k.suszynski@mediovski.pl>
 * @author Ryan Faerman <ryan.faerman@gmail.com> 
 */
class CrontabManager
{
    
    /**
     * Location of the crontab executable
     * 
     * @var string
     */
    public $crontab = '/usr/bin/crontab';
    
    /**
     * Name of user to install crontab
     * 
     * @var string
     */
    public $user = null;
    
    /**
     * Location to save the crontab file.
     * 
     * @var string
     */
    private $_tmpfile;
    
    /**
     * @var CronEntry[]
     */
    private $jobs = array();
    
    /**
     * @var array
     */
    private $replace = array();
    
    /**
     * @var array
     */
    private $files = array();
    
    /**
     * @var array
     */
    private $fileHashes = array();
    
    /**
     * @var boolean
     */
    public $prependRootPath = true;
    
    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct()
    {
        $tmpDir = sys_get_temp_dir();
        $this->_tmpfile = tempnam($tmpDir, 'cronman');
    }
    
    /**
     * Creates new job
     * 
     * @param string $group
     * @return CronEntry
     */
    public function newJob($group = null)
    {
        return new CronEntry($this, $group);
    }
    
    /**
     * Adds job to managed list
     * 
     * @param CronEntry $job
     * @param string $file optional
     * @return CrontabManager
     */
    public function add(CronEntry $job, $file = null)
    {
        if (!$file) {
            $this->jobs[] = $job;
        } else {
            if (!isset($this->files[$file])) {
                $this->files[$file] = array();
            }
            $this->files[$file][] = $job;
        }
        return $this;
    }
    
    /**
     * Replace job with another one
     * 
     * @param CronEntry $from
     * @param CronEntry $to
     * @return CrontabManager
     */
    public function replace(CronEntry $from, CronEntry $to)
    {
        $this->replace[] = array($from, $to);
        return $this;
    }
    
    /**
     * Reads cron file and adds jobs to list
     * 
     * @param string $path
     */
    public function manageFile($path)
    {
        $hash = base_convert(crc32($path), 10, 36);
        $this->fileHashes[$path] = $hash;
        $lines = file($path);
        $re = '/(([^\s]+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]+))\s+([^\#]+)/';
        foreach ($lines as $line) {
            if (preg_match($regex, $line, $match)) {
                list(
                    $timeCode,
                    $minute, 
                    $hour, 
                    $dayOfMonth, 
                    $month, 
                    $dayOfWeek,
                    $command
                ) = $match;
                $job = $this->newJob($hash);
                if ($this->prependRootPath) {
                    $job->setRootForCommands(dirname($path));
                }
                $job->on($timeCode);
                $job->doJob($command);
                
                $this->add($job, $path);
            }
        }
    }
    
    /**
     * calcuates crontab command
     * 
     * @return string
     */
    private function _command()
    {
        $cmd = '';
        if ($this->user) {
            $cmd .= sprintf('sudo -u %s ', $this->user);
        }
        $cmd .= $this->crontab;
        return $cmd;
    }
    
    /**
     * Save the jobs to disk, remove existing cron
     * 
     * @param boolean $includeOldJobs optional
     * @return boolean
     * @throws \UnexpectedValueException
     */
    public function activate($includeOldJobs = true)
    {
        $contents = '';
        if ($includeOldJobs) {
            $contents = $this->listJobs();
        }
        
        $contents = $this->_prepareContents($contents);
        
        $dir = dirname($this->_tmpfile);
        
        file_put_contents($this->_tmpfile, $contents, LOCK_EX);
        $this->_exec($this->crontab.' '.$this->_tmpfile.';', $ret);
        unlink($this->_tmpfile);
        if ($ret != 0) {
            throw new \UnexpectedValueException('Can\'t install new crontab', $retVal);
        }
    }
    
    private $_beginBlock = 'BEGIN:%s';
    private $_endBlock   = 'END:%s';
    private $_before = 'Autogenerated by CrontabManager. Do not edit. Orginal file: %s';
    private $_after  = 'End of autogenerated code.';
    
    private function _prepareContents($contents)
    {
        $append = array();
        $contents = explode("\n", $contents);
        
        foreach ($this->fileHashes as $file => $hash) {
            $contents = $this->_removeBlock($contents, $hash);
            $contents = $this->_addBlock($contents, $file, $hash);
        }
        $contents[] = '';
        foreach ($this->jobs as $job) {
            $contents[] = $job;
        }
        $out = $this->_doReplace($contents);
        $out = preg_replace('/[\n]{3,}/m', "\n\n", $out);
        return $out;
    }
    
    private function _doReplace(array $contents)
    {
        $out = join("\n", $contents);
        foreach ($this->replace as $fromJob => $toTob) {
            $from = $fromJob->render(false);
            $out = str_replace($fromJob, $toTob, $out);
            $out = str_replace($from, $toTob, $out);
        }
        return $out;
    }
    
    private function _addBlock(array $contents, $file, $hash)
    {
        $contents[] = '';
        $pre = sprintf('# ' . $this->_beginBlock, $hash);
        $pre .= sprintf(' ' . $this->_before, $file);
        $contents[] = $pre;
        
        foreach ($this->files as $jobs) {
            foreach ($jobs as $job) {
                $contents[] = $job;
            };
        }
        
        $after = sprintf('# ' . $this->_endBlock, $hash);
        $after .= ' ' . $this->_after;
        $contents[] = $after;
    }
    
    private function _removeBlock(array $contents, $hash)
    {
        $from = sprintf('# ' . $this->_beginBlock, $hash);
        $to = sprintf('# ' . $this->_beginBlock, $hash);
        $cut = false;
        $toCut = array();
        foreach ($contents as $no => $line) {
            if (substr($line, 0, strlen($from)) = $from) {
                $cut = true;
            }
            if ($cut) {
                $toCut[] = $no;
            }
            if (substr($line, 0, strlen($to)) = $to) {
                $cut = false;
            }
        }
        foreach ($toCut as $lineNo) {
            unset($contents[$lineNo]);
        }
        return $contents;
    }
    
    /**
     * Runs command in terminal
     * 
     * @param string $command
     * @param integer $returnVal
     * @return string
     */
    private function _exec($command, & $returnVal)
    {
        ob_start();
        passthru($command, $returnVal);
        $output = ob_get_clean();
        return $output;
    }
    
    /**
     * List current cron jobs
     * 
     * @return string
     * @throws \UnexpectedValueException
     */
    public function listJobs()
    {
        $out = $this->_exec($this->_command() . ' -l;', $retVal);
        if ($retVal != 0) {
            throw new \UnexpectedValueException('No cron file or no permissions to list', $retVal);
        }
        return $out;
    }
}

