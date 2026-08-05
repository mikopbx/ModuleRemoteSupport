<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use Closure;
use RuntimeException;

final class SshProcess implements SshProcessInterface
{
    /** @var Closure(list<string>): array{exitCode: int, stdout: string, stderr: string} */
    private readonly Closure $run;

    /** @var Closure(list<string>): ProcessHandle */
    private readonly Closure $spawn;

    /**
     * @param null|Closure(list<string>): array{exitCode: int, stdout: string, stderr: string} $run
     * @param null|Closure(list<string>): ProcessHandle $spawn
     */
    public function __construct(?Closure $run = null, ?Closure $spawn = null)
    {
        $this->run = $run ?? $this->runCommand(...);
        $this->spawn = $spawn ?? $this->spawnCommand(...);
    }

    public function allocate(
        string $privateKey,
        string $knownHosts,
        StationSshUser $stationUser,
    ): string {
        $arguments = [
            ...$this->commonArguments($privateKey, $knownHosts),
            '-T',
            'lobbyalloc@' . RemoteSupportConfig::SUPPORT_HOST,
            // Remote command, not a shell command: the forced command on the
            // server reads it from SSH_ORIGINAL_COMMAND to select the protocol
            // version. Without it the server answers v1 and there is no web port.
            $stationUser->allocationRequest(),
        ];
        $result = ($this->run)($arguments);
        if (
            $result['exitCode'] !== 0
            || strlen($result['stdout']) > RemoteSupportConfig::LOBBY_MAX_BYTES
        ) {
            throw new RuntimeException('Lobby allocation failed');
        }

        return $result['stdout'];
    }

    public function startTunnel(
        LobbyAllocation $allocation,
        string $privateKey,
        string $knownHosts,
        ?WebForwardTarget $webTarget = null,
    ): ProcessHandle {
        if (
            $allocation->tunnelUser !== 'lobbytun'
            || $allocation->tunnelPort < 22_000
            || $allocation->tunnelPort > 22_999
        ) {
            throw new RuntimeException('Invalid tunnel allocation');
        }

        $webForwardArguments = [];
        if ($allocation->webTunnelPort !== null && $webTarget !== null) {
            if (
                $allocation->webTunnelPort < RemoteSupportConfig::WEB_TUNNEL_PORT_MIN
                || $allocation->webTunnelPort > RemoteSupportConfig::WEB_TUNNEL_PORT_MAX
            ) {
                throw new RuntimeException('Invalid tunnel allocation');
            }

            $webForwardArguments = [
                '-R',
                sprintf(
                    '127.0.0.1:%d:%s:%d',
                    $allocation->webTunnelPort,
                    $webTarget->stationIp,
                    $webTarget->httpsPort,
                ),
            ];
        }

        $arguments = [
            ...$this->commonArguments($privateKey, $knownHosts),
            '-o',
            'ExitOnForwardFailure=yes',
            '-N',
            '-T',
            '-R',
            sprintf('127.0.0.1:%d:127.0.0.1:22', $allocation->tunnelPort),
            ...$webForwardArguments,
            $allocation->tunnelUser . '@' . RemoteSupportConfig::SUPPORT_HOST,
        ];

        return ($this->spawn)($arguments);
    }

    /**
     * @return list<string>
     */
    private function commonArguments(string $privateKey, string $knownHosts): array
    {
        $this->assertSafePath($privateKey);
        $this->assertSafePath($knownHosts);

        return [
            'ssh',
            '-p',
            (string)RemoteSupportConfig::SUPPORT_PORT,
            '-i',
            $privateKey,
            '-o',
            'IdentitiesOnly=yes',
            '-o',
            'StrictHostKeyChecking=yes',
            '-o',
            'UserKnownHostsFile=' . $knownHosts,
            '-o',
            'BatchMode=yes',
            '-o',
            'ConnectTimeout=' . RemoteSupportConfig::SSH_CONNECT_TIMEOUT,
            '-o',
            'ServerAliveInterval=' . RemoteSupportConfig::SSH_KEEPALIVE_INTERVAL,
            '-o',
            'ServerAliveCountMax=' . RemoteSupportConfig::SSH_KEEPALIVE_COUNT,
        ];
    }

    private function assertSafePath(string $path): void
    {
        if (
            $path === ''
            || !str_starts_with($path, '/')
            || strlen($path) > 4_096
            || preg_match('/[\x00\r\n]/', $path) === 1
        ) {
            throw new RuntimeException('Invalid SSH runtime path');
        }
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function runCommand(array $arguments): array
    {
        $handle = $this->spawnCommand($arguments);
        $deadline = microtime(true) + RemoteSupportConfig::SSH_CONNECT_TIMEOUT + 5;
        $stdout = '';
        $stderr = '';

        while ($handle->isRunning()) {
            $stdout .= $handle->readStdout(RemoteSupportConfig::LOBBY_MAX_BYTES + 1);
            $stderr .= $handle->readStderr(RemoteSupportConfig::LOBBY_MAX_BYTES + 1);
            if (
                strlen($stdout) > RemoteSupportConfig::LOBBY_MAX_BYTES
                || strlen($stderr) > RemoteSupportConfig::LOBBY_MAX_BYTES
                || microtime(true) >= $deadline
            ) {
                $handle->terminate();
                throw new RuntimeException('SSH command exceeded its limits');
            }

            usleep(10_000);
        }

        $stdout .= $handle->readStdout(RemoteSupportConfig::LOBBY_MAX_BYTES + 1);
        $stderr .= $handle->readStderr(RemoteSupportConfig::LOBBY_MAX_BYTES + 1);
        $exitCode = $handle->close();

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * @param list<string> $arguments
     */
    private function spawnCommand(array $arguments): ProcessHandle
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(
            $arguments,
            $descriptors,
            $pipes,
            null,
            null,
            ['bypass_shell' => true],
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start SSH process');
        }

        fclose($pipes[0]);
        unset($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return new ProcessHandle($process, $pipes);
    }
}
