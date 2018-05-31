<?php

namespace Lazy\Plugin\Domain;

use Lazy\Core\Base\BaseConfiguration;
use Lazy\Core\Configuration;
use Webmozart\Console\Api\Args\Format\Argument;
use Webmozart\Console\Api\Args\Format\Option;

class DomainConfiguration extends BaseConfiguration
{
    public function build(Configuration $config)
    {
        $config
            ->beginCommand('domain')
                ->setDescription('Manage zone files for domain names')
                ->setHandler($this->container['domain.handler'])
                ->beginSubCommand('list')
                    ->setHandlerMethod('handleList')
                    ->setDescription('List managed domain names')
                    ->markDefault()
                ->end()
                ->beginSubCommand('add')
                    ->setHandlerMethod('handleAdd')
                    ->setDescription('Enroll a new domain name')
                    ->addArgument('domain', Argument::REQUIRED, 'The domain name to create')
                ->end()
                ->beginSubCommand('edit')
                    ->setHandlerMethod('handleEdit')
                    ->setDescription('Edit zones file')
                    ->addArgument('domain', Argument::REQUIRED, 'The domain name to edit')
                ->end()
                ->beginSubCommand('remove')
                    ->setHandlerMethod('handleRemove')
                    ->setDescription('Remove a domain name')
                    ->addArgument('domain', Argument::REQUIRED, 'The domain name to remove')
                ->end()
                ->beginSubCommand('primary')
                    ->setHandlerMethod('handlePrimary')
                    ->setDescription('Consult or change your server\'s primary domain name')
                    ->addArgument('domain', Argument::OPTIONAL, '(optional) the new primary domain name')
                ->end()
                ->beginSubCommand('backups')
                    ->setHandlerMethod('handleListBackups')
                    ->setDescription('List all available domain backups')
                ->end()
                ->beginSubCommand('backup')
                    ->setHandlerMethod('handleBackupNow')
                    ->setDescription('Run a new domain configuration backup')
                    ->addArgument('notes', Argument::OPTIONAL, '(optional) backup details')
                ->end()
                ->beginSubCommand('restore')
                    ->setHandlerMethod('handleRestoreBackup')
                    ->setDescription('Restore all domains at a given backup')
                    ->addArgument('id', Argument::REQUIRED, 'backup ID to restore')
                ->end()
             ->end();
    }
}