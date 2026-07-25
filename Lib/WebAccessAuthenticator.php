<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use Closure;
use MikoPBX\AdminCabinet\Controllers\SessionController;
use MikoPBX\Common\Models\PbxSettings;
use MikoPBX\Common\Providers\AclProvider;
use Phalcon\Di\Di;
use Phalcon\Encryption\Security;
use Throwable;

/**
 * Validates the ephemeral engineer web credential of the active support
 * session and grants the full admin role for exactly its lifetime. Any other
 * state, credential mismatch, or infrastructure failure fails closed with an
 * empty result, which the MikoPBX authentication chain treats as "not ours".
 */
final class WebAccessAuthenticator
{
    private const int MAX_INPUT_LENGTH = 128;
    private const string ENGINEER_HOME_PAGE = '/admin-cabinet/extensions/index';

    /** @var Closure(string, string): bool */
    private readonly Closure $verify;

    /** @var Closure(): string */
    private readonly Closure $adminLogin;

    /**
     * @param null|Closure(string, string): bool $verify
     * @param null|Closure(): string $adminLogin
     */
    public function __construct(
        private readonly SessionRepository $repository = new SessionRepository(),
        ?Closure $verify = null,
        ?Closure $adminLogin = null,
    ) {
        $this->verify = $verify ?? static function (string $password, string $hash): bool {
            $container = Di::getDefault();
            if ($container === null) {
                return false;
            }

            $security = $container->get('security');
            if (!$security instanceof Security) {
                return false;
            }

            return $security->checkHash($password, $hash);
        };
        $this->adminLogin = $adminLogin ?? static fn(): string => PbxSettings::getValueByKey(
            PbxSettings::WEB_ADMIN_LOGIN,
        );
    }

    /**
     * @return array<string, string>
     */
    public function authenticate(string $login, string $password): array
    {
        try {
            if (
                $login === ''
                || $password === ''
                || strlen($login) > self::MAX_INPUT_LENGTH
                || strlen($password) > self::MAX_INPUT_LENGTH
            ) {
                return [];
            }

            $session = $this->repository->get();
            if ($session->status !== SessionStatus::ACTIVE->value) {
                return [];
            }

            $webLogin = (string)$session->web_login;
            $passwordHash = (string)$session->web_password_hash;
            if ($webLogin === '' || $passwordHash === '') {
                return [];
            }
            if (!hash_equals($webLogin, $login)) {
                return [];
            }
            if (strtolower($login) === strtolower(trim(($this->adminLogin)()))) {
                return [];
            }
            if (!($this->verify)($password, $passwordHash)) {
                return [];
            }

            return [
                SessionController::ROLE => AclProvider::ROLE_ADMINS,
                SessionController::HOME_PAGE => self::ENGINEER_HOME_PAGE,
                SessionController::USER_NAME => $webLogin,
            ];
        } catch (Throwable) {
            return [];
        }
    }
}
