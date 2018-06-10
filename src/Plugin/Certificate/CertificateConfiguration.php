<?php

namespace Lazy\Plugin\Certificate;

use Lazy\Core\Base\BaseConfiguration;
use Lazy\Core\Configuration;
use Webmozart\Console\Api\Args\Format\Argument;

class CertificateConfiguration extends BaseConfiguration
{
    public function build(Configuration $config)
    {
        $config
            ->beginCommand('certificate')
                ->setDescription('Manage Let\'s Encrypt certificates')
                ->setHandler($this->container['certificate.handler'])
                ->beginSubCommand('list')
                    ->setHandlerMethod('handleList')
                    ->setDescription('List installed certificates')
                    ->markDefault()
                ->end()
                ->beginSubCommand('add')
                    ->setHandlerMethod('handleAdd')
                    ->setDescription('Install a new certificate')
                    ->addArgument('fqdn', Argument::REQUIRED, 'The certificate\'s FQDN to create')
                ->end()
                ->beginSubCommand('remove')
                    ->setHandlerMethod('handleRemove')
                    ->setDescription('Remove a certificate')
                    ->addArgument('fqdn', Argument::REQUIRED, 'The certificate\'s FQDN to remove')
                ->end()
                ->beginSubCommand('backups')
                    ->setHandlerMethod('handleListBackups')
                    ->setDescription('List all available certificate backups')
                ->end()
                ->beginSubCommand('backup')
                    ->setHandlerMethod('handleBackupNow')
                    ->setDescription('Backup current certificates configuration')
                    ->addArgument('notes', Argument::OPTIONAL, '(optional) backup details')
                ->end()
                ->beginSubCommand('restore')
                    ->setHandlerMethod('handleRestoreBackup')
                    ->setDescription('Restore all certificate configurations at a given backup')
                    ->addArgument('id', Argument::REQUIRED, 'backup ID to restore')
                ->end()
             ->end();
    }
}