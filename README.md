# lazy

A command-line tool to manage websites, emails and other stuffs on Debian servers

## Installation

First, prepare your system by reading [that doc](doc/linux.md).

Now, install lazy:

```
sudo su root
cd /root/
git clone https://github.com/ninsuo/lazy.git
cd lazy
composer install
cp config.dist.yml config.yml  
ln -s /root/lazy/lazy.php /bin/lazy
```

## Usage

All commands are available by typing `lazy -h`.
