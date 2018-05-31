<?php

namespace Lazy\Core\Exception;

class StopExecutionException extends \RuntimeException
{
    public function __construct()
    {
        if (count(func_get_args()) == 0) {
            parent::__construct('An error occurred, see the stacktrace (-v) for details.');
        } else {
            parent::__construct(call_user_func_array('sprintf', func_get_args()));
        }
    }
}