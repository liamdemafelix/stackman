<?php

/**
 * ---------------------------------------
 * delete-vhost: Deletes a virtual host
 * ---------------------------------------
 * This file is part of Stackman.
 */
if (!isset($cli)) {
    echo 'Please do not run this script directly.'.PHP_EOL;
    exit(1);
}

// Define the arguments we need
$cli->arguments->add([
    'domain' => [
        'prefix' => 'd',
        'longPrefix' => 'domain',
        'description' => 'The domain to be deleted. Use the primary domain only.',
        'required' => true,
    ],
    'preserve_homedir' => [
        'prefix' => 'H',
        'longPrefix' => 'preserve-homedir',
        'description' => 'Preserves the user\'s home directory when the user is being deleted.',
        'defaultValue' => false,
        'noValue' => true,
    ],
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
$preserve_homedir = $cli->arguments->get('preserve_homedir');

// Read the virtual host if it exists
$vhost = "/etc/httpd/vhosts.d/{$domain}.conf";
if (!is_file($vhost)) {
    $cli->to('error')->red('The virtual host configuration for '.$domain.' does not exist.');
    exit(1);
}
$vhostData = file_get_contents($vhost);

// Get the user
preg_match('/\#\ User\: [a-zA-Z0-9]+/m', $vhostData, $userMatches);
if (0 == count($userMatches)) {
    $cli->to('error')->red('Virtual host metadata corrupted: no match for user.');
    exit(1);
}
$user = str_replace('# User: ', '', $userMatches[0]);

// Get the mode
preg_match('/\#\ Mode\: [a-zA-Z0-9]+/m', $vhostData, $modeMatches);
if (0 == count($modeMatches)) {
    $cli->to('error')->red('Virtual host metadata corrupted: no match for mode.');
    exit(1);
}
$mode = str_replace('# Mode: ', '', $modeMatches[0]);

// Get the proxy
$proxy = null;
preg_match('/\#\ Proxy\: (http|https)\:\/\/[a-z0-9\.\:\/]+/m', $vhostData, $proxyMatches);
if (0 == count($proxyMatches)) {
    if ('proxy' == $mode) {
        $cli->to('error')->red('Virtual host metadata corrupted: no match for proxy.');
        exit(1);
    }
} else {
    $proxy = str_replace('# Proxy: ', '', $proxyMatches[0]);
}

// Get the PHP version
$php = null;
preg_match('/\#\ PHP\ Version\: [0-9]\.[0-9]/m', $vhostData, $phpMatches);
if (0 == count($phpMatches)) {
    if ('LAMP' == $mode) {
        $cli->to('error')->red('Virtual host metadata corrupted: no match for PHP version.');
        exit(1);
    }
} else {
    $php = str_replace('# PHP Version: ', '', $phpMatches[0]);
}
$phpVersion = 'php'.str_replace('.', '', $php);

exec("systemctl stop httpd");
if ($mode == 'proxy') {
    // Delete the virtual host
    exec("rm -f /etc/httpd/vhosts.d/{$domain}.conf");
} else {
    // Delete the virtual host
    exec("rm -f /etc/httpd/vhosts.d/{$domain}.conf");

    // Delete the FPM file
    exec("systemctl stop {$phpVersion}-php-fpm");
    exec("rm -f /etc/opt/remi/{$phpVersion}/php-fpm.d/{$user}.conf");
}

// If user dir is not preserved, delete it.
exec("pkill -u {$user}");
$delCounter = 1;
do {
    $preserve = '';
    if ($preserve_homedir) {
        $preserve = ' (preserving home directory)';
    }
    $cli->out('Attempt #' . $delCounter . ' to delete user account' . $preserve);
    if ($preserve_homedir) {
        exec("userdel {$user}", $output, $status);
    } else {
        exec("userdel -r {$user}", $output, $status);
    }
    if ($status === 0) {
        $cli->out('User account deleted');
    }
    $delCounter++;
    if ($delCounter >= 5 && $status != 0) {
        $cli->to('error')->red("Failed to delete user account: {$user} after 5 attempts. Please run userdel manually.");
        $status = 0;
    }
} while ($status != 0);

// Start services
exec("systemctl start httpd");
if ($mode != 'proxy') {
    exec("systemctl start {$phpVersion}-php-fpm");
}

// Delete SSL Certificates
exec("rm -f /etc/stackman/tmp/{$domain}-privkey.pem /etc/stackman/{$domain}-privkey.pem /etc/stackman/tmp/{$domain}-fullchain.pem /etc/stackman/{$domain}-fullchain.pem");
exec("certbot delete --cert-name {$domain}");

// Done
$cli->lightGreen()->out("The virtual host {$domain} has been deleted.");
