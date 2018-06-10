<?php

namespace Lazy\Plugin\Website;

use Lazy\Core\Base\BaseConfiguration;
use Lazy\Core\Configuration;
use Webmozart\Console\Api\Args\Format\Argument;
use Webmozart\Console\Api\Args\Format\Option;

class WebsiteConfiguration extends BaseConfiguration
{
    public function build(Configuration $config)
    {
        $config
            ->beginCommand('website')
                ->setDescription('Manage Apache websites')
                ->setHandler($this->container['website.handler'])
                ->beginSubCommand('list')
                    ->setHandlerMethod('handleList')
                    ->setDescription('List installed websites')
                    ->markDefault()
                ->end()
                ->beginSubCommand('add')
                    ->setHandlerMethod('handleAdd')
                    ->setDescription('Install a new website')
                    ->addArgument('fqdn', Argument::REQUIRED, 'The website\'s FQDN to create')
                ->end()
                ->beginSubCommand('edit')
                    ->setHandlerMethod('handleEdit')
                    ->setDescription('Edit website configuration')
                    ->addArgument('fqdn', Argument::REQUIRED, 'The website\'s FQDN to edit')
                ->end()
                ->beginSubCommand('remove')
                    ->setHandlerMethod('handleRemove')
                    ->setDescription('Remove a website')
                    ->addArgument('fqdn', Argument::REQUIRED, 'The website\'s FQDN to remove')
                ->end()
                ->beginSubCommand('backups')
                    ->setHandlerMethod('handleListBackups')
                    ->setDescription('List all available website backups')
                ->end()
                ->beginSubCommand('backup')
                    ->setHandlerMethod('handleBackupNow')
                    ->setDescription('Backup current websites configuration')
                    ->addArgument('notes', Argument::OPTIONAL, '(optional) backup details')
                ->end()
                ->beginSubCommand('restore')
                    ->setHandlerMethod('handleRestoreBackup')
                    ->setDescription('Restore all website configurations at a given backup')
                    ->addArgument('id', Argument::REQUIRED, 'backup ID to restore')
                ->end()
             ->end();
    }
}