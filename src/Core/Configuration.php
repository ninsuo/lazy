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
            ->setName('lazy')
            ->setDisplayName('🍔 lazy 🍔')
            ->setVersion('0.0.1')
            ->setCatchExceptions(true);

        // Configure color tags (to use <red>bob</red> for example)
        $colors = ['black', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan', 'white'];
        foreach ($colors as $color) {
            $this->addStyle(Style::tag($color)->fg($color));
        }

        // Used for prompt()s.
        $this->addStyle(Style::tag('hl')->bold());

        return $this;
    }
}
