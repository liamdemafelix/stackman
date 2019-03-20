<?php

/**
 * ---------------------------------------
 * create-vhost: Create a virtual host
 * ---------------------------------------
 * This file is part of Stackman.
 */

if (!isset($cli)) {
    echo "Please do not run this script directly." . PHP_EOL;
    exit(1);
}

// Define the arguments we need
$cli->arguments->add([
    'user' => [
        'prefix' => 'u',
        'longPrefix' => 'user',
        'description' => 'Defines the user this virtual host will run as.',
        'required' => true
    ],
    'domain' => [
        'prefix' => 'd',
        'longPrefix' => 'domain',
        'description' => 'Defines the domain name to use in the virtual host. Accepts multiple values separated by commas, with the first value being the primary domain.',
        'required' => true
    ],
    'email' => [
        'prefix' => 'e',
        'longPrefix' => 'email',
        'description' => 'The e-mail address of the user for this virtual host.',
        'required' => true
    ],
    'mode' => [
        'prefix' => 'm',
        'longPrefix' => 'mode',
        'description' => 'Defines the mode to use (LAMP/proxy).',
        'required' => true
    ],
    'proxy' => [
        'prefix' => 'p',
        'longPrefix' => 'proxy',
        'description' => 'Sets the URL to proxy to if mode is set to proxy.'
    ],
    'shell' => [
        'prefix' => 's',
        'longPrefix' => 'shell',
        'description' => 'Sets the user\'s shell.',
        'required' => true,
        'defaultValue' => '/bin/bash'
    ],
    'php' => [
        'prefix' => 'V',
        'longPrefix' => 'php',
        'description' => 'Sets the PHP version to use if mode is set to LAMP.',
    ]
]);

// Parse Arguments
try {
    $cli->arguments->parse();
} catch (League\CLImate\Exceptions\InvalidArgumentException $e) {
    $cli->to('error')->red($e->getMessage());
    exit(1);
}

// Get the required fields
$domainList = $cli->arguments->get('domain');
$user = $cli->arguments->get('user');
$email = $cli->arguments->get('email');
$mode = $cli->arguments->get('mode');
$shell = $cli->arguments->get('shell');
$proxy = null;
if ($cli->arguments->defined('proxy')) {
    $proxy = $cli->arguments->get('proxy');
}
$php = null;
if ($cli->arguments->defined('php')) {
    $php = $cli->arguments->get('php');
}
$phpVersion = "php"; // Used for naming format

// Check if modes are valid
$modes = [
    'LAMP', 'proxy'
];
if (!in_array($mode, $modes)) {
    $cli->to('error')->red('Invalid mode \'' . $mode . '\'');
    exit(1);
}

// If mode is set to 'proxy', check if a valid proxy URL is set.
if ($mode == 'proxy' && empty($proxy)) {
    $cli->to('error')->red('A proxy URL must be set if the mode is set to \'proxy\'.');
    exit(1);
}

// Check if username exists
if (is_dir("/home/{$user}")) {
    $cli->to('error')->red('The user \'' . $user . '\' already exists.');
    exit(1);
}

// If mode is LAMP, check if a valid PHP is supplied
if ($mode == 'LAMP') {
    // Check if a PHP version is supplied
    if (!$cli->arguments->defined('php')) {
        $cli->to('error')->red('A valid PHP version must be specified if the mode is set to \'LAMP\'.');
        exit(1);
    }
    $php = $cli->arguments->get('php');
    $phpVersion .= str_replace('.', '', $php);
    // Check if the supplied PHP version is installed
    if (!is_dir("/opt/remi/{$phpVersion}")) {
        $cli->to('error')->red('PHP version \'' . $php . '\' is not installed or is misconfigured.');
        exit(1);
    }
}

// Generate passwords
$password = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(16))), 0, 16);
$mysql_password = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(16))), 0, 16);

// Create the user
exec("useradd -s /bin/bash -m -p \$(openssl passwd -1 {$password}) {$user}");
exec("usermod -aG apache {$user}");
exec("usermod -aG redis {$user}");

// Create the directories
exec("su {$user} -c 'mkdir -p /home/{$user}/{data,logs,www,acme-challenge}'");
exec("mkdir -p /etc/stackman/tmp");

// Set variables
$fpmDirectory = "/run/php/{$php}/";
$fpmSocket = $fpmDirectory . $user . ".sock";
if (!is_dir($fpmDirectory)) {
    exec("mkdir -p {$fpmDirectory}");
}
$domains = explode(",", $domainList);
$domain = $domains[0]; // Primary domain
$aliases = "";
for ($x = 1; $x <= count($domains) - 1; $x++) {
    $aliases .= "{$domains[$x]} "; // Include a space here, we'll trim that later.
}
$aliases = trim($aliases);
$variables = [
    "domain" => $domain,
    "server_alias" => $aliases,
    "server_alias_line" => "",
    "document_root" => "/home/{$user}/www/public",
    "user" => $user,
    "fpm_socket" => $fpmSocket,
    "ssl_cert" => "/etc/stackman/tmp/{$domain}-fullchain.pem",
    "ssl_key" => "/etc/stackman/tmp/{$domain}-privkey.pem"
];
if (count($aliases) > 0) {
    $variables['server_alias_line'] = "ServerAlias {$aliases}";
}

// Generate dummy SSL
exec("openssl req -x509 -nodes -days 7300 -newkey rsa:2048 -keyout /etc/stackman/tmp/{$domain}-privkey.pem -out /etc/stackman/tmp/{$domain}-fullchain.pem -subj \"/C=PE/ST=Lima/L=Lima/O=Acme Inc. /OU=IT Department/CN=acme.com\"");

// Perform mode-specific actions
if ($mode == 'LAMP') { // If mode is LAMP
    // Create the apache virtual host file
    if (!is_dir("/etc/httpd/vhosts.d")) {
        exec("mkdir -p /etc/httpd/vhosts.d");
    }

    // Create the Apache Template File
    $apacheTemplate = file_get_contents('templates/lamp-apache.tmpl');
    foreach ($variables as $variable => $value) {
        $apacheTemplate = str_replace("%{$variable}%", $value, $apacheTemplate);
    }

    // Write the Apache virtual host file
    file_put_contents("/etc/httpd/vhosts.d/{$domain}.conf", $apacheTemplate);
    
    // Create the PHP-FPM Template File
    $fpmTemplate = file_get_contents('templates/lamp-fpm.tmpl');
    foreach ($variables as $variable => $value) {
        $fpmTemplate = str_replace("%{$variable}%", $value, $apacheTemplate);
    }

    // Write the PHP-FPM configuration file
    file_put_contents($fpmSocket, $fpmTemplate);

    // Restart services
    exec("systemctl reload httpd");
    exec("systemctl restart {$phpVersion}-php-fpm");

    // Done
    $cli->lightGreen()->out("Done. Please view the new access details below.");
} elseif ($mode == 'proxy') { // If mode is Proxy
}
