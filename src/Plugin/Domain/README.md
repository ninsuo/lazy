# Domain names

We are going to manage domain names ourselves using bind9 daemon.

## System requirements

Ok, let's take it seriously. We have a server, an IP and an unbound domain name `beast.systems`. We need to set up our primary DNS server.

```sh
sudo apt-get install bind9 bind9utils
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

## Usage without lazy

Now, let's declare our forward zone configuration file in `/etc/bind/named.conf.local`. Add the following:

```
zone "beast.systems" {
     type master;
     file "/etc/bind/db.beast.systems";
     allow-transfer {
        62.210.207.60;
        217.70.177.40;
     };
     allow-update { none; };
};
```

Note: here, `217.70.177.40` is gandi's (my registrar) given secondary dns.

Now, create the zone in `/etc/bind/db.beast.systems`:

```zone
;
; BIND data file for beast.systems
;
$TTL    86400
$ORIGIN beast.systems.
@       IN      SOA     ns.beast.systems. alain.fuz.org. (
                        1706171243      ; Serial
                        604800          ; Refresh
                        86400           ; Retry
                        2419200         ; Expire
                        604800 )        ; Negative Cache TTL
;
@       IN      NS      ns.beast.systems.
@       IN      TXT     "v=spf1 +a +mx +ip4:62.210.207.60 -all"
@       IN      A       62.210.207.60
ns      IN      A       62.210.207.60
the     IN      A       62.210.207.60
*       IN      A       62.210.207.60
```

As serial, put a timestamp based number (like YYMMDDHHII) to ensure its uniqueness.

Replace `beast.systems` by your domain name, and `62.210.207.60` by your server's IP address. `alain.fuz.org.` can be anything you want, ending with a dot.

**The spf1 record is used to allow this domain to send emails. Never put a typo here, this may arm your domain reputation for years, resulting on your emails to end up as spam in most providers' mailboxes.**

You can obviously add all your dns records at the bottom of this file, for example we can already set up our reverse dns to have a fancy name when requests leave the server (I just added `the`, to end up with `the.beast.systems`).

Now, restart your bind9 server:

```sh
sudo service bind9 restart
```

We now need to configure the reverse zone. In `/etc/bind/named.conf.local`, you should add:

```
zone "207.210.62.in-addr.arpa" {
     type master;
     file "/etc/bind/db.207.210.62";
};
```

Note: IP of my server is `62.210.207.60`, so I built `207.210.62` using the 3 first numbers of my IP in reverse order. 

Now open `/etc/bind/db.207.210.62` and add the following content:

```zone
;
; BIND reverse data file for 62.210.207.XXX interface
;
$TTL    86400
@       IN      SOA     ns.beast.systems. root.beast.systems. (
                        1706171315      ; Serial
                         604800         ; Refresh
                          86400         ; Retry
                        2419200         ; Expire
                         604800 )       ; Negative Cache TTL
;
@       IN      NS      ns.beast.systems.
60      IN      PTR     ns.beast.systems.
```

Replace `beast.systems` by your domain name, use a Serial based on the current date (here, 17/06/17 13:15 becomes `1706171315`), and the `60` at the bottom left by the last digit of your IP address.

Restart again your bind9 server:

```sh
sudo service bind9 restart
```

Check your syslog to find eventual errors.

```sh
sudo tail -250f /var/log/syslog
```

You can also use dig, the DNS lookup utility, to check your configuration:

```sh
dig @localhost beast.systems
```

Now, in the registrar where your domain `beast.systems` is stored:

- add a `NS IN A 62.210.207.60` record in the zone file

- set a glue domain record `ns` that points to your ip address `62.210.207.60`

- wait that `ns.beast.systems` respond to ping with the right ip address (it can take several hours)

- request to change your name servers and use `ns.beast.systems` as primary server, and use your registrar's secondary nameserver as a backup.

- go to google's public dns [flush cache](https://developers.google.com/speed/public-dns/cache) page, and flush your domain name.

- when you'll be able to ping the.beast.systems, it will mean your primary server is all set up and ready. 

## Regular backups

Not required for this.
