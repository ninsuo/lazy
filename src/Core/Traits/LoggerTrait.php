<?php

namespace Lazy\Core\Traits;

use Webmozart\Console\Api\IO\IO;

trait LoggerTrait
{
    /**
     * @param $message
     */
    public function raw($message)
    {
        $this->container['io']->write(
            call_user_func_array('sprintf', func_get_args())
        );
    }

    /**
     * @param $data
     */
    public function json($data)
    {
        $this->raw(json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * @param $message
     */
    public function log($message)
    {
        $this->container['io']->writeLine(
            sprintf('%s: %s',
                date('d/m/Y H:i:s'),
                call_user_func_array('sprintf', func_get_args())
            )
        );
    }

    /**
     * @param $message
     */
    public function info($message)
    {
        $this->container['io']->writeLine(
            sprintf('<blue>%s</blue>: %s',
                date('d/m/Y H:i:s'),
                call_user_func_array('sprintf', func_get_args())
            )
        );
    }

    /**
     * @param $message
     */
    public function error($message)
    {
        $this->container['io']->writeLine(
            sprintf('<red>%s</red>: %s',
                date('d/m/Y H:i:s'),
                call_user_func_array('sprintf', func_get_args())
            )
        );
    }

    /**
     * @param $message
     */
    public function success($message)
    {
        $this->container['io']->writeLine(
            sprintf('<green>%s</green>: %s',
                date('d/m/Y H:i:s'),
                call_user_func_array('sprintf', func_get_args())
            )
        );
    }
}