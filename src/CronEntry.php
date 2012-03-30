<?php
/**
 * @author                                                      Krzysztof Suszyński <k.suszynski@mediovski.pl>
 * @version                                                     0.2
 * @package                                                     php.manager.crontab
 *
 * Copyright (c) 2012 Krzysztof Suszyński <k.suszynski@mediovski.pl>
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
 * Crontab Entry object
 *
 * @author Krzysztof Suszyński <k.suszynski@mediovski.pl>
 */
class CronEntry
{
    /**
     * Minute (0 - 59)
     *
     * @var string
     */
    private $minute = 0;

    /**
     * Hour (0 - 23)
     *
     * @var string
     */
    private $hour = 10;

    /**
     * Day of Month (1 - 31)
     *
     * @var string
     */
    private $dayOfMonth = '*';

    /**
     * Month (1 - 12) OR jan,feb,mar,apr...
     *
     * @var string
     */
    private $month = '*';

    /**
     * Day of week (0 - 6) (Sunday=0 or 7) OR sun,mon,tue,wed,thu,fri,sat
     *
     * @var string
     */
    private $dayOfWeek = '*';

    /**
     * Job to be done
     *
     * @var string
     */
    private $job = null;

    /**
     * Group of job
     *
     * @var string|null
     */
    private $group = null;

    /**
     * Cron manager
     *
     * @var CrontabManager
     */
    private $_manager;

    /**
     * @var string
     */
    private $_root = '';

    /**
     * @var null
     */
    public $comments = null;

    /**
     * Constructor
     *
     * @param CrontabManager $manager
     * @param string|null    $group
     */
    public function __construct(CrontabManager $manager, $group = null)
    {
        $this->_manager = $manager;
        if ($group) {
            $this->group = $group;
        }
    }

    /**
     * Set root directory for relative commands
     *
     * @param string $root
     */
    public function setRootForCommands($root)
    {
        $this->_root = $root;
    }

    /**
     * Set minute or minutes
     *
     * @param string $minute required
     *
     * @return CronEntry
     */
    public function onMinute($minute)
    {
        $this->minute = $minute;
        return $this;
    }

    /**
     * Set hour or hours
     *
     * @param string $hour required
     *
     * @return CronEntry
     */
    public function onHour($hour)
    {
        $this->hour = $hour;
        return $this;
    }

    /**
     * Set day of month or days of month
     *
     * @param string $dayOfMonth required
     *
     * @return CronEntry
     */
    public function onDayOfMonth($dayOfMonth)
    {
        $this->dayOfMonth = $dayOfMonth;
        return $this;
    }

    /**
     * Set month or months
     *
     * @param string $month required
     *
     * @return CronEntry
     */
    public function onMonth($month)
    {
        $this->month = $month;
        return $this;
    }

    /**
     * Set day of week or days of week
     *
     * @param string $minute required
     *
     * @return CronEntry
     */
    public function onDayOfWeek($day)
    {
        $this->dayOfWeek = $day;
        return $this;
    }

    /**
     * Set entire time code with one public function.
     *
     * Set entire time code with one public function. This has to be a
     * complete entry. See http://en.wikipedia.org/wiki/Cron#crontab_syntax
     *
     * @param string $timeCode required
     *
     * @return CronEntry
     */
    public function on($timeCode)
    {
        list(
            $this->minute,
            $this->hour,
            $this->dayOfMonth,
            $this->month,
            $this->dayOfWeek
            ) = preg_split('/\s+/', $timeCode);

        return $this;
    }

    /**
     * Add job to the jobs array.
     *
     * Add job to the jobs array. Each time segment should be set before calling
     * this method. The job should include the absolute path to the commands
     * being used.
     *
     * @param string      $job   required
     * @param string|null $group optional
     *
     * @param bool        $autoAdd
     *
     * @return CrontabManager
     */
    public function doJob($job, $group = null, $autoAdd = true)
    {
        $this->job = $job;
        $this->job = preg_replace('/\\\n/m', '', $this->job);
        if ($group) {
            $this->group = $group;
        }
        if ($autoAdd) {
            $this->_manager->add($this);
        }

        return $this->_manager;
    }

    /**
     * Adds comments to this job
     *
     * @param string[] $comments
     *
     * @return CronEntry
     */
    public function addComments(array $comments)
    {
        $this->comments = $comments;
        return $this;
    }

    /**
     * Retrives full command path if can and should
     *
     * @return string
     */
    private function _getFullCommand()
    {
        $parts = preg_split('/\s+/', $this->job);
        reset($parts);
        $first = current($parts);
        unset($parts[key($parts)]);
        ob_start();
        passthru("which $first", $ret);
        $fullcommand = trim(ob_get_clean());
        if ($ret == 0) {
            return trim($fullcommand . ' ' . join(' ', $parts));
        } else {
            $root = $this->_root;
            if ($this->_root) {
                $root = rtrim($this->_root, DIRECTORY_SEPARATOR);
                $root .= DIRECTORY_SEPARATOR;
            }
            return trim($root . $this->job);
        }
    }

    /**
     * Render to string method
     *
     * @return string
     */
    public function render($commentEntry = true)
    {
        if (empty($this->job)) {
            return '';
        }
        $entry = array(
            $this->minute,
            $this->hour,
            $this->dayOfMonth,
            $this->month,
            $this->dayOfWeek,
            $this->_getFullCommand()
        );
        $entry = join("\t", $entry);
        if ($commentEntry) {
            $hash = base_convert(crc32($entry), 10, 36);
            $comments = join("\n", $this->comments);
            if (!empty($comments)) {
                $comments .= "\n";
            }
            $entry = $comments . $entry . " # $hash";
        }
        return $entry;
    }

    /**
     * Render to string method
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render(true);
    }
}
