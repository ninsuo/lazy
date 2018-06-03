# Emails

All Debian distributions come with exim, which is easier to configure than postfix.

But after a few comparisons, I decided to install postfix anyway, because it has a postfix-mysql package that will let me manage mailboxes on the mysql server.

It seems to be well integrated with Dovecot, an IMAP server, which will complete my installation.

## Packages

```
sudo apt-get install postfix postfix-mysql dovecot-core dovecot-imapd dovecot-pop3d dovecot-lmtpd dovecot-mysql mysql-server
```

## Configure the DNS

Add the following in your DNS configuration:

```
	IN      MX      10 beast.systems.
```

## MySQL preparation

Run `mysql` as root.

```
sudo mysql -u root
```

Then create the schema.

```mysql
CREATE DATABASE mailserver;

GRANT ALL PRIVILEGES ON mailserver.* TO 'mailserver'@'127.0.0.1' IDENTIFIED BY 'somepassword';

GRANT SELECT ON mailserver.* TO 'mailuser'@'127.0.0.1' IDENTIFIED BY '0TFQfLOLFvGTtq3NEBT8bYHnRlH48Sfm';

FLUSH PRIVILEGES;

USE mailserver;

CREATE TABLE `virtual_domains` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `virtual_users` (
  `id` int(11) NOT NULL auto_increment,
  `domain_id` int(11) NOT NULL,
  `password` varchar(106) NOT NULL,
  `email` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  FOREIGN KEY (domain_id) REFERENCES virtual_domains(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `virtual_aliases` (
  `id` int(11) NOT NULL auto_increment,
  `domain_id` int(11) NOT NULL,
  `source` varchar(100) NOT NULL,
  `destination` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (domain_id) REFERENCES virtual_domains(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

We can now insert our first domain, email and alias.

```mysql
INSERT INTO `mailserver`.`virtual_domains`
  (`id`, `name`)
VALUES
  (1, 'beast.systems');

INSERT INTO `mailserver`.`virtual_users`
  (`id`, `domain_id`, `email`, `password`)
VALUES
  (1, 1, 'alain@beast.systems', ENCRYPT('password', CONCAT('$6$', SUBSTRING(SHA(RAND()), -16))));

INSERT INTO `mailserver`.`virtual_aliases`
  (`id`, `domain_id`, `source`, `destination`)
VALUES
  (1, 1, 'alias@beast.systems', 'alain@beast.systems');
```

## Configure Postfix

Let's do a backup first:

```
cp /etc/postfix/main.cf /etc/postfix/main.cf.orig
```

Now, put this in `/etc/postfix/main.cf`:

```
smtpd_banner = $myhostname ESMTP $mail_name (Ubuntu)
biff = no
append_dot_mydomain = no
readme_directory = no

# TLS (self-signed)
smtpd_tls_cert_file=/etc/dovecot/dovecot.pem
smtpd_tls_key_file=/etc/dovecot/private/dovecot.pem
smtpd_use_tls=yes
smtpd_tls_auth_only = yes
smtp_tls_security_level = may
smtpd_tls_security_level = may

# Enabling SMTP for authenticated users, and handing off authentication to Dovecot
smtpd_sasl_type = dovecot
smtpd_sasl_path = private/auth
smtpd_sasl_auth_enable = yes

smtpd_recipient_restrictions =
        permit_sasl_authenticated,
        permit_mynetworks,
        reject_unauth_destination

myhostname = beast.systems
alias_maps = hash:/etc/aliases
alias_database = hash:/etc/aliases
myorigin = /etc/mailname
mydestination =  $myhostname, beast.systems, sd-50799.dedibox.fr, localhost.dedibox.fr, localhost
relayhost =
mynetworks = 127.0.0.0/8 [::ffff:127.0.0.0]/104 [::1]/128
mailbox_size_limit = 0
recipient_delimiter = +
inet_interfaces = all

# Handing off local delivery to Dovecot's LMTP, and telling it where to store mail
virtual_transport = lmtp:unix:private/dovecot-lmtp

# Virtual domains, users, and aliases
virtual_mailbox_domains = mysql:/etc/postfix/mysql-virtual-mailbox-domains.cf
virtual_mailbox_maps = mysql:/etc/postfix/mysql-virtual-mailbox-maps.cf
virtual_alias_maps = mysql:/etc/postfix/mysql-virtual-alias-maps.cf,
        mysql:/etc/postfix/mysql-virtual-email2email.cf
