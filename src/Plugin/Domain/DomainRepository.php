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
            array_map(function ($elem) {
                $domain = substr(basename($elem), 3);
                if (preg_match('/^[0-9\.]+$/', $domain) || in_array($domain, ['empty', 'local', 'root'])) {
                    return false;
                }

                return $domain;
            }, glob('/etc/bind/db.*'))
        );

        $file = '/etc/bind/db.'.$this->getArpa();

        if (is_file($file)) {
            $primary = $this->exec("cat :file | grep IN | grep SOA | cut -d '\t' -f 4 | cut -d ' ' -f 1", [
                'file' => $file,
            ]);

            $domains->primary = substr($primary->stdout, 0, -1);
        }

        return $domains;
    }

    public function create($domain)
    {
        $this->createBackup(sprintf('Creating domain %s', $domain));

        $content = $this->render(__DIR__.'/db.domain.tld.twig', [
            'domain'         => $domain,
            'email'          => $this->getParameter('admin_email'),
            'timestamp'      => time(),
            'server_ip'      => $this->getParameter('server_ip'),
            'server_reverse' => $this->getParameter('server_reverse'),
        ]);

        $file = sprintf('/etc/bind/db.%s', $domain);
        file_put_contents($file, $content);

        $this->regenerateLocalConfiguration();

        $domains = $this->getDomains();
        $this->regenerateArpa($domains, $domains->primary ? $domains->primary : $domain);

        $this->exec('service bind9 restart');
        $this->success('Successfully enrolled %s.', $domain);
    }

    public function edit($domain)
    {
        $this->createBackup(sprintf('Editing domain %s', $domain));

        $file = sprintf('/etc/bind/db.%s', $domain);

        $this->exec(sprintf('%s %s', $this->getParameter('editor'), $file), [], true);
        $this->info('This is your configuration for domain %s', $domain);
        $this->raw(file_get_contents($file));

        $this->exec('service bind9 restart');
        $this->success('Successfully edited domain name %s', $domain);
    }

    public function remove($domain)
    {
        $this->createBackup(sprintf('Removing domain %s', $domain));

        $file = sprintf('/etc/bind/db.%s', $domain);
        $this->exec('rm :file', [
            'file' => $file,
        ]);

        $this->regenerateLocalConfiguration();

        $domains = $this->getDomains();
        $this->regenerateArpa($domains, $domains->primary !== $domain ? $domains->primary : null);

        $this->exec('service bind9 restart');

        $this->success('Successfully removed domain name %s.', $domain);
    }

    public function setPrimary($domain)
    {
        $this->createBackup(sprintf('Setting domain %s as primary', $domain));

        $this->regenerateArpa($domain);

        $this->exec('service bind9 restart');

        $this->success('Successfully set domain name %s as primary.', $domain);
    }

    public function listBackups()
    {
        $backups = array_map(function ($v) {
            $json       = json_decode(file_get_contents($v), true);
            $json['id'] = basename(substr($v, 0, -5));

            return $json;
        }, glob(sprintf('%s/*.json', $this->getBackupDirectory())));

        usort($backups, function ($a, $b) {
            return strtotime($a['date']) < strtotime($b['date']);
        });

        return $backups;
    }

    public function createBackup($title)
    {
        $this->cleanBackups();

        $id        = Uuid::uuid4();
        $backupDir = sprintf('%s/%s', $this->getBackupDirectory(), $id);

        $this->exec('rsync -lra /etc/bind :dir', [
            'dir' => $backupDir,
        ]);

        $backupTrace = sprintf('%s/%s.json', $this->getBackupDirectory(), $id);

        file_put_contents($backupTrace, json_encode([
            'date'  => date('Y-m-d H:i:s'),
            'notes' => $title,
        ]));

        $this->success('Successfully backed up in %s (%s)', $id, $title);

        return $id;
    }

    public function restoreBackup($id)
    {
        $this->createBackup(sprintf('Restoring backup %s', $id));

        $sourceDir = sprintf('%s/%s', $this->getBackupDirectory(), $id);
        $targetDir = '/etc/bind';

        $this->exec('rm -rf :target', [
            'target' => $targetDir,
        ]);

        $this->exec('rsync -lra :source :target', [
            'source' => $sourceDir,
            'target' => $targetDir,
        ]);

        $this->exec('service bind9 restart');

        $this->success('Successfully restored backup %s', $id);
    }

    /**
     * @return string
     */
    public function getBackupDirectory()
    {
        $dir = $this->getParameter('backup_dir').'/domain';

        if (!is_dir($dir)) {
            $this->exec('mkdir -p :dir', [
                'dir' => $dir,
            ]);
        }

        return $dir;
    }

    protected function regenerateLocalConfiguration()
    {
        $content = $this->render(__DIR__.'/named.conf.local.twig', [
            'domains'       => $this->getDomains()->domains,
            'arpa'          => $this->getArpa(),
            'server_ip'     => $this->getParameter('server_ip'),
            'secondary_dns' => $this->getParameter('secondary_dns'),
        ]);

        $file = sprintf('/etc/bind/named.conf.local');
        file_put_contents($file, $content);
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

    protected function removeBackup($id)
    {
        $backupDir  = sprintf('%s/%s', $this->getBackupDirectory(), $id);
        $backupFile = sprintf('%s.json', $backupDir);

        $this->exec('rm -rf :dir :file', [
            'dir'  => $backupDir,
            'file' => $backupFile,
        ]);
    }

    protected function regenerateArpa(Domains $domains, $primary = null)
    {
        $file = sprintf('/etc/bind/db.%s', $this->getArpa());

        if (count($domains->domains) == 0) {
            file_put_contents($file, '');

            return;
        }

        if (is_null($primary)) {
            $primary = reset($domains->domains);
        }

        $content = $this->render(__DIR__.'/db.xxx.xxx.xxx.twig', [
            'domains'   => $this->getDomains()->domains,
            'primary'   => $primary,
            'email'     => $this->getParameter('admin_email'),
            'timestamp' => time(),
            'arpa'      => $this->getArpa(),
            'revArpa'   => $this->getReverseArpa(),
        ]);

        $file = sprintf('/etc/bind/db.%s', $this->getArpa());
        file_put_contents($file, $content);
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
