<?php
/**
 * Created by JetBrains PhpStorm.
 * User: ksuszyns
 * Date: 11.04.12
 * Time: 02:09
 */

$target = $_SERVER['argv'][1];
$first = $_SERVER['argv'][2];
if (substr($first, 0, 1) == '-') {
    // opts
    if ($first == '-l') {
        if (!is_readable($target)) {
            fprintf(STDERR, 'Target is not readable'."\n");
            exit(1);
        }
        $content = file_get_contents($target);
        if (empty($content)) {
            fprintf(STDERR, 'Empty crontab'."\n");
            exit(2);
        }
        fprintf(STDOUT, $content);
        exit(0);
    }
    if ($first == '-r') {
        file_put_contents($target, '');
        exit(0);
    }
} else {
    // replace from input file
    $input = file_get_contents($_SERVER['argv'][2]);
    file_put_contents($target, $input);
    exit(0);
}