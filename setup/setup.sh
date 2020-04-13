#!/bin/bash
# Stackman Setup Script
# https://github.com/liamdemafelix/stackman

# Make sure we're root
if [ "x$(id -u)" != 'x0' ]; then
    echo 'Error: this script can only be executed by root'
    exit 1
fi

# Only CentOS 7 is supported!
if [ ! -e '/etc/redhat-release' ]; then
    echo 'Stackman only runs on CentOS 7.'
    exit 1
fi
if [ -z "$(cat /etc/redhat-release | grep 'CentOS Linux release 7')" ]; then
    echo 'Stackman only runs on CentOS 7.'
    exit 1
fi

# Generate a MySQL Root Password
MYSQL_ROOT_PASSWORD=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 16 | head -n 1)

# Disable SELinux now and on boot
sudo setenforce 0
sudo sed -i 's/SELINUX=enforcing/SELINUX=disabled/g' /etc/selinux/config
sudo sed -i 's/SELINUX=enforcing/SELINUX=disabled/g' /etc/sysconfig/selinux

# Update the system
sudo /bin/yum update -y

# Install the Remi repository
echo "+ Installing the Remi repository +"
sudo /bin/rpm -Uvh https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm
sudo /bin/rpm -Uvh http://rpms.famillecollet.com/enterprise/remi-release-7.rpm

# Install yum-utils
echo "+ Installing yum-utils +"
sudo /bin/yum install yum-utils -y

# Set the system time
echo "+ Syncing system time +"
sudo /bin/yum install ntpdate -y
echo '#!/bin/sh' > /etc/cron.daily/ntpdate
echo "$(which ntpdate) -s pool.ntp.org" >> /etc/cron.daily/ntpdate
chmod 775 /etc/cron.daily/ntpdate
ntpdate -s pool.ntp.org

# Install Apache
echo "+ Installing Apache +"
sudo /bin/yum install httpd -y
sudo /bin/mkdir -p /etc/httpd/vhosts.d
mv -f /etc/httpd/conf/httpd.conf /etc/httpd/conf/httpd.conf.stock
wget https://github.com/liamdemafelix/stackman/raw/master/setup/apache/httpd.conf -O /etc/httpd/conf/httpd.conf
sed -i 's/SSLProtocol all/#SSLProtocol all/g' /etc/httpd/conf.d/ssl.conf
sed -i 's/SSLCipherSuite HIGH/#SSLCipherSuite HIGH/g' /etc/httpd/conf.d/ssl.conf
sed -i 's/#SSLHonorCipherOrder/SSLHonorCipherOrder/g' /etc/httpd/conf.d/ssl.conf
sed -i '/SSLEngine on/a SSLCipherSuite EECDH+AESGCM:EDH+AESGCM:AES256+EECDH:AES256+EDH' /etc/httpd/conf.d/ssl.conf
sed -i '/SSLCipherSuite EECDH+AESGCM:EDH+AESGCM:AES256+EECDH:AES256+EDH/a SSLProtocol All -SSLv2 -SSLv3 -TLSv1 -TLSv1.1' /etc/httpd/conf.d/ssl.conf
sed -i '/SSLProtocol All -SSLv2 -SSLv3 -TLSv1 -TLSv1.1/a SSLCompression off' /etc/httpd/conf.d/ssl.conf
sudo systemctl start httpd
sudo systemctl enable httpd
rm -f /etc/httpd/conf.d/welcome.conf

# Install MySQL
echo "+ Installing MySQL +"
cat >/etc/yum.repos.d/MariaDB.repo <<EOL
[mariadb]
name = MariaDB
baseurl = http://yum.mariadb.org/10.4/centos7-amd64
gpgkey=https://yum.mariadb.org/RPM-GPG-KEY-MariaDB
gpgcheck=1
EOL
sudo /bin/yum install MariaDB-server MariaDB-client -y
sudo systemctl start mariadb
sudo systemctl enable mariadb
mysqladmin -u root password ${MYSQL_ROOT_PASSWORD}
rm -f /root/.my.cnf
cat > /root/.my.cnf <<EOL
[client]
user=root
password="${MYSQL_ROOT_PASSWORD}"
EOL
chmod 600 /root/.my.cnf
mysql -e "DELETE FROM mysql.user WHERE User=''"
mysql -e "DROP DATABASE test" >/dev/null 2>&1
mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%'"
mysql -e "DELETE FROM mysql.user WHERE user='' or password='';"
mysql -e "FLUSH PRIVILEGES"

