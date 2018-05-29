<?php

namespace Lazy\Plugin\Domain;

use Lazy\Core\Base\BaseConfiguration;
use Lazy\Core\Configuration;

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
                ->beginSubCommand('add')
                    ->setHandlerMethod('handleAdd')
                    ->setDescription('Add a new domain name')
                ->end()
                ->beginSubCommand('remove')
                    ->setHandlerMethod('handleRemove')
                    ->setDescription('Remove a domain name')
                ->end()
            ->end();
    }
}