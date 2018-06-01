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

        // Initial standard (http:80) configuration in order to go through Letsencrypt challenge.

        $content = $this->render(__DIR__.'/NNN-sub.domain.tld-init.conf.twig', [
            'fqdn' => $fqdn,
            'email' => $email,
            'dir' => $dir,
        ]);

        $available = sprintf('/etc/apache2/sites-available/000-%s-init.conf', $fqdn);
        $enabled = sprintf('/etc/apache2/sites-enabled/000-%s-init.conf', $fqdn);
        file_put_contents($available, $content);
        $this->exec('ln -sf :available :enabled', [
            'available' => $available,
            'enabled' => $enabled,
        ]);

        $this->exec('service apache2 restart');

        // Create SSL certificate and clear up temp configuration

        if (!is_file(sprintf('/etc/letsencrypt/renewal/%s.conf', $fqdn))) {
            $this->exec('certbot --non-interactive --agree-tos --email :email certonly --webroot --webroot-path=:webroot --domains :fqdn', [
                'webroot' => sprintf('%s/exposed', $dir),
                'email' => $this->getParameter('admin_email'),
                'fqdn' => $fqdn,
            ]);
        }

        $this->exec('rm :available :enabled', [
            'available' => $available,
            'enabled' => $enabled,
        ]);

        // Final and complete (http:80 and https:443) configuration

        $content = $this->render(__DIR__.'/NNN-sub.domain.tld.conf.twig', [
            'fqdn' => $fqdn,
            'email' => $email,
            'dir' => $dir,
        ]);

        $available = sprintf('/etc/apache2/sites-available/000-%s.conf', $fqdn);
        $enabled = sprintf('/etc/apache2/sites-enabled/000-%s.conf', $fqdn);
        file_put_contents($available, $content);
        $this->exec('ln -sf :available :enabled', [
            'available' => $available,
            'enabled' => $enabled,
        ]);

        $this->exec('service apache2 restart');

        $this->success('Website now available at https://%s.', $fqdn);
    }

    public function edit($fqdn)
    {
        $backupId = $this->createBackup(sprintf('Editing website %s', $fqdn));

        edit:

        $file = sprintf('/etc/apache2/sites-available/000-%s.conf', $fqdn);

        $this->exec(sprintf('%s %s', $this->getParameter('editor'), $file), [], true);
        $this->info('This is your configuration for website %s', $fqdn);
        $this->raw(file_get_contents($file));

        switch ($this->prompt('Is this configuration ok?', ['yes', 'edit', 'abort'])) {
            case 'yes':
                $this->exec('service apache2 restart');
                $this->success('Successfully edited website %s', $fqdn);
                break;
            case 'edit':
                goto edit;
            case 'abort':
                $this->restoreBackup($backupId);
                $this->removeBackup($backupId);
                $this->info('Website edition has been cancelled.');
                break;
        }
    }

    public function remove($fqdn)
    {
        $this->createBackup(sprintf('Removing website %s', $fqdn));

        $files = [
            '/etc/apache2/sites-available/000-%s-init.conf',
            '/etc/apache2/sites-available/000-%s-init.conf',
            '/etc/apache2/sites-enabled/000-%s-init.conf',
            '/etc/apache2/sites-enabled/000-%s-init.conf',
        ];

        foreach ($files as $file) {
            $this->exec('rm -f :file', ['file' => sprintf($file, $fqdn)]);
        }

        if (is_file(sprintf('/etc/letsencrypt/renewal/%s.conf', $fqdn))) {
            $this->exec('certbot delete --non-interactive --apache --agree-tos --cert-name :fqdn', [
                'fqdn' => $fqdn,
            ]);
        }

        $this->exec('service apache2 restart');

        $this->success('Successfully removed website %s.', $fqdn);
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

        $this->exec('cp -r /etc/apache2 :dir', [
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
        $this->exec('service apache2 stop');

        $this->createBackup(sprintf('Restoring backup %s', $id));

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
        $dir = $this->getParameter('backup_dir') . '/website';

        if (!is_dir($dir)) {
            $this->exec('mkdir -p :dir', [
                'dir' => $dir,
            ]);
        }

        return $dir;
    }
}
