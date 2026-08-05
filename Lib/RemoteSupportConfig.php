<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

final class RemoteSupportConfig
{
    public const string SUPPORT_HOST = 'support-tunnel.miko.ru';
    public const int SUPPORT_PORT = 34022;
    public const int LOBBY_PROTOCOL_VERSION = 2;
    public const int WEB_TUNNEL_PORT_MIN = 23_000;
    public const int WEB_TUNNEL_PORT_MAX = 23_999;
    public const string WEB_LOGIN_PREFIX = 'miko-support-';
    public const int SESSION_TTL_SECONDS = 28_800;
    public const int LOBBY_MAX_BYTES = 8_192;
    public const int SSH_CONNECT_TIMEOUT = 10;
    public const int SSH_KEEPALIVE_INTERVAL = 15;
    public const int SSH_KEEPALIVE_COUNT = 2;
    public const string SUPPORT_KNOWN_HOST = '[support-tunnel.miko.ru]:34022 '
        . 'ssh-ed25519 '
        . 'AAAAC3NzaC1lZDI1NTE5AAAAIHpxmLgRDTdIGvp+9RhTLg7Wn1F0HMgCYI2rrr46qVzB';
    public const string WARPGATE_PUBLIC_KEY = 'ssh-ed25519 '
        . 'AAAAC3NzaC1lZDI1NTE5AAAAIDTZYDUTv9WCq4EDH40bpkbpxc7jun4eSN+GH/o8z45H';

    private function __construct()
    {
    }
}
