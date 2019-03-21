<?php

/**
 * ---------------------------------------
 * delete-vhost: Deletes a virtual host
 * ---------------------------------------
 * This file is part of Stackman.
 */

if (!isset($cli)) {
    echo "Please do not run this script directly." . PHP_EOL;
    exit(1);
}

// Define the arguments we need
$cli->arguments->add([
    'domain' => [
        'prefix' => 'd',
        'longPrefix' => 'domain',
        'description' => 'The domain to be deleted. Use the primary domain only.',
        'required' => true
    ],
    'preserve_homedir' => [
        'prefix' => 'H',
        'longPrefix' => 'preserve-homedir',
        'description' => 'Preserves the user\'s home directory when the user is being deleted.',
        'noValue' => true
    ]
]);

// Parse Arguments
try {
    $cli->arguments->parse();
} catch (League\CLImate\Exceptions\InvalidArgumentException $e) {
    $cli->to('error')->red($e->getMessage());
    exit(1);
}

// Get the domain
$domain = $cli->arguments->get('domain');
$preserve_homedir = $cli->arguments->defined('preserve_homedir');

// Read the virtual host if it exists
$vhost = "/etc/httpd/vhosts.d/{$domain}.conf";
if (!is_file($vhost)) {
    $cli->to('error')->red('The virtual host configuration for ' . $domain . ' does not exist.');
    exit(1);
}
$vhostData = file_get_contents($vhost);

// Get the user
preg_match('/\#\ User\: [a-zA-Z0-9]+/m', $vhostData, $userMatches);
if (count($userMatches) == 0) {
    $cli->to('error')->red('Virtual host metadata corrupted: no match for user.');
    exit(1);
}
$user = str_replace("# User: ", "", $userMatches[0]);

// Get the mode
preg_match('/\#\ Mode\: [a-zA-Z0-9]+/m', $vhostData, $modeMatches);
if (count($modeMatches) == 0) {
    $cli->to('error')->red('Virtual host metadata corrupted: no match for mode.');
    exit(1);
}
$mode = str_replace("# Mode: ", "", $modeMatches[0]);

// Get the proxy
$proxy = null;
preg_match('/\#\ Proxy\: (http|https)\:\/\/[a-z0-9\.\:\/]+/m', $vhostData, $proxyMatches);
if (count($proxyMatches) == 0) {
    if ($mode == 'proxy') {
        $cli->to('error')->red('Virtual host metadata corrupted: no match for proxy.');
        exit(1);
    }
} else {
    $proxy = str_replace("# Proxy: ", "", $proxyMatches[0]);
}

// Get the PHP version
$php = null;
preg_match('/\#\ PHP\ Version\: [0-9]\.[0-9]/m', $vhostData, $phpMatches);
if (count($phpMatches) == 0) {
    if ($mode == 'LAMP') {
        $cli->to('error')->red('Virtual host metadata corrupted: no match for PHP version.');
        exit(1);
    }
} else {
    $php = str_replace("# PHP Version: ", "", $phpMatches[0]);
}
$phpVersion = "php" . str_replace(".", "", $php);

if ($mode == "proxy") {
    // Delete the virtual host
    exec("rm -f /etc/httpd/vhosts.d/{$domain}.conf");

    // If user dir is not preserved, delete it.
    exec("pkill -u {$user}");
    if ($preserve_homedir) {
        exec("userdel {$user}");
    } else {
        exec("userdel -r {$user}");
    }

    // Reload
    exec("systemctl reload httpd");
} else {
    // Delete the virtual host
    exec("rm -f /etc/httpd/vhosts.d/{$domain}.conf");
    exec("systemctl reload httpd");

    // Delete the FPM file
    exec("systemctl stop {$phpVersion}-php-fpm");
    exec("rm -f /etc/opt/remi/{$phpVersion}/php-fpm.d/{$user}.conf");
    exec("systemctl start {$phpVersion}-php-fpm");
}

// Delete SSL Certificates
exec("rm -f /etc/stackman/tmp/{$domain}-privkey.pem /etc/stackman/{$domain}-privkey.pem /etc/stackman/tmp/{$domain}-fullchain.pem /etc/stackman/{$domain}-fullchain.pem");
exec("certbot delete --cert-name {$domain}");

// Done
$cli->lightGreen()->out("The virtual host {$domain} has been deleted.");
