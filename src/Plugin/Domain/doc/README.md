# Domain names

We are going to manage domain names ourselves using bind9 daemon.

## System requirements

Ok, let's take it seriously. We have a server, an IP and an unbound domain name `beast.systems`. We need to set up our primary DNS server.

```sh
apt-get install bind9 bind9utils
```

Let's set our dns server to IPv4 in `/etc/default/bind9`, change `OPTIONS` to be the following:

```ini
OPTIONS="-4 -u bind"
```

Then, in `/etc/bind/named.conf.options`, set the following:

```
acl blacklist {
        0.0.0.0/8;  192.0.2.0/24; 224.0.0.0/3;
        10.0.0.0/8; 172.16.0.0/12; 192.168.0.0/16;
};

options {
        directory "/var/cache/bind";

        forwarders {
                8.8.8.8;
                8.8.4.4;
        };

        dnssec-validation auto;
        auth-nxdomain no;    # conform to RFC1035
        listen-on port 53 { any; };

        blackhole { blacklist; };
        version "DNS server";
};
```

Restart bind9:

```sh
sudo service bind9 restart
```

Now, [enroll your domain name](enroll.md), and you'll be good to go.

More info about how bind9 works: read [how were developed `lazy domain`](domain.md)

