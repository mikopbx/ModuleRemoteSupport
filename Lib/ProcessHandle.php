<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use RuntimeException;

class ProcessHandle
{
    /** @var resource|null */
    private $process;

    /** @var array<int, resource> */
    private array $pipes;

    /**
     * @param resource $process
     * @param array<int, resource> $pipes
     */
    public function __construct($process, array $pipes)
    {
        if (!is_resource($process)) {
            throw new RuntimeException('Invalid process resource');
        }

        $this->process = $process;
        $this->pipes = $pipes;
    }

    /**
     * @phpstan-impure
     */
    public function isRunning(): bool
    {
        if ($this->process === null) {
            return false;
        }

        $status = proc_get_status($this->process);

        return $status['running'];
    }

    public function readStdout(int $maxBytes = 8_192): string
    {
        return $this->readPipe(1, $maxBytes);
    }

    public function readStderr(int $maxBytes = 8_192): string
    {
        return $this->readPipe(2, $maxBytes);
    }

    public function terminate(int $graceMilliseconds = 1_000): void
    {
        $process = $this->process;
        if ($process === null) {
            return;
        }

        if (!$this->isRunning()) {
            $this->close();

            return;
        }

        proc_terminate($process);
        $deadline = microtime(true) + ($graceMilliseconds / 1_000);
        while ($this->isRunning() && microtime(true) < $deadline) {
            usleep(10_000);
        }

        if ($this->isRunning()) {
            proc_terminate($process, 9);
        }

        $this->close();
    }

    public function close(): int
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $this->pipes = [];

        if ($this->process === null) {
            return -1;
        }

        $process = $this->process;
        $this->process = null;

        return proc_close($process);
    }

    private function readPipe(int $index, int $maxBytes): string
    {
        if ($maxBytes < 1 || !isset($this->pipes[$index]) || !is_resource($this->pipes[$index])) {
            return '';
        }

        $contents = stream_get_contents($this->pipes[$index], $maxBytes);

        return is_string($contents) ? $contents : '';
    }
}
