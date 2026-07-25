<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use MikoPBX\Common\Models\LanInterfaces;
use MikoPBX\Common\Models\PbxSettings;
use RuntimeException;
use Throwable;

/**
 * Reverse-forward destination for the engineer web access.
 *
 * The target is always the real station IP, never 127.0.0.1: forwarding to the
 * station loopback would engage the MikoPBX localhost trust bypass and give the
 * engineer an unauthenticated, unattributed admin session. The web channel must
 * instead terminate on a non-loopback address so the ephemeral module
 * credential is required.
 */
final readonly class WebForwardTarget
{
    public function __construct(
        public string $stationIp,
        public int $httpsPort,
    ) {
        $validIp = filter_var(
            $stationIp,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_RES_RANGE,
        );
        if ($validIp === false) {
            throw new RuntimeException('Invalid station web address');
        }
        if ($httpsPort < 1 || $httpsPort > 65_535) {
            throw new RuntimeException('Invalid station web port');
        }
    }

    /**
     * Resolves the station admin address from the primary (internet) interface
     * and the configured HTTPS port. Returns null when no usable non-loopback
     * address exists, so the caller degrades to an SSH-only session.
     */
    public static function fromPbxConfiguration(): ?self
    {
        try {
            $interface = LanInterfaces::findFirst("internet = '1' AND disabled='0'");
            if (!$interface instanceof LanInterfaces || empty($interface->ipaddr)) {
                return null;
            }

            $httpsPort = (int)PbxSettings::getValueByKey(PbxSettings::WEB_HTTPS_PORT);
            if ($httpsPort < 1 || $httpsPort > 65_535) {
                $httpsPort = 443;
            }

            return new self((string)$interface->ipaddr, $httpsPort);
        } catch (Throwable) {
            return null;
        }
    }
}
