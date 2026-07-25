<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use Closure;
use MikoPBX\Common\Models\PbxSettings;
use Phalcon\Di\Di;
use Phalcon\Encryption\Security;
use RuntimeException;

final class WebCredentialGenerator
{
    /**
     * Dictation-safe alphabet: no 0/1/i/l/o so the administrator can read the
     * password to the engineer over the phone without ambiguity.
     */
    private const string PASSWORD_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    private const int PASSWORD_GROUPS = 4;
    private const int PASSWORD_GROUP_LENGTH = 5;
    private const int LOGIN_RANDOM_BYTES = 8;
    private const int LOGIN_ATTEMPTS = 5;

    /** @var Closure(string): string */
    private readonly Closure $hash;

    /** @var Closure(): string */
    private readonly Closure $adminLogin;

    /** @var Closure(): string */
    private readonly Closure $loginFactory;

    /**
     * @param null|Closure(string): string $hash
     * @param null|Closure(): string $adminLogin
     * @param null|Closure(): string $loginFactory
     */
    public function __construct(
        ?Closure $hash = null,
        ?Closure $adminLogin = null,
        ?Closure $loginFactory = null,
    ) {
        $this->hash = $hash ?? static function (string $password): string {
            $container = Di::getDefault();
            if ($container === null) {
                throw new RuntimeException('MikoPBX dependency injection is unavailable');
            }

            $security = $container->get('security');
            if (!$security instanceof Security) {
                throw new RuntimeException('MikoPBX security service is unavailable');
            }

            return $security->hash($password);
        };
        $this->adminLogin = $adminLogin ?? static fn(): string => PbxSettings::getValueByKey(
            PbxSettings::WEB_ADMIN_LOGIN,
        );
        $this->loginFactory = $loginFactory ?? static fn(): string => RemoteSupportConfig::WEB_LOGIN_PREFIX
            . bin2hex(random_bytes(self::LOGIN_RANDOM_BYTES));
    }

    public function generate(): GeneratedWebCredential
    {
        $login = $this->issueLogin();
        $password = $this->issuePassword();
        $passwordHash = ($this->hash)($password);
        if (!str_starts_with($passwordHash, '$')) {
            throw new RuntimeException('Web password hash is not a recognized hash');
        }

        return new GeneratedWebCredential(
            login: $login,
            password: $password,
            passwordHash: $passwordHash,
        );
    }

    private function issueLogin(): string
    {
        $adminLogin = strtolower(trim(($this->adminLogin)()));
        for ($attempt = 0; $attempt < self::LOGIN_ATTEMPTS; $attempt++) {
            $login = ($this->loginFactory)();
            if (strtolower($login) !== $adminLogin) {
                return $login;
            }
        }

        throw new RuntimeException('Unable to issue a web login distinct from the admin login');
    }

    private function issuePassword(): string
    {
        $alphabetSize = strlen(self::PASSWORD_ALPHABET);
        $groups = [];
        for ($group = 0; $group < self::PASSWORD_GROUPS; $group++) {
            $chars = '';
            for ($position = 0; $position < self::PASSWORD_GROUP_LENGTH; $position++) {
                $chars .= self::PASSWORD_ALPHABET[random_int(0, $alphabetSize - 1)];
            }
            $groups[] = $chars;
        }

        return implode('-', $groups);
    }
}
