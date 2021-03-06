<?php

/**
 * ---------------------------------------
 * issue-ssl: Issues an SSL Certificate
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
        'description' => 'Defines the domain name to use in the SSL issuance command. Accepts multiple values separated by commas, with the first value being the primary domain.',
        'required' => true
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

// System IP
$systemIP = exec('curl -4s icanhazip.com');
if (!filter_var($systemIP, FILTER_VALIDATE_IP)) {
    $cli->to('error')->red("Cannot retrieve system IP address.");
    exit(1);
}

// Set the domains
$domainArray = explode(",", $domain);
$domains = "";
foreach ($domainArray as $dom) {
    // Check if they're actually domains
    if (!filter_var($dom, FILTER_VALIDATE_DOMAIN)) {
        $cli->to('error')->red("{$dom} is an invalid domain.");
        exit(1);
    }
    // Make sure the domains are pointing to our IP address
    $domIP = gethostbyname($dom);
    if ($domIP != $systemIP) {
        $cli->to('error')->red("{$dom} ({$domIP}) is not pointing to the system IP address! Your system IP is {$systemIP}.");
        exit(1);
    } else {
        $domains .= " -d {$dom}";
    }
}

// Read the virtual host if it exists
$vhost = "/etc/httpd/vhosts.d/{$domainArray[0]}.conf";
if (!is_file($vhost)) {
    $cli->to('error')->red('The virtual host configuration for ' . $domain . ' does not exist.');
    exit(1);
}
$vhostData = file_get_contents($vhost);

// Get the email address
preg_match('/\#\ Email\:\ [a-zA-Z0-9\_\.]+\@[a-zA-Z0-9\-\.]+/m', $vhostData, $emailMatches);
if (count($emailMatches) == 0) {
    $cli->to('error')->red('Virtual host metadata corrupted: no match for email.');
    exit(1);
}
$email = str_replace("# Email: ", "", $emailMatches[0]);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $cli->to('error')->red('Invalid e-mail address. Virtual host metadata is probably corrupted.');
    exit(1);
}

// Issue SSL Certificates via Certbot
exec("certbot --apache certonly {$domains} --rsa-key-size 4096 --non-interactive --agree-tos -m {$email} --cert-name {$domainArray[0]}");
exec("ln -sf /etc/letsencrypt/live/{$domainArray[0]}/privkey.pem /etc/stackman/{$domainArray[0]}-privkey.pem");
exec("ln -sf /etc/letsencrypt/live/{$domainArray[0]}/cert.pem /etc/stackman/{$domainArray[0]}-cert.pem");
exec("sed -i '/SSLCertificateKeyFile/a SSLCertificateChainFile \"\/etc\/letsencrypt\/live\/{$domainArray[0]}\/chain.pem\"' /etc/httpd/vhosts.d/{$domainArray[0]}.conf");

// Delete SSL Certificates
exec("rm -f /etc/stackman/tmp/{$domain}-privkey.pem /etc/stackman/tmp/{$domain}-fullchain.pem");

// Restart Apache
exec("systemctl reload httpd");

// Done
$cli->lightGreen()->out("An SSL certificate request has been processed for the specified domains.");
