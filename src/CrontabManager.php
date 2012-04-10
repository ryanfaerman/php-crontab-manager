<?php
/**
 * @author                                            Ryan Faerman <ryan.faerman@gmail.com>
 * @author                                            Krzysztof Suszyński <k.suszynski@mediovski.pl>
 * @version                                           0.2
 * @package                                           php.manager.crontab
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

    public $cronContent = '';

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
     * @var CronEntry[]
     */
    private $replace = array();

    /**
     * @var CronEntry[]
     */
    private $files = array();

    /**
     * @var array
     */
    private $fileHashes = array();
    
    /**
     * @var array
     */
    private $filesToRemove = array();

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
        $this->_setTempFile();
    }
    
    /**
     * Sets tempfile name
     * 
     * @return CrontabManager
     */
    protected function _setTempFile()
    {
        $tmpDir = sys_get_temp_dir();
        $this->_tmpfile = tempnam($tmpDir, 'cronman');
        
        return $this;
    }

    /**
     * Creates new job
     *
     * @param string $group
     *
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
     * @param string    $file optional
     *
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
     *
     * @return CrontabManager
     */
    public function replace(CronEntry $from, CronEntry $to)
    {
        $this->replace[] = array($from, $to);
        return $this;
    }

    /**
     * @var string[]
     */
    private $_comments = array();
    
    /**
     * Parse input cron file to cron entires
     * 
     * @param string $path
     * @param string $hash
     * @return CronEntry[]
     * @throws \InvalidArgumentException
     */
    private function _parseFile($path, $hash)
    {
        $jobs = array();
        
        $lines = file($path);
        $re = '/^\s*(([^\s\#]+)\s+([^\s\#]+)\s+([^\s\#]+)\s+([^\s\#]+)\s+' .
            '([^\s\#]+))\s+([^\#]+)/';
        foreach ($lines as $lineno => $line) {
            if (preg_match($re, $line, $match)) {
                // is cron entry
                list(
                    $whole,
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
                $job
                    ->on($timeCode)
                    ->addComments($this->_comments)
                    ->doJob($command, $hash, false);
                $this->_comments = array();
            } elseif (preg_match('/^\s*\#/', $line)) {
                $this->_comments[] = $line;
            } elseif (trim($line) == '') {
                continue;
            } else {
                $msg = sprintf('Line #%d of file: "%s" is invalid!', $lineno, $path);
                throw new \InvalidArgumentException($msg);
            }
            $jobs[] = $job;
        }
        return $jobs;
    }

    /**
     * Reads cron file and adds jobs to list
     *
     * @param string $filename
     * @returns CrontabManager
     */
    public function enableOrUpdateFile($filename)
    {
        $path = realpath($filename);
        if (!$path)
            return;
        $hash = base_convert(crc32($path), 10, 36);
        
        if (isset($this->filesToRemove[$hash])) {
            unset($this->filesToRemove[$hash]);
        }
        $this->fileHashes[$path] = $hash;
        $jobs = $this->_parseFile($path, $hash);
        foreach ($jobs as $job) {
            $this->add($job, $path);
        }
        
        return $this;
    }
    
    /**
     * Disable file from crontab
     * 
     * @param string $filename
     * @return CrontabManager
     */
    public function disableFile($filename)
    {
        $path = realpath($filename);
        if (!$path)
            return;
        $hash = base_convert(crc32($path), 10, 36);
        if (isset($this->fileHashes[$path])) {
            unset($this->fileHashes[$path]);
            unset($this->files[$path]);
        }
        $this->filesToRemove[$hash] = $path;
        
        return $this;
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
     *
     * @return boolean
     * @throws \UnexpectedValueException
     */
    public function save($includeOldJobs = true)
    {
        $this->cronContent = '';
        if ($includeOldJobs) {
            try {
                $this->cronContent = $this->listJobs();
            } catch (\UnexpectedValueException $e) {
            }
        }

        $this->cronContent = $this->_prepareContents($this->cronContent);

        $this->_replaceCronContents();
    }

    /**
     * Replaces cron contents
     * 
     * @throws \UnexpectedValueException
     * @return CrontabManager
     */
    protected function _replaceCronContents()
    {
        file_put_contents($this->_tmpfile, $this->cronContent, LOCK_EX);
        $out = $this->_exec($this->crontab . ' ' . $this->_tmpfile . ' 2>&1', $ret);
        unlink($this->_tmpfile);
        $this->_setTempFile();
        if ($ret != 0) {
            throw new \UnexpectedValueException(
                $out . "\n" .$this->cronContent, $ret
            );
        }
        return $this;
    }

    /**
     * @var string
     */
    private $_beginBlock = 'BEGIN:%s';
    /**
     * @var string
     */
    private $_endBlock = 'END:%s';
    /**
     * @var string
     */
    private $_before = "Autogenerated by CrontabManager.\n# Do not edit. Orginal file: %s";
    /**
     * @var string
     */
    private $_after = 'End of autogenerated code.';

    /**
     * @param string $contents
     *
     * @return string
     */
    private function _prepareContents($contents)
    {
        if (empty($contents)) {
            $contents = array();
        } else {
            $contents = explode("\n", $contents);
        }
        
        foreach ($this->filesToRemove as $file) {
            $contents = $this->_removeBlock($contents, $hash);
        }

        foreach ($this->fileHashes as $file => $hash) {
            $contents = $this->_removeBlock($contents, $hash);
            $contents = $this->_addBlock($contents, $file, $hash);
        }
        if ($this->jobs)
            $contents[] = '';
        foreach ($this->jobs as $job) {
            $contents[] = $job;
        }
        $out = $this->_doReplace($contents);
        $out = preg_replace('/[\n]{3,}/m', "\n\n", $out);
        return trim($out) . "\n";
    }

    /**
     * @param array $contents
     *
     * @return string
     */
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

    /**
     * @param array  $contents
     * @param string $file
     * @param string $hash
     *
     * @return array
     */
    private function _addBlock(array $contents, $file, $hash)
    {
        $pre = sprintf('# ' . $this->_beginBlock, $hash);
        $pre .= sprintf(' ' . $this->_before, $file);
        $contents[] = $pre;
        $contents[] = '';

        foreach ($this->files as $jobs) {
            foreach ($jobs as $job) {
                $contents[] = $job;
            }
        }

        $contents[] = '';
        $after = sprintf('# ' . $this->_endBlock, $hash);
        $after .= ' ' . $this->_after;
        $contents[] = $after;

        return $contents;
    }

    /**
     * @param array  $contents
     * @param string $hash
     *
     * @return array
     */
    private function _removeBlock(array $contents, $hash)
    {
        $from = sprintf('# ' . $this->_beginBlock, $hash);
        $to = sprintf('# ' . $this->_endBlock, $hash);
        $cut = false;
        $toCut = array();
        foreach ($contents as $no => $line) {
            if (substr($line, 0, strlen($from)) == $from) {
                $cut = true;
            }
            if ($cut) {
                $toCut[] = $no;
            }
            if (substr($line, 0, strlen($to)) == $to) {
                break;
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
     * @param string  $command
     * @param integer $returnVal
     *
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

