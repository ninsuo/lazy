<?php

namespace Lazy\Core\Model;

class Execution
{
    /**
     * @var string
     */
    private $query;

    /**
     * @var string
     */
    private $stdout;

    /**
     * @var string
     */
    private $stderr;

    /**
     * @var integer
     */
    private $code;

    public function __construct($query, $stdout, $stderr, $code)
    {
        $this->query  = $query;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->code   = $code;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @return string
     */
    public function getStdout()
    {
        return $this->stdout;
    }

    /**
     * @return string
     */
    public function getStderr()
    {
        return $this->stderr;
    }

    /**
     * @return integer
     */
    public function getCode()
    {
        return $this->code;
    }

    public function __toString(): string
    {
        $execution = sprintf('Executed: <yellow>%s</yellow>', $this->query);

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

        return $execution;
    }
}