```

In `/etc/postfix/mysql-virtual-mailbox-domains.cf`, add:

```mysql
user = mailuser
password = mailuserpass
hosts = 127.0.0.1
dbname = mailserver
query = SELECT 1 FROM virtual_domains WHERE name='%s'
```

In `/etc/postfix/mysql-virtual-mailbox-maps.cf`, add:

```mysql
user = mailuser
password = mailuserpass
hosts = 127.0.0.1
dbname = mailserver
query = SELECT 1 FROM virtual_users WHERE email='%s'
```

In `/etc/postfix/mysql-virtual-alias-maps.cf`, add:

```mysql
user = mailuser
password = mailuserpass
hosts = 127.0.0.1
dbname = mailserver
query = SELECT destination FROM virtual_aliases WHERE source='%s'
```

In `/etc/postfix/mysql-virtual-email2email.cf`, add:

```mysql
user = mailuser
password = mailuserpass
hosts = 127.0.0.1
dbname = mailserver
query = SELECT email FROM virtual_users WHERE email='%s'
```

Restart postfix:

```
sudo service postfix restart
```

Test that the domain, email and alias inserted on mysql can be found:

```
postmap -q beast.systems mysql:/etc/postfix/mysql-virtual-mailbox-domains.cf
postmap -q alain@beast.systems mysql:/etc/postfix/mysql-virtual-mailbox-maps.cf
postmap -q alias@beast.systems mysql:/etc/postfix/mysql-virtual-alias-maps.cf
```

Backup the master configuration:

```
cp /etc/postfix/master.cf /etc/postfix/master.cf.orig
```

Then ensure that there's the following content inside `/etc/postfix/master.cf`:

```
#
# Postfix master process configuration file.  For details on the format
# of the file, see the master(5) manual page (command: "man 5 master").
#
# Do not forget to execute "postfix reload" after editing this file.
#
# ==========================================================================
# service type  private unpriv  chroot  wakeup  maxproc command + args
#               (yes)   (yes)   (yes)   (never) (100)
# ==========================================================================
smtp      inet  n       -       -       -       -       smtpd
#smtp      inet  n       -       -       -       1       postscreen
#smtpd     pass  -       -       -       -       -       smtpd
#dnsblog   unix  -       -       -       -       0       dnsblog
#tlsproxy  unix  -       -       -       -       0       tlsproxy
submission inet n       -       -       -       -       smtpd
  -o syslog_name=postfix/submission
  -o smtpd_tls_security_level=encrypt
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_client_restrictions=permit_sasl_authenticated,reject
  -o milter_macro_daemon_name=ORIGINATING
smtps     inet  n       -       -       -       -       smtpd
  -o syslog_name=postfix/smtps
  -o smtpd_tls_wrappermode=yes
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_client_restrictions=permit_sasl_authenticated,reject
  -o milter_macro_daemon_name=ORIGINATING
```

Change permissions & restart

```
chmod -R o-rwx /etc/postfix
service postfix restart
```

## Configure Dovecot

First, backup all the things!

```
cp /etc/dovecot/dovecot.conf /etc/dovecot/dovecot.conf.orig
cp /etc/dovecot/conf.d/10-mail.conf /etc/dovecot/conf.d/10-mail.conf.orig
cp /etc/dovecot/conf.d/10-auth.conf /etc/dovecot/conf.d/10-auth.conf.orig
cp /etc/dovecot/dovecot-sql.conf.ext /etc/dovecot/dovecot-sql.conf.ext.orig
cp /etc/dovecot/conf.d/10-master.conf /etc/dovecot/conf.d/10-master.conf.orig
cp /etc/dovecot/conf.d/10-ssl.conf /etc/dovecot/conf.d/10-ssl.conf.orig
```

Then, in `/etc/dovecot/dovecot.conf`, put:

```
protocols = imap pop3 lmtp
```

just below `!include_try /usr/share/dovecot/protocols.d/*.protocol`.

Now open `/etc/dovecot/conf.d/10-mail.conf` and change the following keys:

```
mail_location = maildir:/var/mail/vhosts/%d/%n
mail_privileged_group = mail
```

If you check `ls -ld /var/mail`, you should see ownership like:

```
drwxrwsr-x 2 root mail 4096 Feb 12 09:55 /var/mail
```

Add the vmail group:

```
groupadd -g 5000 vmail
useradd -g vmail -u 5000 vmail -d /var/mail
```

Now create the vhost and change permissions:

```
mkdir -p /var/mail/vhosts/beast.systems
chown -R vmail:vmail /var/mail
```


https://www.linode.com/docs/email/postfix/email-with-postfix-dovecot-and-mysql/

