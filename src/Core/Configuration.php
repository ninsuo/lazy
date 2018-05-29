<?php

namespace Lazy\Core;

use Webmozart\Console\Config\DefaultApplicationConfig;

class Configuration extends DefaultApplicationConfig
{
    protected function configure()
    {
        parent::configure();

        return $this
            ->setName('lazy')
            ->setVersion('0.0.1');
    }
}
