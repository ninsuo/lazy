<?php

namespace Lazy\Plugin\Website;

use Lazy\Core\Base\BaseHandler;
use Lazy\Core\Exception\StopExecutionException;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Regex;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;

class WebsiteHandler extends BaseHandler
{
    public function handleList(Args $args, IO $io)
    {
        $websites = $this->getRepository()->getWebsites();

        $table = new Table();
        $table->setHeaderRow(['Website FQDN']);
        foreach ($websites as $website) {
            $table->addRow([$website]);
        }

        $table->render($io);
    }

    public function handleAdd(Args $args, IO $io)
    {
        $fqdn = $this->sanitizeWebsite($args->getArgument('fqdn'));

        if (in_array($fqdn, $this->getRepository()->getWebsites())) {
            throw new StopExecutionException('Website %s already exists!', $fqdn);
        }

        $email = $this->sanitizeEmail();

        $this->getRepository()->create($fqdn, $email);
    }

    public function handleEdit(Args $args, IO $io)
    {
        $website = $this->sanitizeWebsite($args->getArgument('fqdn'));

        $websites = $this->getRepository()->getWebsites();
        if (!in_array($website, $websites->domains)) {
            throw new StopExecutionException('Website %s does not exist!', $website);
        }

        $this->getRepository()->edit($website);
    }

    public function handleRemove(Args $args, IO $io)
    {
        $website = $this->sanitizeWebsite($args->getArgument('fqdn'));

        $websites = $this->getRepository()->getWebsites();
        if (!in_array($website, $websites->domains)) {
            throw new StopExecutionException('Website %s does not exist!', $website);
        }

        $email = $this->sanitizeEmail();

        $this->getRepository()->remove($website, $email);
    }

    public function handleListBackups(Args $args, IO $io)
    {
        $backups = $this->getRepository()->listBackups();

        $table = new Table();
        $table->setHeaderRow(['ID', 'Date', 'Notes']);
        foreach ($backups as $backup) {
            $table->addRow([$backup['id'], $backup['date'], $backup['notes']]);
        }

        $table->render($io);
    }

    public function handleBackupNow(Args $args, IO $io)
    {
        $this->getRepository()->createBackup(
            $args->getArgument('notes')
        );
    }

    public function handleRestoreBackup(Args $args, IO $io)
    {
        $id = $args->getArgument('id');

        $config = sprintf(
            '%s/%s.txt',
            $this->getRepository()->getBackupDirectory(),
            str_replace('/', '', $id)
        );

        if (!is_file($config)) {
            $this->error('Websites backup ID #%s does not exist.', $id);
        }

        $this->getRepository()->restoreBackup($id);
    }

    public function sanitizeWebsite($fqdn)
    {
        $this->validate('fqdn', $fqdn, new Regex('!^[a-zA-Z0-9\.\-]+$!'));

        return mb_strtolower($fqdn);
    }

    public function sanitizeEmail()
    {
        $email = $this->getParameter('admin_email');

        $this->validate('email', $email, new Email());

        return $email;
    }

    /**
     * @return WebsiteRepository
     */
    private function getRepository()
    {
        return $this->container['website.repository'];
    }
}