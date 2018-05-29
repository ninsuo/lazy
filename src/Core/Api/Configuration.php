<?php

namespace Lazy\Core\Api;

use Lazy\Core\Configuration as Config;

interface Configuration
{
    public function build(Config $config);
}
