<?php

declare(strict_types=1);

use Modules\ModuleRemoteSupport\Lib\StationSshUser;

require_once __DIR__ . '/bootstrap.php';

contractAssertSame(
    'root',
    (new StationSshUser('root'))->login,
    'the default station account is accepted',
);

contractAssertSame(
    'protocol=2 station_user=mikoadmin',
    (new StationSshUser('mikoadmin'))->allocationRequest(),
    'the allocation request carries the protocol version and the station account',
);

$rejected = [
    'empty login' => '',
    'uppercase login' => 'Root',
    'embedded space' => 'root admin',
    'shell metacharacters' => 'root;id',
    'option injection' => '-oProxyCommand=id',
    'newline injection' => "root\nprotocol=1",
    'leading digit' => '1root',
    'too long' => str_repeat('a', 33),
];
foreach ($rejected as $case => $login) {
    $threw = false;
    try {
        new StationSshUser($login);
    } catch (RuntimeException) {
        $threw = true;
    }

    contractAssert($threw, $case . ' must be rejected');
}

// A station whose SSHLogin setting is unreadable or invalid must still get an
// SSH session: the lobby default matches the MikoPBX default account.
contractAssertSame(
    'root',
    StationSshUser::fromPbxConfiguration()->login,
    'an unavailable settings backend falls back to the default account',
);

echo "Station user contract: OK\n";
