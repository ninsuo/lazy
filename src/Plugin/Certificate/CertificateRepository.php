<?php

namespace Lazy\Plugin\Certificate;

use Lazy\Core\Base\BaseService;
use Ramsey\Uuid\Uuid;

class CertificateRepository extends BaseService
{
    public function getCertificates()
    {
        $certificates = [];
        $output       = $this->exec("ls /etc/letsencrypt/renewal/*.conf")->stdout;
        foreach (array_filter(explode("\n", $output)) as $line) {
            $certificates[] = substr($line, strlen('/etc/letsencrypt/renewal/'), -5);
        }

        return $certificates;
    }

    public function isCertificate($fqdn)
    {
        return in_array($fqdn, $this->getCertificates());
    }

    public function create($fqdn)
    {
        $this->createBackup(sprintf('Creating certificate %s', $fqdn));

        $dir = sprintf('%s/%s', $this->getParameter('web_dir'), $fqdn);

        // Create SSL certificate

        $this->exec('certbot --non-interactive --agree-tos --email :email certonly --webroot --webroot-path=:webroot --domains :fqdn', [
            'webroot' => sprintf('%s/exposed', $dir),
            'email'   => $this->getParameter('admin_email'),
            'fqdn'    => $fqdn,
        ]);

        // Regenerating Apache configuration for SSL (http:80 and https:443) configuration

        $content = $this->render(__DIR__.'/NNN-sub.domain.tld.conf.twig', [
            'fqdn'  => $fqdn,
            'email' => $this->getParameter('admin_email'),
            'dir'   => $dir,
        ]);

        $available = sprintf('/etc/apache2/sites-available/000-%s.conf', $fqdn);
        $enabled   = sprintf('/etc/apache2/sites-enabled/000-%s.conf', $fqdn);
        file_put_contents($available, $content);
        $this->exec('ln -sf :available :enabled', [
            'available' => $available,
            'enabled'   => $enabled,
        ]);

        $this->exec('service apache2 restart');

        $this->success('Website %s available at https://%s.', sprintf('%s/exposed', $dir), $fqdn);
    }

    public function remove($fqdn)
    {
        $this->createBackup(sprintf('Removing certificate %s', $fqdn));

        $files = [
            '/etc/apache2/sites-available/000-%s.conf',
            '/etc/apache2/sites-enabled/000-%s.conf',
        ];

        foreach ($files as $file) {
            $this->exec('rm -f :file', ['file' => sprintf($file, $fqdn)]);
        }

        $this->exec('certbot delete --non-interactive --apache --agree-tos --cert-name :fqdn', [
            'fqdn' => $fqdn,
        ]);

        $this->exec('service apache2 restart');

        $this->success('Successfully removed certificate %s.', $fqdn);
        $this->info('Regenerating website configuration without HTTPS...');

        $this->container['website.repository']->create($fqdn);
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

        $this->exec('mkdir -p :dir', ['dir' => $backupDir]);

        $this->exec('rsync -lra /etc/apache2/ :dir', [
            'dir' => sprintf('%s/apache2', $backupDir),
        ]);

        $this->exec('rsync -lra /etc/letsencrypt/ :dir', [
            'dir' => sprintf('%s/letsencrypt', $backupDir),
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
        $this->exec('service apache2 stop');

        $this->createBackup(sprintf('Restoring backup %s', $id));

        $sourceDir = sprintf('%s/%s', $this->getBackupDirectory(), $id);

        $this->exec('rm -rf /etc/apache2');
        $this->exec('rsync -lra :source /etc/apache2', [
            'source' => sprintf('%s/apache2/', $sourceDir),
        ]);

        $this->exec('rm -rf /etc/letsencrypt');
        $this->exec('rsync -lra :source /etc/letsencrypt', [
            'source' => sprintf('%s/letsencrypt/', $sourceDir),
        ]);

        $this->exec('service apache2 start');

        $this->success('Successfully restored backup %s', $id);
    }

    public function getBackupDirectory()
    {
        $dir = $this->getParameter('backup_dir').'/certificate';

        if (!is_dir($dir)) {
            $this->exec('mkdir -p :dir', [
                'dir' => $dir,
            ]);
        }

        return $dir;
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
}
