<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$englishPath = $root . '/README.md';
$russianPath = $root . '/README.ru.md';
contractAssert(is_file($englishPath), 'English README exists');
contractAssert(is_file($russianPath), 'Russian README exists');

$english = file_get_contents($englishPath);
$russian = file_get_contents($russianPath);
contractAssert(is_string($english) && is_string($russian), 'README files are readable');

foreach (
    [
        '## What it does',
        '## Before you start',
        '## Start a support session',
        '## Share the support code',
        '## End remote access',
        '## Security and privacy',
        '## Network requirements',
        '## Troubleshooting',
        '## Contact support',
        '## For contributors',
        'eight hours',
        'root',
        'recorded',
        'support-tunnel.miko.ru:34022',
        'www.mikopbx.com:443',
        'README.ru.md',
    ] as $fragment
) {
    contractAssert(str_contains($english, $fragment), 'English README contains ' . $fragment);
}

foreach (
    [
        '## Что делает модуль',
        '## Перед началом',
        '## Как запустить сеанс поддержки',
        '## Как передать код поддержки',
        '## Как завершить удалённый доступ',
        '## Безопасность и конфиденциальность',
        '## Требования к сети',
        '## Устранение неполадок',
        '## Как связаться с поддержкой',
        '## Для разработчиков',
        'восемь часов',
        'root',
        'записываться',
        'support-tunnel.miko.ru:34022',
        'www.mikopbx.com:443',
        'README.md',
    ] as $fragment
) {
    contractAssert(str_contains($russian, $fragment), 'Russian README contains ' . $fragment);
}

foreach ([$english, $russian] as $readme) {
    contractAssert(
        str_contains($readme, 'https://www.mikopbx.com/support/'),
        'README contains the support website',
    );
    contractAssert(
        !str_contains($readme, '?code='),
        'README never suggests putting the support code in a URL',
    );
}

echo "README contract: OK\n";
