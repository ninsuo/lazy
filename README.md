# lazy

A command-line tool to manage websites, emails and other stuffs on Debian servers

## Installation

First, prepare your system by reading [that doc](doc/linux.md).

Now, install lazy:

```
sudo su root
cd
git clone https://github.com/ninsuo/lazy.git
cd lazy
composer install
cp config.dist.yml config.yml  
ln -s /root/lazy/lazy.php /bin/lazy
```

## Usage

All commands are available by typing `lazy -h`.

### Enroll a domain name

```
root@beastsys:~/lazy# lazy domain add beast.systems
03/06/2018 10:31:50: Successfully backed up in 996650db-50a1-4b3c-afaf-ec7dc8f85330 (Creating domain beast.systems)
03/06/2018 10:31:50: Successfully enrolled beast.systems.
03/06/2018 10:31:50: Successfully backed up in 4622513f-9907-41bf-8169-a98af9f17cc7 (Setting domain beast.systems as primary)
03/06/2018 10:31:51: Successfully set domain name beast.systems as primary.
root@beastsys:~/lazy# lazy domain list
+---------------+---------+
| Domain Name   | Primary |
+---------------+---------+
| beast.systems | yes     |
+---------------+---------+
root@beastsys:~# lazy domain backups
+--------------------------------------+---------------------+-----------------------------------------+
| ID                                   | Date                | Notes                                   |
+--------------------------------------+---------------------+-----------------------------------------+
| 4622513f-9907-41bf-8169-a98af9f17cc7 | 2018-06-03 10:31:50 | Setting domain beast.systems as primary |
| 996650db-50a1-4b3c-afaf-ec7dc8f85330 | 2018-06-03 10:31:50 | Creating domain beast.systems           |
+--------------------------------------+---------------------+-----------------------------------------+
```

