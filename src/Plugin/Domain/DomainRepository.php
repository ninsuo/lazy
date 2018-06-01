<?php

namespace Lazy\Plugin\Domain;

use Lazy\Core\Base\BaseService;
use Ramsey\Uuid\Uuid;

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

        prompt:

        $this->info('This is your configuration for domain %s', $domain);
        $this->raw(file_get_contents($file));

        switch ($this->prompt('Is this configuration ok?', ['yes', 'edit', 'abort'])) {
            case 'yes':
                $this->exec('service bind9 restart');
                $this->success('Successfully enrolled %s.', $domain);

                $domains = $this->getDomains();
                if (!$domains->primary) {
                    $this->setPrimary($domain, $email);
                }

                break;
            case 'edit':
                $this->exec(sprintf('%s %s', $this->getParameter('editor'), $file), [], true);

                goto prompt;
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

        $this->success('Successfully removed domain name %s.', $domain);

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
        $this->createBackup(sprintf('Setting domain %s as primary', $domain));

        $content = $this->render(__DIR__.'/db.xxx.xxx.xxx.twig', [
            'domains' => $this->getDomains()->domains,
            'domain' => $domain,
            'email' => $email,
            'timestamp' => time(),
            'arpa' => $this->getArpa(),
            'revArpa' => $this->getReverseArpa(),
        ]);

        $file = sprintf('/etc/bind/db.%s', $this->getArpa());
        file_put_contents($file, $content);

        $this->exec('service bind9 restart');
        $this->success('Successfully set domain name %s as primary.', $domain);
    }

    protected function removePrimary()
    {
        $file = sprintf('/etc/bind/db.%s', $this->getArpa());

        $this->exec('rm :file', [
            'file' => $file,
        ]);

        $this->success('Successfully removed the reverse dns data file.');
    }

    public function listBackups()
    {
        $backups = array_map(function($v) {
            $json = json_decode(file_get_contents($v), true);
            $json['id'] = basename(substr($v, 0, -5));
            return $json;
        }, glob(sprintf('%s/*.json', $this->getBackupDirectory())));

        usort($backups, function($a, $b) {
            return strtotime($a['date']) < strtotime($b['date']);
        });

        return $backups;
    }

    protected function cleanBackups()
    {
        $exec = $this->exec('ls -t :dir | grep -v json', [
            'dir' => $this->getBackupDirectory(),
        ]);

        $backups = explode("\n", $exec->stdout);

        $count = count($backups);
        if ($count > 9) {
            for ($i = 9; $i < $count; $i++) {
                $this->removeBackup($backups[$i]);
            }
        }
   }

    public function createBackup($title)
    {
        $this->cleanBackups();

        $id = Uuid::uuid4();
        $backupDir = sprintf('%s/%s', $this->getBackupDirectory(), $id);

        $this->exec('cp -r /etc/bind :dir', [
            'dir' => $backupDir,
        ]);

        $backupTrace = sprintf('%s/%s.json', $this->getBackupDirectory(), $id);

        file_put_contents($backupTrace, json_encode([
            'date' => date('Y-m-d H:i:s'),
            'notes' => $title,
        ]));

        $this->success('Successfully backed up in %s (%s)', $id, $title);

        return $id;
    }

    public function restoreBackup($id)
    {
        $this->createBackup('Restoring backup %s', $id);

        $sourceDir = sprintf('%s/%s', $this->getBackupDirectory(), $id);
        $targetDir = '/etc/bind';

        $this->exec('rm -rf :target', [
            'target' => $targetDir,
        ]);

        $this->exec('cp -r :source :target', [
            'source' => $sourceDir,
            'target' => $targetDir,
        ]);

        $this->exec('service bind9 restart');

        $this->success('Successfully restored backup %s', $id);
    }

    protected function removeBackup($id)
    {
        $backupDir = sprintf('%s/%s', $this->getBackupDirectory(), $id);
        $backupFile = sprintf('%s.json', $backupDir);

        $this->exec('rm -rf :dir :file', [
            'dir' => $backupDir,
            'file' => $backupFile,
        ]);
    }

    /**
     * @return string
     */
    public function getBackupDirectory()
    {
        $dir = $this->getParameter('backup_dir') . '/domain';

        if (!is_dir($dir)) {
            $this->exec('mkdir -p :dir', [
                'dir' => $dir,
            ]);
        }

        return $dir;
    }

    /**
     * @return string
     */
    private function getArpa()
    {
        $ip = explode('.', $this->getParameter('server_ip'));
        return $ip[2].'.'.$ip[1].'.'.$ip[0];
    }

    /**
     * @return string
     */
    private function getReverseArpa()
    {
        $ip = explode('.', $this->getParameter('server_ip'));
        return $ip[3];
    }
}
