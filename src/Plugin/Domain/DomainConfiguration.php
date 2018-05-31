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
                    ->setDescription('List installed domain names')
                    ->markDefault()
                ->end()
                ->beginSubCommand('enroll')
                    ->setHandlerMethod('handleEnroll')
                    ->setDescription('Enroll a new domain name')
                    ->addArgument('name', Argument::REQUIRED, 'The domain name to create')
                    ->addArgument('email', Argument::OPTIONAL, 'A valid email address')
                ->end()
                ->beginSubCommand('edit')
                    ->setHandlerMethod('handleEdit')
                    ->setDescription('Edit zones file')
                    ->addArgument('name', Argument::REQUIRED, 'The domain name to edit')
                ->end()
                ->beginSubCommand('remove')
                    ->setHandlerMethod('handleRemove')
                    ->setDescription('Remove a domain name')
                    ->addArgument('name', Argument::REQUIRED, 'The domain name to remove')
                ->end()
                ->beginSubCommand('primary')
                    ->setHandlerMethod('handlePrimary')
                    ->setDescription('Consult or change your server\'s primary domain name')
                    ->addArgument('name', Argument::OPTIONAL, '(optional) the new primary domain name')
                ->end()
                ->beginSubCommand('backup')
                    ->setHandlerMethod('handleBackupNow')
                    ->setDescription('List all available domain backups or run a new one')
                    ->addArgument('name', Argument::OPTIONAL, '(optional) the new backup name')
                ->end()
                ->beginSubCommand('restore')
                    ->setHandlerMethod('handleBackupRestore')
                    ->setDescription('Restore all domains at a given backup name')
                    ->addArgument('name', Argument::OPTIONAL, 'The backup name')
                ->end()
             ->end();
    }
}