PHP Crontab Manager
===================

Manages crontab file by adding and deleting the appropriate entries. It is able
to track the source file so that after the changes in this file to upgrade a 
crontab file to the target.

Installation
------------

 # copy files to your project
 # include files from src directory or use some autoloader
 # use it!

Usage
-----

Simple usage:

    use php\manager\crontab\CrontabManager;
    
    $crontab = new CrontabManager();
    
    $crontab->on('* * * * *');
    $crontab->onMinute('20-30')->doJob("echo foo;");
    $crontab->onMinute('35-40')->doJob("echo bar;");
    
    $crontab->activate();
