<?php

namespace Lazy\Core\Exception;

class StopExecutionException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(call_user_func_array('sprintf', func_get_args()));
    }
}