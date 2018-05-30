<?php

namespace Lazy\Core\Base;

use Lazy\Core\Traits\ConfigTrait;
use Lazy\Core\Traits\LoggerTrait;
use Pimple\Container;

abstract class BaseService
{
    use ConfigTrait;
    use LoggerTrait;

    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }



}