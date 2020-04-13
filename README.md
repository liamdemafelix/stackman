# Stackman

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Stackman is a stack manager for CentOS 7.x. It automatically sets up your server with:

* Apache Web Server
* Multiple PHP Versions via PHP-FPM (Versions 7.0, 7.1, 7.2, 7.3 and 7.4 upon install, expandable. System default is set to PHP 7.2.)
* MariaDB
* FirewallD
* Redis Server (configured for socket access)
* Let's Encrypt via Certbot
* Composer
* Stackman tool

Stackman supports automated SSL deployment via a wrapper around Certbot through the `stackman -a issue-ssl` command and includes support for reverse proxies.

Virtual hosts are created on a per-user basis and there are currently no plans for single-user use cases.

## The Stackman Tool

The Stackman Tool allows you to automatically configure your virtual servers through the `stackman` command.

1. `stackman -a create-vhost` - Create a new virtual host
2. `stackman -a delete-vhost` - Delete an existing virtual host
3. `stackman -a issue-ssl` - Issue an SSL certificate for a virtual host

> Please note that virtual hosts must be assigned SSL certificates through the `stackman` command only. Do **NOT** directly use Certbot unless you know what you are doing.

Getting started is pretty easy. For instance, to create a new virtual host for PHP 7.0, just supply the necessary commands to `stackman`:

```
stackman -a create-vhost -u johndoe -d example.com -e hello@johndoe.com -m LAMP -V 7.0
```

Please see the [wiki](https://github.com/liamdemafelix/stackman/wiki) for a detailed guide on how to get started, or to see the options available for the commands above.

## Installation

> Please note that the installer assumes that the server it is being installed on is completely fresh.

Get a fresh CentOS 7.x 64-bit server anywhere (we support bare metal, OpenVZ, Xen, KVM and anything else that has packages for a LAMP stack), then run:

```
yum -y install wget
wget https://git.io/fjflD -O - | bash
```

The link pulls the latest installer from this repository's `master` branch. Alternatively, you can link to the full link if you don't trust the URL shortener:

```
yum -y install wget
wget https://github.com/liamdemafelix/stackman/raw/master/setup/setup.sh -O - | bash
```

It will set up all the necessary services for you. It may take a few minutes to complete depending on the specifications of your server.

After the installer has finished, you can now access the `stackman` command via your terminal. Head over to the [wiki](https://github.com/liamdemafelix/stackman/wiki) for a detailed guide on how to get started.

## Contributing & Bug Reporting

Please feel free to fork Stackman and make pull requests. Follow the PSRs when writing your code, pull requests that do not comply with the PSRs will be rejected.

Please report all non-security-related issues in the [issue tracker](https://github.com/liamdemafelix/stackman/issues). For security issues, please send them to [hey@liam.ph](mailto:hey@liam.ph).

## License

Stackman is licensed under the [MIT Open Source](https://github.com/liamdemafelix/stackman/blob/master/LICENSE.md) license.