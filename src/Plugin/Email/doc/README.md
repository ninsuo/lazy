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

Warning: take note of the postmaster address you just created, it will be required later.

## Postfix

First, let's install Postfix.

```
apt-get install postfix
```

When prompted, 

1) select "Internet Site"

2) leave your server's hostname

Now, create a mapping files:

```
/etc/postfix/sqlite_virtual_alias_maps.cf

user = postfixadmin
password = somepassword
hosts = 127.0.0.1
dbname = postfixadmin
query = SELECT goto FROM alias WHERE address='%s' AND active = '1'

```

```
/etc/postfix/sqlite_virtual_alias_domain_maps.cf

user = postfixadmin
password = somepassword
hosts = 127.0.0.1
dbname = postfixadmin
query = SELECT goto FROM alias,alias_domain WHERE alias_domain.alias_domain = '%d' and alias.address = printf('%u', '@', alias_domain.target_domain) AND alias.active = 1 AND alias_domain.active='1'
```

```
/etc/postfix/sqlite_virtual_alias_domain_catchall_maps.cf

user = postfixadmin
password = somepassword
hosts = 127.0.0.1
dbname = postfixadmin
query  = SELECT goto FROM alias,alias_domain WHERE alias_domain.alias_domain = '%d' and alias.address = printf('@', alias_domain.target_domain) AND alias.active = 1 AND alias_domain.active='1'
```

```
/etc/postfix/sqlite_virtual_domains_maps.cf

user = postfixadmin
password = somepassword
hosts = 127.0.0.1
dbname = postfixadmin
query = SELECT domain FROM domain WHERE domain='%s' AND active = '1'
```

```
/etc/postfix/sqlite_virtual_mailbox_maps.cf

user = postfixadmin
password = somepassword
hosts = 127.0.0.1
dbname = postfixadmin
query = SELECT maildir FROM mailbox WHERE username='%s' AND active = '1'
```

```
/etc/postfix/sqlite_virtual_alias_domain_mailbox_maps.cf

user = postfixadmin
password = somepassword
hosts = 127.0.0.1
dbname = postfixadmin
query = SELECT maildir FROM mailbox,alias_domain WHERE alias_domain.alias_domain = '%d' and mailbox.username = printf('%u', '@', alias_domain.target_domain) AND mailbox.active = 1 AND alias_domain.active='1'
```

Now, run the following commands to configure postfix:

```
postconf -e "myhostname = $(hostname -A)"
 
postconf -e "virtual_mailbox_domains = sqlite:/etc/postfix/sqlite_virtual_domains_maps.cf"
postconf -e "virtual_alias_maps =  sqlite:/etc/postfix/sqlite_virtual_alias_maps.cf, sqlite:/etc/postfix/sqlite_virtual_alias_domain_maps.cf, sqlite:/etc/postfix/sqlite_virtual_alias_domain_catchall_maps.cf"
postconf -e "virtual_mailbox_maps = sqlite:/etc/postfix/sqlite_virtual_mailbox_maps.cf, sqlite:/etc/postfix/sqlite_virtual_alias_domain_mailbox_maps.cf"
 
postconf -e "smtpd_tls_cert_file = /etc/ssl/certs/ssl-cert-snakeoil.pem"
postconf -e "smtpd_tls_key_file = /etc/ssl/private/ssl-cert-snakeoil.key"
postconf -e "smtpd_use_tls = yes"
postconf -e "smtpd_tls_auth_only = yes"
 
postconf -e "smtpd_sasl_type = dovecot"
postconf -e "smtpd_sasl_path = private/auth"
postconf -e "smtpd_sasl_auth_enable = yes"
postconf -e "smtpd_recipient_restrictions = permit_sasl_authenticated, permit_mynetworks, reject_unauth_destination"
 
postconf -e "mydestination = localhost"
postconf -e "mynetworks = 127.0.0.0/8"
postconf -e "inet_protocols = ipv4"
 
postconf -e "virtual_transport = lmtp:unix:private/dovecot-lmtp"
```

Open `/etc/postfix/master.cf` and check that the options for 
`submission inet n` and `smtps inet n` sections match the following:

