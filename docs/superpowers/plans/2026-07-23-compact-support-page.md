# Compact Remote Support Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Present remote support in one CTI-style status segment and replace
remotely downloaded support contacts with one fixed phone link and one fixed
Telegram link.

**Architecture:** Volt owns one large grey container and immutable contact
presentation. JavaScript maps the five server states onto one localized status
summary with a grey, yellow, green, or red LED. The REST endpoint owns session
state only. The remote contact client, contact DTO, response fields, and
fallback website UI are removed so the page has no contact-fetch dependency and
loads no third-party scripts.

**Tech Stack:** PHP 8.4, Phalcon Volt, MikoPBX REST v3, Semantic UI, jQuery,
ES6 compiled with Babel Airbnb, PHP contract tests, PHPStan.

## Global Constraints

- Execute sequentially in the current workspace; do not use subagents.
- Do not run `git add`, create commits, or push during this plan.
- Keep translation files as plain PHP arrays with no includes or data
  transformations.
- Do not embed the Bitrix24 live-chat loader or any third-party JavaScript.
- Render exactly `+7 495 229-30-42` and link it to `tel:+74952293042`.
- Link Telegram to `https://t.me/Telefon1CBot?start=[mkpbx]`.
- Preserve session states, consent notices, code, countdown, and start/stop
  behavior.
- Use one `ui large grey segment` and one status summary; do not wrap state
  content in nested segment cards or repeat state labels as headings.
- Keep the module logo in the shared MikoPBX module header and let the main
  segment fill all horizontal space supplied by the administration layout.
- Remove `module_remote_support_Description` from every plain translation
  array; preserve the state translations used by the status summary.
- Compile source JavaScript only with:
  `/Users/nb/PhpstormProjects/mikopbx/MikoPBXUtils/node_modules/.bin/babel "$INPUT_FILE" --out-dir "$OUTPUT_DIR" --source-maps inline --presets airbnb`.

---

### Task 1: CTI-style status and static contact interface

**Files:**
- Modify: `Tests/check-ui-contract.php`
- Modify: `App/Controllers/RemoteSupportBaseController.php`
- Modify: `App/Views/ModuleRemoteSupport/index.volt`
- Modify: `public/assets/css/module-remote-support.css`
- Modify: `public/assets/js/src/module-remote-support.js`
- Generate: `public/assets/js/module-remote-support.js`

**Interfaces:**
- Consumes: the existing session response fields `state`, `code`, `expiresAt`,
  and `errorCode`.
- Produces: fixed anchors `#remote-support-phone` and
  `#remote-support-telegram`; JavaScript with no contact-rendering API.

- [ ] **Step 1: Write the failing UI contract**

Replace the contact-control and website assertions in
`Tests/check-ui-contract.php` with assertions for the single status segment and
compact contact interface.

```php
foreach (
    [
        'remote-support-start',
        'remote-support-copy',
        'remote-support-stop',
        'remote-support-retry',
        'remote-support-phone',
        'remote-support-telegram',
    ] as $controlId
) {
    contractAssert(str_contains($view, 'id="' . $controlId . '"'), $controlId . ' exists');
}

contractAssertSame(
    0,
    substr_count($view, "<h2 class=\"ui header\">{{ t._('module_remote_support_Title') }}</h2>"),
    'module does not duplicate the page title',
);
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
contractAssert(!str_contains($js, 'renderContacts'), 'contacts are not rendered from REST data');
contractAssert(!str_contains($js, '$contactFallback'), 'contact fallback is not cached');
```

- [ ] **Step 2: Run the UI contract and verify RED**

Run:

```bash
php Tests/check-ui-contract.php
```

Expected: failure because the duplicate heading and website fallback still
exist and the fixed phone/Telegram destinations are absent.

- [ ] **Step 3: Implement the CTI-style status segment**

In `App/Views/ModuleRemoteSupport/index.volt`, make the page root a
`ui large grey segment`, add the LED summary, remove all duplicate state
headings, and replace the dynamic contact controls with fixed links.

```html
<aside class="remote-support-contacts">
    <h4 class="ui header">{{ t._('module_remote_support_Contacts') }}</h4>
    <a id="remote-support-phone"
       class="ui basic button"
       href="tel:+74952293042">
        <i class="phone icon"></i>
        +7 495 229-30-42
    </a>
    <a id="remote-support-telegram"
       class="ui basic button"
       href="https://t.me/Telefon1CBot?start=[mkpbx]"
       target="_blank"
       rel="noopener noreferrer">
        <i class="telegram plane icon"></i>
        Telegram
    </a>
</aside>
```

Style the summary using the ModuleCTIClient color system: grey for off, yellow
for transitions, green for active, and red for error. Remove obsolete body
header and contact-fallback CSS. Pass `logoImagePath` from
`RemoteSupportBaseController` so the common MikoPBX module header renders the
logo, and do not apply `max-width` or centered auto margins to the root module
segment.

- [ ] **Step 4: Remove dynamic contact rendering from source JavaScript**

From `public/assets/js/src/module-remote-support.js`:

