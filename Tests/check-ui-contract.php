<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$viewPath = $root . '/App/Views/ModuleRemoteSupport/index.volt';
$controllerPath = $root . '/App/Controllers/ModuleRemoteSupportController.php';
$baseControllerPath = $root . '/App/Controllers/RemoteSupportBaseController.php';
$jsPath = $root . '/public/assets/js/src/module-remote-support.js';
$cssPath = $root . '/public/assets/css/module-remote-support.css';

contractAssert(is_file($viewPath), 'native Volt page must exist');
contractAssert(is_file($controllerPath), 'UI controller must exist');
contractAssert(is_file($baseControllerPath), 'UI base controller must exist');
contractAssert(is_file($jsPath), 'source JavaScript must exist');
contractAssert(is_file($cssPath), 'module stylesheet must exist');

$view = file_get_contents($viewPath);
$controller = file_get_contents($controllerPath);
$baseController = file_get_contents($baseControllerPath);
$js = file_get_contents($jsPath);
$css = file_get_contents($cssPath);
contractAssert(
    is_string($view)
        && is_string($controller)
        && is_string($baseController)
        && is_string($js)
        && is_string($css),
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
        'module_remote_support_ConsentWebAdmin',
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
        'remote-support-web-access',
        'remote-support-web-login',
        'remote-support-web-password',
        'remote-support-web-copy-login',
        'remote-support-web-copy-password',
    ] as $controlId
) {
    contractAssert(str_contains($view, 'id="' . $controlId . '"'), $controlId . ' exists');
}

contractAssert(
    str_contains($view, 'module_remote_support_WebInstruction'),
    'active state explains how to use the web credential',
);
contractAssert(
    str_contains($view, 'module_remote_support_WebLoginLabel')
        && str_contains($view, 'module_remote_support_WebPasswordLabel'),
    'web credential fields are labelled',
);

contractAssert(
    !str_contains(
        $view,
        "<h2 class=\"ui header\">{{ t._('module_remote_support_Title') }}</h2>",
    ),
    'module does not duplicate the page title',
);
contractAssert(
    !str_contains($view, 'remote-support-header')
        && !str_contains($view, 'module_remote_support_Description')
        && !str_contains($view, 'logoImagePath'),
    'introductory support block stays removed from the module body',
);
contractAssert(
    str_contains($baseController, "'logoImagePath'")
        && str_contains($baseController, "'assets/img/cache/'")
        && str_contains($baseController, 'self::MODULE_UNIQUE_ID')
        && str_contains($baseController, "'/logo.svg'"),
    'shared MikoPBX module header receives the module logo',
);
contractAssert(
    !str_contains(
        $view,
        "<h3 class=\"ui header\">{{ t._('module_remote_support_StateOff') }}</h3>",
    ),
    'off state does not repeat the lower status heading',
);
contractAssert(
    str_contains(
        $view,
        '<div id="remote-support-page" class="ui large grey segment module-remote-support">',
    ),
    'page uses one large grey module segment',
);
contractAssert(
    preg_match('/\.module-remote-support\s*\{[^}]*max-width\s*:/s', $css) !== 1
        && preg_match('/\.module-remote-support\s*\{[^}]*margin\s*:\s*0\s+auto/s', $css) !== 1,
    'module segment uses all horizontal space provided by the admin layout',
);
contractAssertSame(
    1,
    substr_count($view, 'id="remote-support-summary"'),
    'page has one calm status summary',
);
contractAssert(
    str_contains($view, 'class="remote-support-summary-led"')
        && str_contains($view, 'class="remote-support-summary-text"'),
    'status summary contains a LED and localized text',
);
foreach (
    [
        'remote-support-summary-grey',
        'remote-support-summary-green',
        'remote-support-summary-yellow',
        'remote-support-summary-red',
    ] as $summaryClass
) {
    contractAssert(str_contains($css, '.' . $summaryClass), $summaryClass . ' is styled');
    contractAssert(str_contains($js, "'" . $summaryClass . "'"), $summaryClass . ' is driven by JS');
}
contractAssert(
    !str_contains($view, 'class="ui segment remote-support-state"'),
    'state content does not create nested segment cards',
);
foreach (
    [
        'module_remote_support_StateStarting',
        'module_remote_support_StateActive',
        'module_remote_support_StateStopping',
        'module_remote_support_StateError',
    ] as $stateKey
) {
    contractAssert(
        !preg_match(
            '/<(?:h[1-6]|div)[^>]*class="[^"]*(?:header|message)[^"]*"[^>]*>'
                . '\s*\{\{\s*t\._\(\'' . preg_quote($stateKey, '/') . '\'\)\s*\}\}/',
            $view,
        ),
        $stateKey . ' is not repeated as a large state heading',
    );
}
contractAssert(
    str_contains($view, 'href="tel:+74952293042"')
        && str_contains($view, '+7 495 229-30-42'),
    'fixed support phone is rendered',
);
contractAssert(
    str_contains($view, 'href="https://t.me/Telefon1CBot?start=[mkpbx]"'),
    'fixed Telegram bot is rendered',
);
contractAssert(!str_contains($view, 'remote-support-website'), 'website button is removed');
contractAssert(
    !str_contains($view, 'remote-support-contact-fallback'),
    'missing-contact fallback is removed',
);
contractAssert(!preg_match('/<form\b/i', $view), 'page has no settings form');
contractAssert(!preg_match('/<input\b[^>]*(ttl|duration|hours)/i', $view), 'TTL is not configurable');
contractAssert(str_contains($view, 'aria-live="polite"'), 'state changes use an accessible live region');
contractAssert(
    str_contains($js, 'renderSummary(state)'),
    'render updates the single status summary',
);

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
    str_contains($js, 'webLogin') && str_contains($js, 'webPassword'),
    'JS renders the ephemeral web credential',
);
contractAssert(
    !preg_match('/webPassword.{0,80}href|href.{0,80}webPassword/is', $js),
    'web password is never appended to href',
);
contractAssert(
    !preg_match('/webLogin.{0,80}href|href.{0,80}webLogin/is', $js),
    'web login is never appended to href',
);
contractAssert(
    !str_contains($js, '?webPassword=') && !str_contains($js, '?webLogin='),
    'web credential is never placed in a query string',
);
contractAssert(
    str_contains($js, "['starting', 'active', 'stopping'].includes(state)"),
    'polling runs only for transitional or active states',
);
contractAssert(str_contains($js, 'navigator.clipboard'), 'copy uses Clipboard API');
contractAssert(str_contains($js, 'document.execCommand'), 'copy has a safe fallback');
contractAssert(!str_contains($js, 'renderContacts'), 'contacts are not rendered from REST data');
contractAssert(!str_contains($js, '$contactFallback'), 'contact fallback is not cached');

echo "UI contract: OK\n";
