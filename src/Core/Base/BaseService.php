<?php

namespace Lazy\Core\Base;

use Lazy\Core\Model\Execution;
use Lazy\Core\Traits\ConfigTrait;
use Lazy\Core\Traits\LoggerTrait;
use Pimple\Container;
use Symfony\Component\Process\Process;

abstract class BaseService
{
    use ConfigTrait;
    use LoggerTrait;

    /**
     * @var Container
     */
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function exec($query, array $parameters = [], $tty = false)
    {
        foreach ($parameters as $key => $parameter) {
            $query = str_replace(
                sprintf(':%s', $key),
                escapeshellarg($parameter),
                $query
            );
        }

        $process = new Process($query);
        $process->setTty($tty);
        $process->run();

        $executed = new Execution($query, $process->getOutput(), $process->getErrorOutput(), $process->getExitCode());

        $this->container['io']->writeLine($executed);

        return $executed;
    }

    public function render($template, array $parameters = [])
    {
        $twig = new \Twig_Environment(
            new \Twig_Loader_Filesystem(dirname($template))
        );

        return $twig->render(basename($template), $parameters);
    }
}
