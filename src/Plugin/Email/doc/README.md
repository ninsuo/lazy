# Emails

We are going to use PostfixAdmin schema to install emails, because there are more
documentation than for an installation from scratch. And by the way, the first
ocarina was running email through PostfixAdmin and it worked well, thus it should
be good to go.

## Postfix Admin

Install the following packages first:

```
apt-get install wget nano dbconfig-common sqlite3 php7.1-mbstring php7.1-imap
service apache2 restart
```

Create a virtual user `vmail` which will own all emails


```
useradd -r -u 150 -g mail -d /var/vmail -s /sbin/nologin -c "Virtual Mail User" vmail
mkdir -p /var/vmail
chmod -R 770 /var/vmail
chown -R vmail:mail /var/vmail
```

Create the PostfixAdmin database:

```
mysql
CREATE DATABASE postfixadmin;
GRANT ALL PRIVILEGES ON postfixadmin.* TO 'postfixadmin'@'127.0.0.1' identified by 'somepassword';
```

Create the PostfixAdmin website:

Note: I'm storing it in the website we created in the Website documentation 
earlier, but you can create another website if you wish here.

```
lazy website add sd-50799.dedibox.fr
```

Go to https://sourceforge.net/projects/postfixadmin/files/ and download the
latest version of the project.

```
cd /var/www/sd-50799.dedibox.fr
scp alain@home.fuz.org:~/Downloads/postfixadmin-* ./
tar xzvf postfixadmin-3.2.tar.gz
rm postfixadmin-3.2.tar.gz
cp postfixadmin-3.2/config.inc.php postfixadmin-3.2/config.local.php
emacs -nw postfixadmin-3.2/config.local.php
```

Update the following parameters:

```
$CONF['configured'] = true;
$CONF['database_type'] = 'mysqli';
$CONF['database_host'] = '127.0.0.1';
$CONF['database_user'] = 'postfixadmin';
$CONF['database_password'] = 'somepassword';
$CONF['database_name'] = 'postfixadmin';
$CONF['domain_path'] = 'NO';
$CONF['domain_in_mailbox'] = 'YES';
```

Expose the website:

```
mkdir postfixadmin-3.2/templates_c
chown -R www-data:www-data postfixadmin-3.2
rm -r exposed
ln -s /var/www/sd-50799.dedibox.fr/postfixadmin-3.2/public exposed
```

Now, go to https://sd-50799.dedibox.fr/setup.php and check if everything went fine.

Once done, 

1) set a setup password and add its hash in your config.local.php (at the key setup_password)

2) enter your setup password in the form, and choose an admin email address & password

3) you can finally login at https://sd-50799.dedibox.fr/login.php

## Postfix


## Dovecot




https://www.rosehosting.com/blog/setup-and-configure-a-mail-server-with-postfixadmin/


