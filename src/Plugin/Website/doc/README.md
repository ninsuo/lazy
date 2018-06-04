# Websites & Certificates

Using apache2, website management is really easy.

## Installation

Install the PHP repositories to be sure to download the latest version.
Here, it is 7.1 but check before installing.

```
apt-get install apt-transport-https lsb-release ca-certificates
wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
apt-get update
```

Now install the whole thing in a row:

```sh
apt-get install \
mysql-server redis-server sqlite \
php7.1 php7.1-cli php7.1-common \
php7.1-curl php7.1-gd php7.1-mysql php7.1-sqlite3 php-redis \
php-ssh2 php7.1-mcrypt php7.1-mcrypt php7.1-zip php7.1-intl \
php7.1-xml apache2 libapache2-mod-php7.1
```

Install php dependancy manager:

```sh
php -r "readfile('https://getcomposer.org/installer');" | php
mv composer.phar /bin/composer
```

Visit `http://62.210.207.60` to check if everything is fine.

## Apache tweaking

We could stop here, but let's tweak a bit the apache configuration. Open `/etc/apache2/apache2.conf` and remove the following blocks:

```apache
<Directory /usr/share>
        AllowOverride None
    	Require all granted
</Directory>

<Directory /var/www/>
        Options Indexes FollowSymLinks
        AllowOverride None
        Require all granted
</Directory>
```

If you're using a decent hosting, they should have given you a hostname from which your server becomes available (for me, that's `sd-50799.dedibox.fr`). This will be really helpful considering that you don't have any domain name bound to your server yet, and certificates can't be attributed to ip addresses.

Type the following:

```
mkdir -p /var/www/sd-50799.dedibox.fr/exposed
cd /var/www/sd-50799.dedibox.fr/exposed
echo "Hello, world!" >> index.html
```

Let's edit `/etc/apache2/sites-available/000-default.conf`, replace everything by:

```apacheconfig
<Directory /var/www/sd-50799.dedibox.fr/exposed>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
</Directory>

<VirtualHost *:80>
        ServerName sd-50799.dedibox.fr
        ServerAdmin alain@fuz.org
        DocumentRoot /var/www/sd-50799.dedibox.fr/exposed/
        ErrorLog ${APACHE_LOG_DIR}/error.log
        CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

We rename this file for better consistency and restart apache:

```sh
cd /etc/apache2/sites-available
mv 000-default.conf 000-sd-50799.dedibox.fr.conf
cd ../sites-enabled
rm 000-default.conf
ln -s ../sites-available/000-sd-50799.dedibox.fr.conf ./
service apache2 restart
```

Test it! Have a look to [http://sd-50799.dedibox.fr](http://sd-50799.dedibox.fr)

## SSL and certbot

First, enable mod-ssl, mod-rewrite and mod-suexec on apache:

```sh
a2enmod ssl
a2enmod suexec
a2enmod rewrite
service apache2 restart
```

Then, install certbot:

Add the following line to `/etc/apt/sources.list`

```
echo 'deb http://ftp.debian.org/debian stretch-backports main' >> /etc/apt/sources.list 
```

Note that Debian 9 is "stretch" and Debian 7 is "jessie", don't mess it!

Run the following commands one by one because of questions asked:

```
apt-get update
apt-get install software-properties-common dirmngr
apt-get -t stretch-backports install certbot python-certbot-apache
```

Now, install your first certificate:

```
certbot certonly --agree-tos --email alain@fuz.org --webroot --webroot-path=/var/www/sd-50799.dedibox.fr/exposed/ --domains sd-50799.dedibox.fr
```

Put the following configuration in `/etc/apache2/sites-available/000-sd-50799.dedibox.fr.conf`:

```apacheconfig
<Directory /var/www/sd-50799.dedibox.fr/exposed>
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

<VirtualHost *:80>
    ServerName sd-50799.dedibox.fr

    # Note that no SSL certificate will be generated for wilcards
    # ServerAlias www.sd-50799.dedibox.fr
    # ServerAlias *.sd-50799.dedibox.fr

    ServerAdmin alain@fuz.org
    DocumentRoot /var/www/sd-50799.dedibox.fr/exposed
    ErrorLog /var/www/sd-50799.dedibox.fr/logs/error.log
    CustomLog /var/www/sd-50799.dedibox.fr/logs/access.log combined

    # Comment this to avoid redirecting all HTTP traffic to HTTPS
    RewriteEngine on
    RewriteCond %{SERVER_NAME} =sd-50799.dedibox.fr
    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]

    # Uncomment this if you wish to redirect all traffic from *.sd-50799.dedibox.fr to sd-50799.dedibox.fr
    # Don't forget to create the *.sd-50799.dedibox.fr ServerAlias
    #
    # RewriteEngine on
    # RewriteCond %{HTTP_HOST} !^sd-50799.dedibox.fr$ [NC]
    # RewriteRule ^(.*)$ https://sd-50799.dedibox.fr/$1 [R=301,NC,L]

</VirtualHost>

<VirtualHost *:443>
    ServerName sd-50799.dedibox.fr

    # Note that no SSL certificate will be generated for wilcards
    # ServerAlias www.sd-50799.dedibox.fr
    # ServerAlias *.sd-50799.dedibox.fr

    ServerAdmin alain@fuz.org
    DocumentRoot /var/www/sd-50799.dedibox.fr/exposed
    ErrorLog /var/www/sd-50799.dedibox.fr/logs/error.log
    CustomLog /var/www/sd-50799.dedibox.fr/logs/access.log combined

    SSLCertificateFile /etc/letsencrypt/live/sd-50799.dedibox.fr/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/sd-50799.dedibox.fr/privkey.pem
    #Include /etc/letsencrypt/options-ssl-apache.conf

    # Uncomment this if you wish to redirect all traffic from *.sd-50799.dedibox.fr to sd-50799.dedibox.fr
    # Don't forget to create the *.sd-50799.dedibox.fr ServerAlias
    #
    # RewriteEngine on
    # RewriteCond %{HTTP_HOST} !^sd-50799.dedibox.fr$ [NC]
    # RewriteRule ^(.*)$ https://sd-50799.dedibox.fr/$1 [R=301,NC,L]

</VirtualHost>
```

Create missing directories and restart apache:

```
mkdir /var/www/sd-50799.dedibox.fr/logs
chown -R www-data:www-data /var/www
service apache2 restart
```

Visit `https://sd-50799.dedibox.fr` to check if everything is working.

Add your first crontab for auto-renewing certificates:

```
crontab -e
```

```cron
# check certificates expiration every hour at :00
0 * * * * certbot renew --agree-tos --quiet 2>&1 >/dev/null
```

You are ready to go, if you want more information about what does lazy, you
can read the documentation [here](website.md).
