<?php

namespace Lazy\Core;

use Webmozart\Console\Api\Formatter\Style;
use Webmozart\Console\Config\DefaultApplicationConfig;

class Configuration extends DefaultApplicationConfig
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('🍔 lazy 🍔')
            ->setVersion('0.0.1');

        // Configure color tags (to use <red>bob</red> for example)
        $colors = ['black', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan', 'white'];
        foreach ($colors as $color) {
            $this->addStyle(Style::tag($color)->fg($color));
        }

        return $this;
    }
}
