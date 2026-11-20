<?php

namespace SuperAdmin\Admin\Reporter\Tracer;

class Parser implements \Iterator
{
    protected $trace = '';

    /**
     * @var array<int, Frame>
     */
    protected $frames = [];

    protected $position = 0;

    public function __construct($trace)
    {
        $this->trace = $trace;
    }

    /**
     * @return array<int, Frame>
     */
    public function parse()
    {
        $frames = preg_split('/\r?\n/', (string) $this->trace);

        $this->frames = array_map(function ($frame) {
            return new Frame($frame);
        }, $frames);

        return $this->frames;
    }

    public function current(): mixed
    {
        return $this->frames[$this->position] ?? null;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function valid(): bool
    {
        return isset($this->frames[$this->position]);
    }

    public function rewind(): void
    {
        $this->position = 0;
    }
}
