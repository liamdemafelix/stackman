<?php

/**
 * ---------------------------------------
 * version: Displays the Stackman version
 * ---------------------------------------
 * This file is part of Stackman.
 */

if (!isset($cli)) {
    echo "Please do not run this script directly." . PHP_EOL;
    exit(1);
}

$cli->out('You are running Stackman ' . $stackmanVersion);
exit(0);
