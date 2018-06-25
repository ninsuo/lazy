<?php

namespace Lazy\Plugin\Certificate;

use Lazy\Core\Base\BaseHandler;
use Lazy\Core\Exception\StopExecutionException;
use Symfony\Component\Validator\Constraints\Regex;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;

class CertificateHandler extends BaseHandler
{
    public function handleList(Args $args, IO $io)
    {
        $certificates = $this->getRepository()->getCertificates();

        $table = new Table();
        $table->setHeaderRow(['Certificate FQDN']);
        foreach ($certificates as $certificate) {
            $table->addRow([$certificate]);
        }

        $table->render($io);
    }

    public function handleAdd(Args $args, IO $io)
    {
        $fqdn = $this->sanitizeCertificate($args->getArgument('fqdn'));

        if (in_array($fqdn, $this->getRepository()->getCertificates())) {
            throw new StopExecutionException('Certificate %s already exists!', $fqdn);
        }

        if (!$this->container['website.repository']->isWebsite($fqdn)) {
            throw new StopExecutionException('Create %s website first (to go through Let\'s encrypt challenges).', $fqdn);
        }

        $this->getRepository()->create($fqdn);
    }

    public function handleRemove(Args $args, IO $io)
    {
        $certificate = $this->sanitizeCertificate($args->getArgument('fqdn'));

        $certificates = $this->getRepository()->getCertificates();
        if (!in_array($certificate, $certificates)) {
            throw new StopExecutionException('Certificate %s does not exist!', $certificate);
        }

        $this->getRepository()->remove($certificate);
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
            '%s/%s.json',
            $this->getRepository()->getBackupDirectory(),
            str_replace('/', '', $id)
        );

        if (!is_file($config)) {
            throw new StopExecutionException('Certificates backup ID %s does not exist.', $id);
        }

        $this->getRepository()->restoreBackup($id);
    }

    public function sanitizeCertificate($fqdn)
    {
        $this->validate('fqdn', $fqdn, new Regex('!^[a-zA-Z0-9\.\-_]+$!'));

        return mb_strtolower($fqdn);
    }

    /**
     * @return CertificateRepository
     */
    private function getRepository()
    {
        return $this->container['certificate.repository'];
    }
}