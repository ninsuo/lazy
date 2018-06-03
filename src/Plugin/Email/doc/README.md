# Emails

a tester?
https://www.rosehosting.com/blog/setup-and-configure-a-mail-server-with-postfixadmin/







As Debian is an OS for lumberjacks, it's a fucking damn pain to install a secure 
email environment manually. So we are going to install and tweak iRedMail solution,

## Install iRedMail

Go to https://www.iredmail.org/download.html and copy download link location.

Now, in your server, be root:

```
su
cd /root/
wget https://bitbucket.org/zhb/iredmail/downloads/iRedMail-0.9.8.tar.bz2
tar xjvf iRedMail-0.9.8.tar.bz2 
cd iRedMail-0.9.8
bash iRedMail.sh
```

Do the following when prompted:

- press OK at the welcome message
- leave /var/vmail
- leave nginx checked
- tick MariaDB
- choose a mysql password
- enter your first domain name (eg: beast.systems)
- choose a password for postmaster@beast.systems
- tick roundcube, iRedAdmin and fail2ban, untick the others
- proceed to installation

Installation takes a while. Once finished, `reboot`.

## Add a few records in your DNS:

Install `dnsutils`, that will be useful later on.

```
apt-get install dnsutils
```

Add the following DNS in your bind zone configuration:

```
        10      MX      beast.systems
```

TODO dkim record

## Get rid of Nginx

Because Nginx takes the ports 80 & 443, it will conflict with our apache.

We will need:

- to change both ports on nginx
- to create websites that redirect to the nginx at the right port

1) edit `/etc/nginx/sites-available/00-default-ssl.conf`

Replace 443 by 4430

2) edit `/etc/nginx/sites-available/00-default.conf`

Replace 80 by 800

3) restart nginx: `service nginx restart`