- cache the single summary container;
- add an allowlisted state-to-summary-class mapping;
- update the summary label and color from `render()`;
- remove `$phone`, `$telegram`, and `$contactFallback` properties;
- remove the corresponding jQuery lookups from `initialize()`;
- remove `moduleRemoteSupport.renderContacts(...)` from `render()`;
- delete the entire `renderContacts(contacts)` method;
- remove `contacts: []` from the object passed by `renderSafeError()`.

Remove `module_remote_support_Description` from all 29 literal translation
arrays and assert its absence in the translation contract.

The resulting safe-error call is:

```javascript
moduleRemoteSupport.render({
    state: 'error',
    errorCode: safeCode,
});
```

- [ ] **Step 5: Compile the source JavaScript**

Run:

```bash
/Users/nb/PhpstormProjects/mikopbx/MikoPBXUtils/node_modules/.bin/babel \
  public/assets/js/src/module-remote-support.js \
  --out-dir public/assets/js \
  --source-maps inline \
  --presets airbnb
```

Expected: `public/assets/js/module-remote-support.js` is regenerated without
`renderContacts`, `$phone`, `$telegram`, or `$contactFallback`.

- [ ] **Step 6: Verify Task 1 GREEN**

Run:

```bash
php Tests/check-ui-contract.php
node --check public/assets/js/src/module-remote-support.js
node --check public/assets/js/module-remote-support.js
```

Expected: `UI contract: OK` and both Node syntax checks exit zero.

### Task 2: Session-only REST response

**Files:**
- Modify: `Tests/check-api-contract.php`
- Modify: `Tests/run-all.php`
- Modify: `Lib/RestAPI/Session/Actions/GetStatusAction.php`
- Modify: `Lib/RestAPI/Session/DataStructure.php`
- Modify: `Lib/RemoteSupportConfig.php`
- Delete: `Lib/SupportContact.php`
- Delete: `Lib/SupportContactsClient.php`
- Delete: `Tests/check-contacts-contract.php`

**Interfaces:**
- Consumes: `RemoteSupportSession`.
- Produces:
  `GetStatusAction::fromSession(RemoteSupportSession $session): PBXApiResult`
  with only `state`, `code`, `startedAt`, `expiresAt`, and `errorCode`.

- [ ] **Step 1: Write the failing REST contract**

Remove the `SupportContact` import and `$contacts` fixture from
`Tests/check-api-contract.php`. Call:

```php
$statusResult = GetStatusAction::fromSession($session);
```

After the existing state/code assertions, add:

```php
contractAssert(
    !array_key_exists('contacts', $statusResult->data),
    'GET does not expose dynamic contacts',
);
contractAssert(
    !array_key_exists('supportSite', $statusResult->data),
    'GET does not expose a website fallback',
);

$dataStructure = file_get_contents(
    $root . '/Lib/RestAPI/Session/DataStructure.php',
);
contractAssert(is_string($dataStructure), 'REST data structure must be readable');
contractAssert(
    !str_contains($dataStructure, "'contacts' =>"),
    'REST schema has no contacts field',
);
contractAssert(
    !str_contains($dataStructure, "'supportSite' =>"),
    'REST schema has no supportSite field',
);
```

- [ ] **Step 2: Run the REST contract and verify RED**

Run:

```bash
php Tests/check-api-contract.php
```

Expected: failure because `fromSession()` still requires contacts and the
response/schema still expose contact fields.

- [ ] **Step 3: Make GetStatusAction session-only**

Change `main()` to:

```php
return self::fromSession((new SessionRepository())->get());
```

Change `fromSession()` to:

```php
public static function fromSession(RemoteSupportSession $session): PBXApiResult
{
    $result = new PBXApiResult();
    $result->success = true;
    $result->data = self::sessionData($session);

    return $result;
}
```

Remove the imports for `RemoteSupportConfig`, `SupportContact`, and
`SupportContactsClient`.

- [ ] **Step 4: Remove the contact schema and configuration**

Delete `contacts` and `supportSite` definitions from
`Lib/RestAPI/Session/DataStructure.php`.

Delete these constants from `Lib/RemoteSupportConfig.php`:

```php
public const string CONTACTS_URL = 'https://www.mikopbx.com/support/contacts.json';
public const string SUPPORT_SITE = 'https://www.mikopbx.com/support/';
public const float CONTACT_CONNECT_TIMEOUT = 2.0;
public const float CONTACT_TIMEOUT = 3.0;
public const int CONTACT_MAX_BYTES = 65_536;
```

Delete `Lib/SupportContact.php`, `Lib/SupportContactsClient.php`, and
`Tests/check-contacts-contract.php`. Remove
`check-contacts-contract.php` from the list in `Tests/run-all.php`.

- [ ] **Step 5: Verify Task 2 GREEN**

Run:

```bash
php Tests/check-api-contract.php
php Tests/run-all.php
rg -n "SupportContactsClient|SupportContact|CONTACTS_URL|supportSite|data\\.contacts" \
  App Lib Tests public/assets/js/src public/assets/js/module-remote-support.js
```

