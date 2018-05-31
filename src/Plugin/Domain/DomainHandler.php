<?php

namespace Lazy\Plugin\Domain;

use Lazy\Core\Base\BaseHandler;
use Lazy\Core\Exception\StopExecutionException;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Regex;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;

class DomainHandler extends BaseHandler
{
    public function handleList(Args $args, IO $io)
    {
        $domains = $this->getRepository()->getDomains();

        $table = new Table();
        $table->setHeaderRow(['Domain Name', 'Primary']);
        foreach ($domains->domains as $domain) {
            $table->addRow([
                $domain,
                $domain === $domains->primary ? '<green>yes</green>' : '<red>no</red>',
            ]);
        }

        $table->render($io);
    }

    public function handleAdd(Args $args, IO $io)
    {
        $domain = $this->sanitizeDomain($args->getArgument('domain'));

        $domains = $this->getRepository()->getDomains();
        if (in_array($domain, $domains->domains)) {
            throw new StopExecutionException('Domain %s already exists!', $domain);
        }

        $email = $this->sanitizeEmail();

        $this->getRepository()->create($domain, $email);
    }

    public function handleEdit(Args $args, IO $io)
    {
        $domain = $this->sanitizeDomain($args->getArgument('domain'));

        $domains = $this->getRepository()->getDomains();
        if (!in_array($domain, $domains->domains)) {
            throw new StopExecutionException('Domain %s does not exist!', $domain);
        }

        $this->getRepository()->edit($domain);
    }

    public function handleRemove(Args $args, IO $io)
    {
        $domain = $this->sanitizeDomain($args->getArgument('domain'));

        $domains = $this->getRepository()->getDomains();
        if (!in_array($domain, $domains->domains)) {
            throw new StopExecutionException('Domain %s does not exist!', $domain);
        }

        $email = $this->sanitizeEmail();

        $this->getRepository()->remove($domain, $email);
    }

    public function handlePrimary(Args $args, IO $io)
    {
        if (is_null($args->getArgument('domain'))) {
            $domains = $this->getRepository()->getDomains();
            if ($domains->primary) {
                $this->info('Primary domain name is: <b>%s</b>', $domains->primary);
            } else {
                $this->info('There are no primary domain name so far.');
            }

            return;
        }

        $domain = $this->sanitizeDomain($args->getArgument('domain'));

        $domains = $this->getRepository()->getDomains();
        if (!in_array($domain, $domains->domains)) {
            throw new StopExecutionException('Domain %s does not exist!', $domain);
        }
        if ($domain === $domains->primary) {
            throw new StopExecutionException('%s is already the primary domain for this server.', $domain);
        }

        $email = $this->sanitizeEmail();

        $this->getRepository()->setPrimary($domain, $email);
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
            $this->error('Domains backup ID #%s does not exist.', $id);
        }

        $this->getRepository()->restoreBackup($id);
    }

    public function sanitizeDomain($domain)
    {
        $this->validate('domain', $domain, new Regex('!^[a-zA-Z0-9\.\-]+$!'));

        return trim(mb_strtolower($domain), '.');
    }

    public function sanitizeEmail()
    {
        $email = $this->getParameter('admin_email');

        $this->validate('email', $email, new Email());

        return trim(preg_replace("/[^a-z0-9]/", '.', $email), '.');
    }

    /**
     * @return DomainRepository
     */
    private function getRepository()
    {
        return $this->container['domain.repository'];
    }
}