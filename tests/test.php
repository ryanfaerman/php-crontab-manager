<?php
/**
 * LivePlatform
 *
 * Copyrighted by Mediovski sp. z o.o.
 *
 * The code contained in this file is property or copyrighted by
 * Mediovski sp. z o.o. If you are not employee of this company or one of our
 * clients, please delete this file and inform us as soon as possible by
 * email: info@mediovski.pl. This file may not be disclosed, used or copied
 * by anyone other than authorized people mentioned before. If you wish
 * clarification of any matter, please request it by email.
 *
 * @author Krzysztof Suszynski <k.suszynski@mediovski.pl>
 * @copyright Copyright (c) 2012, Mediovski sp. z o.o.
 * @package pl.mediovski.technology
 * @version SVN: $Id: $
 * @filesource
 */

use php\manager\crontab\CrontabManager;

ini_set('display_errors', 'On');
error_reporting(E_ALL);

require '../src/CronEntry.php';
require '../src/CrontabManager.php';

$manager = new CrontabManager();
$manager->manageFile('cronfile');
$manager->activate();
