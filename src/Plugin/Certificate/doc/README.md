# Websites & Certificates

Using letsencrypt, certificate management is almost entirely automatic.

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

Restart apache:

```
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
can read the documentation [here](certificate.md).
