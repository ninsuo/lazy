<?php

namespace Lazy\Plugin\Website;

use Lazy\Core\Base\BaseService;
use Ramsey\Uuid\Uuid;

class WebsiteRepository extends BaseService
{
    public function getWebsites()
    {
        $websites = [];
        $output   = $this->exec("grep -Ri 'ServerName' /etc/apache2/sites-available")->stdout;
        foreach (array_filter(explode("\n", $output)) as $line) {
            $tokens = array_values(array_filter(explode(' ', str_replace("\t", ' ', $line))));
            if (count($tokens) == 3) {
                $websites[] = $tokens[2];
            }
        }

        return array_unique($websites);
    }

    public function isWebsite($fqdn)
    {
        return in_array($fqdn, $this->getWebsites());
    }

    public function create($fqdn)
    {
        $this->createBackup(sprintf('Creating website %s', $fqdn));

        // Create directory structure

        $dir     = sprintf('%s/%s', $this->getParameter('web_dir'), $fqdn);
        $exposed = sprintf('%s/exposed', $dir);

        if (!is_dir($dir)) {
            $this->exec('mkdir -p :dir', ['dir' => sprintf('%s/app', $dir)]);
            $this->exec('mkdir -p :dir', ['dir' => $exposed]);
            file_put_contents(sprintf('%s/index.html', $exposed), sprintf('Hello, %s!', $fqdn));
            $this->exec('mkdir -p :dir', ['dir' => sprintf('%s/logs', $dir)]);
            $this->exec('chown -R www-data:www-data :dir', [
                'dir' => $dir,
            ]);
        }

        // Initial standard (http:80) configuration (necessary to go through Letsencrypt challenge).

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

        $this->success('Website %s available at http://%s.', sprintf('%s/exposed', $dir), $fqdn);
    }

    public function edit($fqdn)
    {
        $this->createBackup(sprintf('Editing website %s', $fqdn));

        $file = sprintf('/etc/apache2/sites-available/000-%s.conf', $fqdn);

        $this->exec(sprintf('%s %s', $this->getParameter('editor'), $file), [], true);
        $this->info('This is your configuration for website %s', $fqdn);
        $this->raw(file_get_contents($file));

        $this->exec('service apache2 restart');
        $this->success('Successfully edited website %s', $fqdn);
    }

    public function remove($fqdn)
    {
        $this->createBackup(sprintf('Removing website %s', $fqdn));

        $files = [
            '/etc/apache2/sites-available/000-%s.conf',
            '/etc/apache2/sites-enabled/000-%s.conf',
        ];

        foreach ($files as $file) {
            $this->exec('rm -f :file', ['file' => sprintf($file, $fqdn)]);
        }

        $this->exec('service apache2 restart');

        $dir = sprintf('%s/%s', $this->getParameter('web_dir'), $fqdn);
        if (is_dir($dir)) {
            $this->exec('rm -rf :dir');
        }

        $this->success('Successfully removed website %s.', $fqdn);
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

        $this->exec('cp -r /etc/apache2 :dir', [
            'dir' => sprintf('%s/apache2', $backupDir),
        ]);

        $this->exec('rsync -l :web_dir :dir', [
            'web_dir' => $this->getParameter('web_dir'),
            'dir'     => sprintf('%s/web_dir', $backupDir),
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

        $this->exec('rm -rf /etc/apache2');
        $this->exec('cp -r :source /etc/apache2', [
            'source' => sprintf('%s/apache2', $sourceDir),
        ]);

        $this->exec('rsync -l :source /tmp/:uuid', [
            'source' => sprintf('%s/web_dir', $sourceDir),
            'uuid'   => $uuid = Uuid::uuid4(),
        ]);

        $this->exec('mv :web_dir /tmp/:uuid-old', [
            'web_dir' => $this->getParameter('web_dir'),
            'uuid'    => $uuid,
        ]);

        $this->exec('mv /tmp/:uuid :web_dir', [
            'uuid'    => $uuid,
            'web_dir' => $this->getParameter('web_dir'),
        ]);

        $this->exec('rm -rf /tmp/:uuid-old', [
            'uuid' => $uuid,
        ]);

        $this->exec('service apache2 restart');

        $this->success('Successfully restored backup %s', $id);
    }

    /**
     * @return string
     */
    public function getBackupDirectory()
    {
        $dir = $this->getParameter('backup_dir').'/website';

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
