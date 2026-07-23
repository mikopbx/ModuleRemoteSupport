<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use MikoPBX\Core\System\Directories;
use MikoPBX\Core\System\Util;
use RuntimeException;

final class RuntimeDirectory implements RemoteSupportRuntimeInterface
{
    private const string DIRECTORY_NAME = 'module-remote-support';
    private const string SESSION_DIRECTORY_PATTERN = '/\A[a-f0-9]{32}\z/D';

    private ?string $sessionDirectory = null;
    private ?string $sshKeygenBinary = null;

    public function preflight(): void
    {
        if (Util::which('ssh') === '') {
            throw new RuntimeException('OpenSSH client is unavailable');
        }

        $this->sshKeygenBinary = Util::which('ssh-keygen');
        if ($this->sshKeygenBinary === '') {
            throw new RuntimeException('OpenSSH key generator is unavailable');
        }

        $baseDirectory = $this->baseDirectory();
        $this->ensureDirectory($baseDirectory);
        if (!is_writable($baseDirectory)) {
            throw new RuntimeException('Private runtime is not writable');
        }

        $probe = $baseDirectory . '/probe-' . bin2hex(random_bytes(8));
        try {
            $this->runKeygen($probe);
        } finally {
            $this->removeFile($probe);
            $this->removeFile($probe . '.pub');
        }
    }

    public function create(): void
    {
        $baseDirectory = $this->baseDirectory();
        $this->ensureDirectory($baseDirectory);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $path = $baseDirectory . '/' . bin2hex(random_bytes(16));
            if (@mkdir($path, 0700)) {
                if (!chmod($path, 0700)) {
                    @rmdir($path);
                    throw new RuntimeException('Unable to secure runtime directory');
                }
                $this->sessionDirectory = $path;

                return;
            }
        }

        throw new RuntimeException('Unable to create private runtime directory');
    }

    public function generateKeyPair(): void
    {
        $privateKey = $this->privateKeyPath();
        $this->runKeygen($privateKey);
        if (!is_file($privateKey) || !is_file($privateKey . '.pub')) {
            throw new RuntimeException('OpenSSH did not create an Ed25519 key pair');
        }
        if (!chmod($privateKey, 0600) || !chmod($privateKey . '.pub', 0600)) {
            throw new RuntimeException('Unable to secure generated SSH keys');
        }
    }

    public function writeKnownHosts(): void
    {
        $knownHosts = $this->knownHostsPath();
        $bytes = file_put_contents(
            $knownHosts,
            RemoteSupportConfig::SUPPORT_KNOWN_HOST . "\n",
            LOCK_EX,
        );
        if ($bytes === false || !chmod($knownHosts, 0600)) {
            throw new RuntimeException('Unable to write pinned SSH host key');
        }
    }

    public function privateKeyPath(): string
    {
        return $this->requireSessionDirectory() . '/id_ed25519';
    }

    public function knownHostsPath(): string
    {
        return $this->requireSessionDirectory() . '/known_hosts';
    }

    public function cleanup(): void
    {
        if ($this->sessionDirectory === null) {
            return;
        }

        $this->removeSessionDirectory($this->sessionDirectory);
        $this->sessionDirectory = null;
    }

    public function cleanupStale(): void
    {
        $baseDirectory = $this->baseDirectory();
        if (!is_dir($baseDirectory)) {
            return;
        }

        $entries = scandir($baseDirectory);
        if (!is_array($entries)) {
            throw new RuntimeException('Unable to inspect remote-support runtime');
        }

        foreach ($entries as $entry) {
            if (preg_match(self::SESSION_DIRECTORY_PATTERN, $entry) !== 1) {
                continue;
            }
            $this->removeSessionDirectory($baseDirectory . '/' . $entry);
        }
    }

    private function baseDirectory(): string
    {
        return rtrim(Directories::getDir(Directories::CORE_TEMP_DIR), '/')
            . '/'
            . self::DIRECTORY_NAME;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            if (!chmod($path, 0700)) {
                throw new RuntimeException('Unable to secure runtime base directory');
            }

            return;
        }
        if (!mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create runtime base directory');
        }
        if (!chmod($path, 0700)) {
            throw new RuntimeException('Unable to secure runtime base directory');
        }
    }

    private function runKeygen(string $privateKey): void
    {
        $sshKeygen = $this->sshKeygenBinary ?? Util::which('ssh-keygen');
        if ($sshKeygen === '') {
            throw new RuntimeException('OpenSSH key generator is unavailable');
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(
            [$sshKeygen, '-q', '-t', 'ed25519', '-N', '', '-f', $privateKey],
            $descriptors,
            $pipes,
            null,
            null,
            ['bypass_shell' => true],
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start OpenSSH key generator');
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1], 8_192);
        stream_get_contents($pipes[2], 8_192);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Ed25519 key generation failed');
        }
    }

    private function requireSessionDirectory(): string
    {
        if ($this->sessionDirectory === null) {
            throw new RuntimeException('Remote-support runtime is not initialized');
        }

        return $this->sessionDirectory;
    }

    private function removeSessionDirectory(string $path): void
    {
        $baseDirectory = $this->baseDirectory();
        $name = basename($path);
        if (
            dirname($path) !== $baseDirectory
            || preg_match(self::SESSION_DIRECTORY_PATTERN, $name) !== 1
        ) {
            throw new RuntimeException('Refusing to remove an unmanaged runtime path');
        }
        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        if (!is_array($entries)) {
            throw new RuntimeException('Unable to inspect session runtime');
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $file = $path . '/' . $entry;
            if (is_dir($file)) {
                throw new RuntimeException('Unexpected directory in session runtime');
            }
            $this->removeFile($file);
        }
        if (!rmdir($path)) {
            throw new RuntimeException('Unable to remove session runtime directory');
        }
    }

    private function removeFile(string $path): void
    {
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Unable to remove runtime file');
        }
    }
}