```
smtp      inet  n       -       y       -       -       smtpd
#smtp      inet  n       -       y       -       1       postscreen
#smtpd     pass  -       -       y       -       -       smtpd
#dnsblog   unix  -       -       y       -       0       dnsblog
#tlsproxy  unix  -       -       y       -       0       tlsproxy
submission inet n       -       y       -       -       smtpd
  -o syslog_name=postfix/submission
  -o smtpd_tls_security_level=encrypt
  -o smtpd_sasl_auth_enable=yes
#  -o smtpd_reject_unlisted_recipient=no
  -o smtpd_client_restrictions=permit_sasl_authenticated,reject
#  -o smtpd_helo_restrictions=$mua_helo_restrictions
#  -o smtpd_sender_restrictions=$mua_sender_restrictions
#  -o smtpd_recipient_restrictions=
#  -o smtpd_relay_restrictions=permit_sasl_authenticated,reject
  -o milter_macro_daemon_name=ORIGINATING
smtps     inet  n       -       y       -       -       smtpd
  -o syslog_name=postfix/smtps
#  -o smtpd_tls_wrappermode=yes
  -o smtpd_sasl_auth_enable=yes
#  -o smtpd_reject_unlisted_recipient=no
  -o smtpd_client_restrictions=permit_sasl_authenticated,reject
#  -o smtpd_helo_restrictions=$mua_helo_restrictions
#  -o smtpd_sender_restrictions=$mua_sender_restrictions
#  -o smtpd_recipient_restrictions=
#  -o smtpd_relay_restrictions=permit_sasl_authenticated,reject
  -o milter_macro_daemon_name=ORIGINATING
```

Enable postfix

```
systemctl enable postfix
systemctl restart postfix
```

## Dovecot

First, install dovecot (obviously)

```
sudo apt-get install dovecot-imapd dovecot-lmtpd dovecot-pop3d dovecot-mysql
```

Now, let's configure... change the values to match each of the following keys.

Edit `/etc/dovecot/conf.d/10-mail.conf`:

```
mail_location = maildir:/var/vmail/%d/%n
mail_privileged_group = mail
mail_uid = vmail
mail_gid = mail
first_valid_uid = 150
last_valid_uid = 150
```

Edit `/etc/dovecot/conf.d/10-auth.conf`:

```
auth_mechanisms = plain login
#!include auth-system.conf.ext
!include auth-sql.conf.ext
```

For the next one, check in your postfixadmin configuration the value of
`$CONF['encrypt']` to ensure it will match with the default_pass_scheme.

For example, I can see:

```
root@beastsys:/var/www/sd-50799.dedibox.fr/postfixadmin-3.2# cat config.local.php |grep encrypt
$CONF['encrypt'] = 'md5crypt';
```

Edit `/etc/dovecot/dovecot-sql.conf.ext`:

```
driver = mysql
connect = host=127.0.0.1 dbname=postfixadmin user=postfixadmin password=somepassword

default_pass_scheme = MD5-CRYPT

user_query = \
   SELECT '/var/vmail/%d/%n' as home, 'maildir:/var/vmail/%d/%n' as mail, \
   150 AS uid, 8 AS gid, printf('dirsize:storage=', quota) AS quota \
   FROM mailbox WHERE username = '%u' AND active = '1'

password_query = \
   SELECT username as user, password, '/var/vmail/%d/%n' as userdb_home, \
   'maildir:/var/vmail/%d/%n' as userdb_mail, 150 as userdb_uid, 8 as userdb_gid \
   FROM mailbox WHERE username = '%u' AND active = '1'
```

Edit `/etc/dovecot/conf.d/10-ssl.conf`:

```
ssl = yes
```

Edit `/etc/dovecot/conf.d/15-lda.conf` (take care to put the same email as
you did when configuring postfix):

```
postmaster_address = postmaster@beast.systems
```

Edit `/etc/dovecot/conf.d/10-master.conf`:

Find the `lmtp` section and change it to:

```
service lmtp {
  unix_listener /var/spool/postfix/private/dovecot-lmtp {
    mode = 0600
    user = postfix
    group = postfix
  }
}
```

Find the `auth` section and change it to:

```
service auth {
  unix_listener /var/spool/postfix/private/auth {
    mode = 0666
    user = postfix
    group = postfix
  }
  unix_listener auth-userdb {
    mode = 0600
    user = vmail
    #group = vmail
  }
  user = dovecot
}
```

Find the `auth-worker` section and change it to:

```
service auth-worker {
  user = vmail
}
```

Change a few permissions:

```
chown -R vmail:dovecot /etc/dovecot
chmod -R o-rwx /etc/dovecot
```

Enable dovcot:

```
systemctl enable dovecot
systemctl restart dovecot
```

## DNS



## Roundcube




## Final word

Debian is an OS for lumberjacks.