# Install PHP 7.0, 7.1, 7.2, 7.3 and 7.4
sudo /bin/yum install nano git php70-php php70-php-fpm php70-php-mysql php70-php-json php70-php-xml php70-php-mbstring php70-php-intl php70-php-zip zip unzip php70-php-curl php70-php-xmlrpc php70-php-soap php70-php-gd php70-php-imagick php70-php-redis php71-php php71-php-fpm php71-php-mysql php71-php-json php71-php-xml php71-php-mbstring php71-php-intl php71-php-zip zip unzip php71-php-curl php71-php-xmlrpc php71-php-soap php71-php-gd php71-php-imagick php71-php-redis php72-php php72-php-fpm php72-php-mysql php72-php-json php72-php-xml php72-php-mbstring php72-php-intl php72-php-zip zip unzip php72-php-curl php72-php-xmlrpc php72-php-soap php72-php-gd php72-php-imagick php72-php-redis php73-php php73-php-fpm php73-php-mysql php73-php-json php73-php-xml php73-php-mbstring php73-php-intl php73-php-zip zip unzip php73-php-curl php73-php-xmlrpc php73-php-soap php73-php-gd php73-php-imagick php73-php-redis php74-php php74-php-fpm php74-php-mysql php74-php-json php74-php-xml php74-php-mbstring php74-php-intl php74-php-zip zip unzip php74-php-curl php74-php-xmlrpc php74-php-soap php74-php-gd php74-php-imagick php74-php-redis -y
mkdir -p "/run/php/7.0"
mkdir -p "/run/php/7.1"
mkdir -p "/run/php/7.2"
mkdir -p "/run/php/7.3"
mkdir -p "/run/php/7.4"
sed -i 's/listen = 127.0.0.1:9000/listen = \/run\/php\/7.0\/php-fpm.sock/g' /etc/opt/remi/php70/php-fpm.d/www.conf
sed -i 's/listen = 127.0.0.1:9000/listen = \/run\/php\/7.1\/php-fpm.sock/g' /etc/opt/remi/php71/php-fpm.d/www.conf
sed -i 's/listen = 127.0.0.1:9000/listen = \/run\/php\/7.2\/php-fpm.sock/g' /etc/opt/remi/php72/php-fpm.d/www.conf
sed -i 's/listen = 127.0.0.1:9000/listen = \/run\/php\/7.3\/php-fpm.sock/g' /etc/opt/remi/php73/php-fpm.d/www.conf
sed -i 's/listen = 127.0.0.1:9000/listen = \/run\/php\/7.4\/php-fpm.sock/g' /etc/opt/remi/php74/php-fpm.d/www.conf
sudo systemctl start php70-php-fpm
sudo systemctl start php71-php-fpm
sudo systemctl start php72-php-fpm
sudo systemctl start php73-php-fpm
sudo systemctl start php74-php-fpm
sudo systemctl enable php70-php-fpm
sudo systemctl enable php71-php-fpm
sudo systemctl enable php72-php-fpm
sudo systemctl enable php73-php-fpm
sudo systemctl enable php74-php-fpm

# Add PHP 7.2 as the system's default PHP
sudo ln -s /opt/remi/php72/root/usr/bin/php /usr/bin/php

# Set version-specific shortcuts
ln -s /opt/remi/php70/root/usr/bin/php /usr/bin/php7.0
ln -s /opt/remi/php71/root/usr/bin/php /usr/bin/php7.1
ln -s /opt/remi/php72/root/usr/bin/php /usr/bin/php7.2
ln -s /opt/remi/php73/root/usr/bin/php /usr/bin/php7.3
ln -s /opt/remi/php74/root/usr/bin/php /usr/bin/php7.4

# Install Composer
curl -sS https://getcomposer.org/installer | sudo /usr/bin/php -- --install-dir=/usr/local/bin --filename=composer

# Add composer aliases
cat >/usr/local/bin/php7.0-composer <<EOL
#!/bin/bash
/usr/bin/php7.0 /usr/local/bin/composer $@
EOL
chmod +x /usr/local/bin/php7.0-composer
cat >/usr/local/bin/php7.1-composer <<EOL
#!/bin/bash
/usr/bin/php7.1 /usr/local/bin/composer $@
EOL
chmod +x /usr/local/bin/php7.1-composer
cat >/usr/local/bin/php7.2-composer <<EOL
#!/bin/bash
/usr/bin/php7.2 /usr/local/bin/composer $@
EOL
chmod +x /usr/local/bin/php7.2-composer
cat >/usr/local/bin/php7.3-composer <<EOL
#!/bin/bash
/usr/bin/php7.3 /usr/local/bin/composer $@
EOL
chmod +x /usr/local/bin/php7.3-composer
cat >/usr/local/bin/php7.4-composer <<EOL
#!/bin/bash
/usr/bin/php7.4 /usr/local/bin/composer $@
EOL
chmod +x /usr/local/bin/php7.4-composer

# Add to firewall rules
sudo /bin/yum install firewalld -y
sudo systemctl start firewalld
sudo systemctl enable firewalld
sudo /bin/firewall-cmd --permanent --zone=public --add-service=http
sudo /bin/firewall-cmd --permanent --zone=public --add-service=https
sudo /bin/firewall-cmd --reload

# Install the Redis server
sudo /bin/yum install redis -y
sudo systemctl start redis
sudo systemctl enable redis
sudo sed -i 's/port 6379/port 0/g' /etc/redis.conf
sudo sed -i 's/\# unixsocket \/tmp\/redis.sock/unixsocket \/run\/redis\/redis.sock/g' /etc/redis.conf
sudo sed -i 's/\# unixsocketperm 700/unixsocketperm 770/g' /etc/redis.conf
sudo systemctl restart redis

# Install Certbot
sudo /bin/yum install certbot python2-certbot-apache -y

# Install the Stackman Binary
sudo git clone https://github.com/liamdemafelix/stackman.git /usr/local/stackman
cd /usr/local/stackman
composer install
ln -s /usr/local/stackman/stackman /usr/local/bin/stackman
chmod +x /usr/local/bin/stackman
cd ~

# Done
echo -e "\nYour system has been successfully set up for Stackman. Your MySQL root password is saved in ~/.my.cnf. A reboot is now recommended."
