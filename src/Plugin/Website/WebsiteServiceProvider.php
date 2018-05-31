<?php

namespace Lazy\Plugin\Website;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class WebsiteServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['website.configuration'] = function ($c) {
            return new WebsiteConfiguration($c);
        };

        $container['website.handler'] = function ($c) {
            return new WebsiteHandler($c);
        };

        $container['website.repository'] = function ($c) {
            return new WebsiteRepository($c);
        };
    }
}