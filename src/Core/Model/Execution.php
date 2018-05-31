<?php

namespace Lazy\Core\Model;

class Execution
{
    /**
     * @var string
     */
    public $query;

    /**
     * @var string
     */
    public $stdout;

    /**
     * @var string
     */
    public $stderr;

    /**
     * @var integer
     */
    public $code;

    public function __construct($query, $stdout, $stderr, $code)
    {
        $this->query  = $query;
        $this->stdout = trim($stdout);
        $this->stderr = trim($stderr);
        $this->code   = $code;
    }

    public function __toString(): string
    {
        $execution = str_repeat('-', 80);
        $execution .= PHP_EOL;

        $execution .= sprintf('Executed: <yellow>%s</yellow>', $this->query);

        if ($this->code) {
            $execution .= sprintf(' (status code <red>%s</red>)', $this->code);
        } else {
            $execution .= sprintf(' (status code <green>%s</green>)', $this->code);
        }

        $execution .= PHP_EOL;

        if ($this->stdout) {
            $execution .= '<blue>';
            $execution .= $this->stdout;
            $execution .= '</blue>';
        }

        if ($this->stdout && $this->stderr) {
            $execution .= PHP_EOL . PHP_EOL;
        }

        if ($this->stderr) {
            $execution .= '<red>';
            $execution .= $this->stderr;
            $execution .= '</red>';
        }

        $execution .= PHP_EOL;
        $execution .= str_repeat('-', 80);
        $execution .= PHP_EOL;

        return $execution;
    }
}