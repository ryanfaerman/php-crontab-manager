<?
/**
 * @author Ryan Faerman <ryan.faerman@gmail.com>
 * @version 0.1
 * @package PHPCronTab
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
 
class Crontab {
        
        /**
         * Location of the crontab executable
         * @var string
         */
        var $crontab = '/usr/bin/crontab';
        
        /**
         * Location to save the crontab file.
         * @var string
         */
        var $destination = '/tmp/CronManager';
        
        /**
         * Minute (0 - 59)
         * @var string
         */
        var $minute        = 0;
        
        /**
         * Hour (0 - 23)
         * @var string
         */
        var $hour = 10;
        
        /**
         * Day of Month (1 - 31)
         * @var string
         */
        var        $dayOfMonth = '*';
        
        /**
         * Month (1 - 12) OR jan,feb,mar,apr...
         * @var string
         */
        var $month = '*';
        
        /**
         * Day of week (0 - 6) (Sunday=0 or 7) OR sun,mon,tue,wed,thu,fri,sat
         * @var string
         */
        var $dayOfWeek = '*';
        
		  /**
         * Year 1970–2099
         * @var string
         */
	var $year = null;
        /**
         * @var array
         */
        var $jobs = array();
		
		/**
         * @var array
         */
	var $old_jobs = array();
        
        function Crontab() {
        }
		
	function __construct(){
		
		$this -> old_jobs = $this->listJobs();
	}
        
        /**
        * Set minute or minutes
        * @param string $minute required
        * @return object
        */
        function onMinute($minute) {
                $this->minute = $minute;
                return $this;
        }
        
        /**
        * Set hour or hours
        * @param string $hour required
        * @return object
        */
        function onHour($hour) {
                $this->hour = $hour;
                return $this;
        }
        
        /**
        * Set day of month or days of month
        * @param string $dayOfMonth required
        * @return object
        */
        function onDayOfMonth($dayOfMonth) {
                $this->dayOfMonth = $dayOfMonth;
                return $this;
        }
        
        /**
        * Set month or months
        * @param string $month required
        * @return object
        */
        function onMonth($month) {
                $this->month = $month;
                return $this;
        }
        
        /**
        * Set day of week or days of week
        * @param string $minute required
        * @return object
        */
        function onDayOfWeek($dayOfWeek) {
                $this->dayOfWeek = $dayOfWeek;
                return $this;
        }
		
	/**
        * Set Year
        * @param string $year required
        * @return object
        */
        function onYear($year) {
                $this->year = $year;
                return $this;
        }
        
        /**
        * Set entire time code with one function. This has to be a complete entry. See http://en.wikipedia.org/wiki/Cron#crontab_syntax
        * @param string $timeCode required
        * @return object
        */
        function on($timeCode) {
                list(
                        $this->minute, 
                        $this->hour, 
                        $this->dayOfMonth, 
                        $this->month, 
                        $this->dayOfWeek,
			$this->year
                ) = explode(' ', $timeCode);
                
                return $this;
        }
        
        /**
        * Add job to the jobs array. Each time segment should be set before calling this method. The job should include the absolute path to the commands being used.
        * @param string $job required
        * @return object
        */
        function doJob($job) {
		$year = !is_null($this->year) ? ' '.$this->year : '';
                $this->jobs[] =        $this->minute.' '.
                                                $this->hour.' '.
                                                $this->dayOfMonth.' '.
                                                $this->month.' '.
                                                $this->dayOfWeek.
						$year."\t".								
                                                $job;
                
                return $this;
        }
        
        /**
        * Save the jobs to disk, remove existing cron
        * @param boolean $includeOldJobs optional
        * @return boolean
        */
        function activate($includeOldJobs = true) {
		$contents = '';
		if($includeOldJobs) {
			$contents .= implode("\n", $this->old_jobs);
			$contents .= "\n";
                }
                $contents .= implode("\n", $this->jobs);
                $contents .= "\n";
                
                if(is_writable($this->destination) || !file_exists($this->destination)){
			file_put_contents($this->destination, $contents, LOCK_EX);
                        exec($this->crontab.' -r;');
                        exec($this->crontab.' '.$this->destination.';');
                        return true;
                }
                
                return false;
        }
        
        /**
        * List current cron jobs 
	* @return array
        */
        function listJobs() {
                exec($this->crontab.' -l;', $tmp);
		return $tmp;
        }
		
		/**
        * Delete one job from current jobs. 
        * @return array
        */
		function deleteJob($job = null) {
			if (!is_null($job)){
				$data = array();
				$tmp = $this -> old_jobs;
				$rowsDeleted = 0;
				if (is_array($tmp)){
					foreach($tmp as $val){
						if(!preg_match('/'.$job.'/',$val)){
							$data[] = $val;
						} else {
							$rowsDeleted++;
						}
					}
				}
				$this -> old_jobs = $data;
			} 
			return $rowsDeleted;
        }
}

?>
