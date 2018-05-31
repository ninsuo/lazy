# Websites & Certificates

Install the whole LAMP in a row:

```sh
sudo apt-get install \
mysql-server redis-server sqlite \
php7.1 php7.1-cli php7.1-common \
php7.1-curl php7.1-gd php7.1-mysql php7.1-sqlite3 php-redis \
php-ssh2 php7.1-mcrypt php7.1-mcrypt php7.1-zip php7.1-intl \
php7.1-xml apache2 libapache2-mod-php7.1

sudo php -r "readfile('https://getcomposer.org/installer');" | php
sudo mv composer.phar /bin/composer
```

Visit `http://62.210.207.60` to check if everything is fine.

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

We'll install Baest One application at `https://sd-50799.dedibox.fr`, and we want to expose it in a "standard" directory for future domains: `/data/sites/<website>/exposed`.

- our base directory for websites will always be `/data/sites`
- then, the website, here `sd-50799.dedibox.fr`
- then, `exposed` for exposed files (for your front controllers but not your whole app)

For our sample, this will be:

```sh
cd
sudo mkdir -p /data/sites/sd-50799.dedibox.fr/exposed/
sudo chown -R ninsuo:ninsuo /data/sites/
sudo chown -R www-data:www-data /data/sites/sd-50799.dedibox.fr/exposed/
echo '<?php echo "Hello, sd-50799.dedibox.fr!";' >> index.php
sudo -u www-data cp index.php /data/sites/sd-50799.dedibox.fr/exposed/
rm index.php
```

Let's edit `/etc/apache2/sites-available/000-default.conf`, replace everything by:

```apache
<Directory /data/sites/sd-50799.dedibox.fr/exposed>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
</Directory>

<VirtualHost *:80>
        ServerName sd-50799.dedibox.fr
        ServerAdmin alain@fuz.org
        DocumentRoot /data/sites/sd-50799.dedibox.fr/exposed/
        ErrorLog ${APACHE_LOG_DIR}/error.log
        CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

We rename this file for better consistency and restart apache:

```sh
cd /etc/apache2/sites-available
sudo mv 000-default.conf 000-sd-50799.dedibox.fr.conf
cd ../sites-enabled
sudo rm 000-default.conf
sudo ln -s ../sites-available/000-sd-50799.dedibox.fr.conf ./
sudo service apache2 restart
```

Test it! Have a look to [http://sd-50799.dedibox.fr](http://sd-50799.dedibox.fr)

### Apache SSL and certbot

First, enable mod-ssl and mod-suexec on apache:

```sh
sudo a2enmod ssl
sudo a2enmod suexec
sudo service apache2 restart
```

Then, install certbot (run commands one by one because of questions asked):

```
sudo apt-get install software-properties-common
sudo add-apt-repository ppa:certbot/certbot
sudo apt-get update
sudo apt-get install python-certbot-apache 
```

Now, install your first ceritifates:

```
sudo certbot --apache --agree-tos
```

Add your first crontab for auto-renewing certificates:

```cron
# check certificates expiration every hour at :00
0 * * * * certbot renew --agree-tos --quiet 2>&1 >/dev/null
```

Visit `https://sd-50799.dedibox.fr` to check if everything is magic.
