<?php

namespace Lazy\Plugin\Email;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class EmailServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['email.configuration'] = function ($c) {
            return new EmailConfiguration($c);
        };

        $container['email.handler'] = function ($c) {
            return new EmailHandler($c);
        };

        $container['email.repository'] = function ($c) {
            return new EmailRepository($c);
        };
    }
}