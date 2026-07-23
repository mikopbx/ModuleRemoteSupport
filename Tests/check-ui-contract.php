<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$viewPath = $root . '/App/Views/ModuleRemoteSupport/index.volt';
$controllerPath = $root . '/App/Controllers/ModuleRemoteSupportController.php';
$jsPath = $root . '/public/assets/js/src/module-remote-support.js';

contractAssert(is_file($viewPath), 'native Volt page must exist');
contractAssert(is_file($controllerPath), 'UI controller must exist');
contractAssert(is_file($jsPath), 'source JavaScript must exist');

$view = file_get_contents($viewPath);
$controller = file_get_contents($controllerPath);
$js = file_get_contents($jsPath);
contractAssert(
    is_string($view) && is_string($controller) && is_string($js),
    'UI sources must be readable',
);
contractAssert(
    !preg_match('/\$this->view->pick\s*\(/', $controller),
    'controller relies on BaseController module view resolution',
);

foreach (['off', 'starting', 'active', 'stopping', 'error'] as $state) {
    contractAssertSame(
        1,
        substr_count($view, 'data-state-view="' . $state . '"'),
        'view has exactly one root for ' . $state,
    );
}

foreach (
    [
        'module_remote_support_ConsentRoot',
        'module_remote_support_ConsentEightHours',
        'module_remote_support_ConsentRecording',
    ] as $consentKey
) {
    contractAssert(str_contains($view, $consentKey), 'consent includes ' . $consentKey);
}

foreach (
    [
        'remote-support-start',
        'remote-support-copy',
        'remote-support-stop',
        'remote-support-retry',
        'remote-support-phone',
        'remote-support-telegram',
        'remote-support-website',
    ] as $controlId
) {
    contractAssert(str_contains($view, 'id="' . $controlId . '"'), $controlId . ' exists');
}

contractAssert(
    str_contains($view, 'href="https://www.mikopbx.com/support/"'),
    'website fallback is always present',
);
contractAssert(!preg_match('/<form\b/i', $view), 'page has no settings form');
contractAssert(!preg_match('/<input\b[^>]*(ttl|duration|hours)/i', $view), 'TTL is not configurable');
contractAssert(str_contains($view, 'aria-live="polite"'), 'state changes use an accessible live region');

contractAssert(
    str_contains($js, "KNOWN_STATES: ['off', 'starting', 'active', 'stopping', 'error']"),
    'JS allowlists states',
);
contractAssert(str_contains($js, ".text("), 'remote text uses text-safe jQuery API');
contractAssert(!str_contains($js, '.html('), 'remote text never uses HTML rendering');
contractAssert(!str_contains($js, 'console.'), 'session data is never logged');
contractAssert(!str_contains($js, '?code='), 'code is never placed in a query string');
contractAssert(
    !preg_match('/code.{0,80}href|href.{0,80}code/is', $js),
    'code is never appended to href',
);
contractAssert(
    str_contains($js, "['starting', 'active', 'stopping'].includes(state)"),
    'polling runs only for transitional or active states',
);
contractAssert(str_contains($js, 'navigator.clipboard'), 'copy uses Clipboard API');
contractAssert(str_contains($js, 'document.execCommand'), 'copy has a safe fallback');

echo "UI contract: OK\n";
