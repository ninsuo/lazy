<?php

namespace Lazy\Plugin\Certificate;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class CertificateServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['certificate.configuration'] = function ($c) {
            return new CertificateConfiguration($c);
        };

        $container['certificate.handler'] = function ($c) {
            return new CertificateHandler($c);
        };

        $container['certificate.repository'] = function ($c) {
            return new CertificateRepository($c);
        };
    }
}