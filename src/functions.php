<?php

function drutiny()
{
    global $kernel;
    return $kernel->getContainer();
}

$timezone = 'UTC';

// Set the timezone to the local OS if supported.
if (file_exists('/etc/localtime')) {
    $systemZoneName = readlink('/etc/localtime');
    if (strpos($systemZoneName, 'zoneinfo') !== false) {
        $timezone = substr($systemZoneName, strpos($systemZoneName, 'zoneinfo') + 9);
    }
}

date_default_timezone_set($timezone);

define('REQUEST_TIME', time());
