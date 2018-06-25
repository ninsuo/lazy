<?php

namespace Lazy\Plugin\Domain;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class DomainServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['domain.configuration'] = function ($c) {
            return new DomainConfiguration($c);
        };

        $container['domain.handler'] = function ($c) {
            return new DomainHandler($c);
        };

        $container['domain.repository'] = function ($c) {
            return new DomainRepository($c);
        };
    }
}