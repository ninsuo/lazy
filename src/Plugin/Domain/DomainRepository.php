<?php

namespace Lazy\Plugin\Domain;

use Lazy\Core\Base\BaseService;

class DomainRepository extends BaseService
{
    /**
     * @return Domains
     */
    public function getDomains()
    {
        $domains = new Domains();

        clearstatcache();

        $domains->domains = array_filter(
            array_map(function($elem) {
                $domain = substr(basename($elem), 3);
                if (preg_match('/^[0-9\.]+$/', $domain) || in_array($domain, ['empty', 'local', 'root'])) {
                    return false;
                }

                return $domain;
            }, glob('/etc/bind/db.*'))
        );

        $file = '/etc/bind/db.' . $this->getArpa();

        if (is_file($file)) {
            $primary = $this->exec("cat :file | grep IN | grep SOA | cut -d '\t' -f 4 | cut -d ' ' -f 1", [
                'file' => $file,
            ]);

            $domains->primary = substr($primary->stdout, 0, -1);
        }

        return $domains;
    }

    public function create($domain, $email)
    {
        $backupId = $this->createBackup(sprintf('Creating domain %s', $domain));

        $content = $this->render(__DIR__.'/db.domain.tld.twig', [
            'domain' => $domain,
            'email' => $email,
            'timestamp' => time(),
            'server_ip' => $this->getParameter('server_ip'),
        ]);

        $file = sprintf('/etc/bind/db.%s', $domain);
        file_put_contents($file, $content);

        edit:

        $this->exec(sprintf('%s %s', $this->getParameter('editor'), $file), [], true);
        $this->info('This is your configuration for domain %s', $domain);
        $this->raw(file_get_contents($file));

        switch ($this->prompt('Is this configuration ok?', ['yes', 'edit', 'abort'])) {
            case 'yes':
                $this->exec('service bind9 restart');
                $this->success('Successfully enrolled %s', $domain);

                $domains = $this->getDomains();
                if (!$domains->primary) {
                    $this->setPrimary($domain);
                }

                break;
            case 'edit':
                goto edit;
            case 'abort':
                unlink($file);
                $this->removeBackup($backupId);
                $this->info('Domain creation has been cancelled.');
                break;
        }
    }

    public function edit($domain)
    {
        $backupId = $this->createBackup(sprintf('Editing domain %s', $domain));

        edit:

        $file = sprintf('/etc/bind/db.%s', $domain);

        $this->exec(sprintf('%s %s', $this->getParameter('editor'), $file), [], true);
        $this->info('This is your configuration for domain %s', $domain);
        $this->raw(file_get_contents($file));

        switch ($this->prompt('Is this configuration ok?', ['yes', 'edit', 'abort'])) {
            case 'yes':
                $this->exec('service bind9 restart');
                $this->success('Successfully edited domain name %s',
                    $domain);
                break;
            case 'edit':
                goto edit;
            case 'abort':
                $this->restoreBackup($backupId);
                $this->removeBackup($backupId);
                $this->info('Domain edition has been cancelled.');
                break;
        }
    }

    public function remove($domain, $email)
    {
        $this->createBackup(sprintf('Removing domain %s', $domain));
        $file = sprintf('/etc/bind/db.%s', $domain);
        $this->exec('rm :file', [
            'file' => $file
        ]);
        $this->exec('service bind9 restart');
        $this->success('Successfully removed domain name %s', $domain);

        $domains = $this->getDomains();
        if ($domains->primary === $domain) {
            if (count($domains) == 0) {
                $this->removePrimary();
            } else {
                $this->setPrimary(reset($domains->domains), $email);
            }
        }
    }

    public function setPrimary($domain, $email)
    {
        $content = $this->render(__DIR__.'/db.domain.tld.twig', [
            'domains' => $this->getDomains(),
            'domain' => $domain,
            'email' => $email,
            'timestamp' => time(),
            'arpa' => $this->getArpa(),
        ]);

        $file = sprintf('/etc/bind/db.%s', $this->getArpa());
        file_put_contents($file, $content);

        $this->exec('service bind9 restart');
        $this->success('Successfully set domain name %s as primary.', $domain);
    }

    public function removePrimary()
    {
        $file = sprintf('/etc/bind/db.%s', $this->getArpa());

        $this->exec('rm :file', [
            'file' => $file,
        ]);

        $this->success('Successfully removed the reverse dns data file.');
    }

    public function createBackup($title)
    {

    }

    public function restoreBackup($id)
    {

    }

    public function removeBackup($id)
    {

    }

    /**
     * @return string
     */
    private function getArpa()
    {
        $ip = explode('.', $this->getParameter('server_ip'));
        return $ip[2].'.'.$ip[1].'.'.$ip[0];
    }
}