Expected: both test commands pass. `rg` returns no matches.

### Task 3: Documentation, quality gates, and demo deployment

**Files:**
- Modify: `README.md`
- Modify: `README.ru.md`
- Verify: all changed PHP, Volt, CSS, JavaScript, tests, and documentation
- Deploy: changed runtime files under
  `/storage/usbdisk1/mikopbx/custom_modules/ModuleRemoteSupport`

**Interfaces:**
- Consumes: the completed static UI and session-only REST response.
- Produces: user documentation matching the shipped behavior and a restarted
  demo module in the `off` state.

- [ ] **Step 1: Update English documentation**

In `README.md`:

- replace the downloaded-contact security bullet with:
  `Support contact links never include the temporary session code.`;
- remove `www.mikopbx.com:443` from network requirements;
- change troubleshooting to require only the tunnel destination;
- remove the missing-contacts troubleshooting item;
- replace the support website/email paragraph with the fixed phone and Telegram
  contacts, while retaining the warning never to place the session code in a
  URL.

- [ ] **Step 2: Update Russian documentation**

Apply the equivalent changes in `README.ru.md`, documenting:

```text
Телефон: +7 495 229-30-42
Telegram: https://t.me/Telefon1CBot?start=[mkpbx]
```

State explicitly that the module does not append the session code to either
contact link.

- [ ] **Step 3: Run complete local verification**

Run:

```bash
php Tests/run-all.php
find App Lib Models Setup Tests bin -name '*.php' -print0 \
  | xargs -0 -n1 php -l
/opt/homebrew/bin/phpstan analyse --no-progress --memory-limit=1G
node --check public/assets/js/src/module-remote-support.js
node --check public/assets/js/module-remote-support.js
git diff --check
git status --short --branch
```

Expected:

- all contract files pass;
- every PHP file reports no syntax errors;
- PHPStan reports `[OK] No errors` when MikoPBX dependencies are available;
- both JavaScript syntax checks exit zero;
- no whitespace errors;
- only the intended uncommitted files are listed.

If full PHPStan cannot load local Phalcon runtime classes, run the focused
configuration already used for module-only classes and report the environment
limitation without suppressing errors.

- [ ] **Step 4: Deploy changed runtime files to the demo station**

Copy the changed Volt, CSS, compiled JavaScript, REST action/schema, and config
files to the matching paths below:

```text
/storage/usbdisk1/mikopbx/custom_modules/ModuleRemoteSupport/
```

The deleted contact classes may remain as inert files on the installed test
copy; the new code must not reference them. Do not start a support session.

- [ ] **Step 5: Restart and verify the demo module**

On `root@172.16.32.85`:

```bash
curl -sS -X POST \
  http://127.0.0.1/pbxcore/api/v3/modules/ModuleRemoteSupport:disable
curl -sS -X POST \
  http://127.0.0.1/pbxcore/api/v3/modules/ModuleRemoteSupport:enable
curl -sS \
  http://127.0.0.1/pbxcore/api/v3/module-remote-support/session
```

Expected:

- disable and enable both return `"result": true`;
- GET returns `"state": "off"`;
- GET contains no `contacts` or `supportSite`;
- the page shows one grey segment, the grey off-state summary LED, phone,
  Telegram, and no introductory or support-website block.

- [ ] **Step 6: Leave changes unstaged**

Run:

```bash
git status --short --branch
```

Expected: intended changes remain unstaged/untracked. Do not run `git add`,
commit, or push.

### Task 4: Prevent lifecycle lock inheritance

**Files:**
- Modify: `Tests/check-module-lifecycle-contract.php`
- Modify: `Lib/RemoteSupportConf.php`

**Interfaces:**
- Consumes: MikoPBX core enable/disable hooks while the per-module state lock is
  held.
- Produces: start/stop operations scoped to
  `WorkerRemoteSupportTunnel::class`.

- [ ] **Step 1: Write and verify a failing lifecycle contract**

Assert that `RemoteSupportConf` never calls `restartAllWorkers`, invokes
`Processes::processPHPWorker()` exactly for enable and disable, and contains
the explicit `start` and `stop` actions.

Run:

```bash
php Tests/check-module-lifecycle-contract.php
```

Expected: failure because the old hook restarts every MikoPBX worker while the
core module-state lock is still held.

- [ ] **Step 2: Scope lifecycle hooks to the module worker**

Replace the shared provider/global-worker restart helper with:

```php
Processes::processPHPWorker(
    WorkerRemoteSupportTunnel::class,
    action: 'start',
);
```

Use the corresponding `stop` action after disable. Do not rebuild the global
module provider from the hook; the core enable/disable actions own that step.

- [ ] **Step 3: Verify lifecycle GREEN**

Run:

```bash
php Tests/check-module-lifecycle-contract.php
php Tests/run-all.php
/opt/homebrew/bin/phpstan analyse --debug --no-progress --memory-limit=1G
```

Expected: lifecycle and full contract suites pass, and PHPStan reports no
errors without opening its parallel-worker TCP listener.
