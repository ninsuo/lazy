<?php

namespace Lazy\Plugin\Website;

use Lazy\Core\Base\BaseService;
use Ramsey\Uuid\Uuid;
use Webmozart\Console\UI\Component\Table;

class WebsiteRepository extends BaseService
{
    /**
     * @return array
     */
    public function getWebsites()
    {
        $websites = [];
        $output = $this->exec("grep -Ri 'ServerName' /etc/apache2/sites-available")->stdout;
        foreach (array_filter(explode("\n", $output)) as $line) {
            $tokens = array_values(array_filter(explode(' ', str_replace("\t", ' ', $line))));
            if (count($tokens) == 3) {
                $websites[] = $tokens[2];
            }
        }

        return array_unique($websites);
    }

    public function create($fqdn, $email)
    {
        $this->createBackup(sprintf('Creating website %s', $fqdn));

        // Create directory structure

        $dir = sprintf('%s/%s', $this->getParameter('web_dir'), $fqdn);
        $exposed = sprintf('%s/exposed', $dir);

        $this->exec('mkdir -p :dir', ['dir' => sprintf('%s/app', $dir)]);
        $this->exec('mkdir -p :dir', ['dir' => $exposed]);
        file_put_contents(sprintf('%s/index.html', $exposed), sprintf('Hello, %s!', $fqdn));
        $this->exec('mkdir -p :dir', ['dir' => sprintf('%s/logs', $dir)]);

        // Standard (http:80) configuration

        $content = $this->render(__DIR__.'/NNN-sub.domain.tld.conf.twig', [
            'fqdn' => $fqdn,
            'email' => $email,
            'dir' => $dir,
        ]);

        $available = sprintf('/etc/apache2/sites-available/000-%s.conf', $fqdn);
        $enabled = sprintf('/etc/apache2/sites-enabled/000-%s.conf', $fqdn);
        file_put_contents($available, $content);
        $this->exec(sprintf('%s %s', $this->getParameter('editor'), $available), [], true);
        $this->exec('ln -sf :available :enabled', [
            'available' => $available,
            'enabled' => $enabled,
        ]);

        // SSL (https:443) configuration

        $content = $this->render(__DIR__.'/NNN-sub.domain.tld-ssl.conf.twig', [
            'fqdn' => $fqdn,
            'email' => $email,
            'dir' => $dir,
        ]);

        $available = sprintf('/etc/apache2/sites-available/000-%s-ssl.conf', $fqdn);
        $enabled = sprintf('/etc/apache2/sites-enabled/000-%s-ssl.conf', $fqdn);
        file_put_contents($available, $content);
        $this->exec(sprintf('%s %s', $this->getParameter('editor'), $available), [], true);
        $this->exec('ln -sf :available :enabled', [
            'available' => $available,
            'enabled' => $enabled,
        ]);

        // Create SSL certificate and restart service

        if (!is_file(sprintf('/etc/letsencrypt/renewal/%s.conf', $fqdn))) {
            $this->exec('certbot --non-interactive --agree-tos --email :email --apache --webroot --webroot-path=:webroot --domains :fqdn', [
                'webroot' => sprintf('%s/exposed', $dir),
                'email' => $this->getParameter('admin_email'),
                'fqdn' => $fqdn,
            ]);
        }

        $this->exec('service apache2 restart');

        $this->success('Website now available at https://%s.', $fqdn);
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


    private function removeCertificate($fqdn)
    {
        if (is_file(sprintf('/etc/letsencrypt/renewal/%s.conf', $fqdn))) {
            $this->exec('certbot delete --non-interactive --apache --agree-tos --cert-name :fqdn', [
                'fqdn' => $fqdn,
            ]);
        }
    }

    public function listBackups()
    {
        $backups = array_map(function($v) {
            $json = json_decode(file_get_contents($v), true);
            $json['id'] = substr($v, 0, -5);
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

        $this->exec('cp -r /etc/apache2 :dir', [
            'dir' => $backupDir,
        ]);

        $backupTrace = sprintf('%s/%s.json', $this->getBackupDirectory(), $id);

        file_put_contents($backupTrace, json_encode([
            'date' => date('Y-m-d H:i:s'),
            'notes' => $title,
        ]));

        $this->success('Successfully backed up websites in %s', $id);
    }

    public function restoreBackup($id)
    {
        $this->exec('service apache2 stop');

        $this->createBackup('Restoring backup #%s', $id);

        $sourceDir = sprintf('%s/%s', $this->getBackupDirectory(), $id);
        $targetDir = '/etc/apache2';

        $this->exec('rm -rf :target', [
            'target' => $targetDir,
        ]);

        $this->exec('cp -r :source :target', [
            'source' => $sourceDir,
            'target' => $targetDir,
        ]);

        $this->exec('service apache2 start');

        $this->success('Successfully restored backup #%s', $id);
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
        $dir = $this->getParameter('backup_dir') . '/website';

        if (!is_dir($dir)) {
            $this->exec('mkdir -p :dir', [
                'dir' => $dir,
            ]);
        }

        return $dir;
    }
}
