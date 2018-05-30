<?php

namespace Lazy\Core\Traits;

trait ConfigTrait
{
    public function getParameter($key)
    {
        $config = $this->container['config'];

        if (!array_key_exists($key, $config)) {
            throw new \RuntimeException(sprintf('Parameter "%s" not found in configuration.', $key));
        }

        return $config[$key];
    }
}