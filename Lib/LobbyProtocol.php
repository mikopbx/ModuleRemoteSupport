<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use Closure;
use DateTimeImmutable;
use Throwable;

final class LobbyProtocol
{
    /** @var Closure(): int */
    private readonly Closure $clock;

    /**
     * @param null|Closure(): int $clock
     */
    public function __construct(?Closure $clock = null)
    {
        $this->clock = $clock ?? time(...);
    }

    public function parse(string $output): LobbyAllocation
    {
        if (
            strlen($output) > RemoteSupportConfig::LOBBY_MAX_BYTES
            || preg_match('/[\x00-\x09\x0B-\x1F\x7F]/', $output) === 1
        ) {
            throw new LobbyProtocolException('Invalid lobby response');
        }

        $pattern = '/\A'
            . 'MIKO-LOBBY: v1\n'
            . 'CODE: ([A-Z0-9]{3}-[A-Z0-9]{3})\n'
            . 'SLOT: ([0-9]{1,3})\n'
            . 'TUNNEL_PORT: ([0-9]{1,5})\n'
            . 'TUNNEL_USER: (lobbytun)\n'
            . 'EXPIRES: ([0-9]{4}-[0-9]{2}-[0-9]{2}T'
            . '[0-9]{2}:[0-9]{2}:[0-9]{2}(?:Z|[+-][0-9]{2}:[0-9]{2}))'
            . '\n?\z/D';
        if (preg_match($pattern, $output, $matches) !== 1) {
            throw new LobbyProtocolException('Invalid lobby response');
        }

        $slot = (int)$matches[2];
        $tunnelPort = (int)$matches[3];
        if ($slot < 0 || $slot > 999 || $tunnelPort < 22_000 || $tunnelPort > 22_999) {
            throw new LobbyProtocolException('Lobby allocation is outside allowed ranges');
        }

        $expiresAt = $this->parseExpiry($matches[5]);
        if ($expiresAt->getTimestamp() <= ($this->clock)()) {
            throw new LobbyProtocolException('Lobby allocation has expired');
        }

        return new LobbyAllocation(
            code: $matches[1],
            slot: $slot,
            tunnelPort: $tunnelPort,
            tunnelUser: $matches[4],
            expiresAt: $expiresAt,
        );
    }

    private function parseExpiry(string $value): DateTimeImmutable
    {
        try {
            $expiresAt = new DateTimeImmutable($value);
        } catch (Throwable $exception) {
            throw new LobbyProtocolException('Invalid lobby expiry', 0, $exception);
        }

        $normalizedInput = str_ends_with($value, 'Z')
            ? substr($value, 0, -1) . '+00:00'
            : $value;
        if ($expiresAt->format('Y-m-d\TH:i:sP') !== $normalizedInput) {
            throw new LobbyProtocolException('Invalid lobby expiry');
        }

        return $expiresAt;
    }
}
