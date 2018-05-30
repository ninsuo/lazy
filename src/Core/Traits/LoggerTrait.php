<?php

namespace Lazy\Core\Traits;

use Webmozart\Console\Api\IO\IO;

trait LoggerTrait
{
    /**
     * @param $message
     */
    public function raw(IO $io, $message)
    {
        $io->write(
            call_user_func_array('sprintf', array_slice(func_get_args(), 1))
        );
    }

    /**
     * @param $data
     */
    public function json(IO $io, $data)
    {
        $this->raw($io, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * @param $message
     */
    public function log(IO $io, $message)
    {
        $io->writeLine(
            sprintf('%s: %s',
                date('d/m/Y H:i:s'),
                call_user_func_array('sprintf', array_slice(func_get_args(), 1))
            )
        );
    }

    /**
     * @param $message
     */
    public function info(IO $io, $message)
    {
        $io->writeLine(
            sprintf('<blue>%s</blue>: %s',
                date('d/m/Y H:i:s'),
                call_user_func_array('sprintf', array_slice(func_get_args(), 1))
            )
        );
    }

    /**
     * @param $message
     */
    public function error(IO $io, $message)
    {
        $io->writeLine(
            sprintf('<red>%s</red>: %s',
                date('d/m/Y H:i:s'),
                call_user_func_array('sprintf', array_slice(func_get_args(), 1))
            )
        );
    }

    /**
     * @param $message
     */
    public function success(IO $io, $message)
    {
        $io->writeLine(
            sprintf('<green>%s</green>: %s',
                date('d/m/Y H:i:s'),
                call_user_func_array('sprintf', array_slice(func_get_args(), 1))
            )
        );
    }
}