<?php

namespace Lazy\Plugin\Domain;

use Lazy\Core\Base\BaseService;
use Lazy\Core\Exception\StopExecutionException;

class DomainRepository extends BaseService
{
    /**
     * @return Domains
     */
    public function getDomains()
    {
        $domains = new Domains();

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

    public function create($name, $email)
    {
        $domains = $this->getDomains();

        if (in_array($name, $domains->domains)) {
            throw new StopExecutionException('Domain %s already exists!', $name);
        }

        $backupId = $this->createBackup(sprintf('Creating domain %s', $name));

        $content = $this->render(__DIR__.'/db.domain.tld.twig', [
            'domain' => $name,
            'email' => trim(str_replace('@', '.', $email), '.'),
            'timestamp' => time(),
            'server_ip' => $this->getParameter('server_ip'),
        ]);

        $file = sprintf('/etc/bind/db.%s', $name);
        file_put_contents($file, $content);

        edit:

        $this->exec(sprintf('%s %s', $this->getParameter('editor'), $file), [], true);
        $this->info('This is your configuration for domain %s', $name);
        $this->raw(file_get_contents($file));

        switch ($this->prompt('Is this configuration ok?', ['yes', 'edit', 'abort'])) {
            case 'yes':
                $this->exec('service bind9 restart');
                $this->success('Successfully enrolled %s', $name);
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

    public function edit($name)
    {
        $domains = $this->getDomains();

        if (!in_array($name, $domains->domains)) {
            throw new StopExecutionException('Domain %s does not exists!', $name);
        }

        $backupId = $this->createBackup(sprintf('Editing domain %s', $name));

        edit:

        $file = sprintf('/etc/bind/db.%s', $name);

        $this->exec(sprintf('%s %s', $this->getParameter('editor'), $file), [], true);
        $this->info('This is your configuration for domain %s', $name);
        $this->raw(file_get_contents($file));

        switch ($this->prompt('Is this configuration ok?', ['yes', 'edit', 'abort'])) {
            case 'yes':
                $this->exec('service bind9 restart');
                $this->success('Successfully edited %s', $name);
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
