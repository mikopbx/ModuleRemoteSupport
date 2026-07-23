# Compact Remote Support Page Design

**Date:** 2026-07-23

## Goal

Make the Remote Support page easier for a non-technical administrator to scan,
use the same calm status-summary pattern as ModuleCTIClient, and expose only
the current direct MIKO support contacts without embedding third-party
live-chat code.

## Interface

- Keep the page title rendered by the MikoPBX administration shell.
- Keep the module logo in the shared MikoPBX module header.
- Render the module body as one `ui large grey segment`; do not use a settings
  form or nested segment cards for individual session states. Do not constrain
  the segment with a module-specific maximum width; it fills the horizontal
  space provided by the administration layout.
- Remove the logo/description block from the module body, duplicate state
  headings, and the obsolete `module_remote_support_Description` translation
  from every locale.
- Add one status summary at the top with a localized label and a round LED:
  - grey for `off`;
  - yellow for `starting` and `stopping`;
  - green for `active`;
  - red for `error`.
- Preserve the existing session states, consent notices, session code, timer,
  and start/stop actions.
- Show only the current state's content below the summary. The summary owns the
  state label, so progress, active, and error content do not repeat it as a
  heading or message header.
- Keep a compact contacts area, separated by a divider, containing exactly:
  - phone link `tel:+74952293042`, displayed as `+7 495 229-30-42`;
  - Telegram link
    `https://t.me/Telefon1CBot?start=[mkpbx]`, displayed as `Telegram`.
- Remove the support-website button and the missing-contacts fallback text.
- Do not load or embed the Bitrix24 live-chat loader.

## Data Flow

Contacts are immutable module UI configuration for this version. Volt renders
the two links directly, so the browser does not wait for the session status
request before showing them.

The session REST response contains only session state data. It no longer fetches
`contacts.json` and no longer returns `contacts` or `supportSite`.

The JavaScript controller remains responsible only for session state and
actions. Dynamic contact rendering and its cached DOM references are removed.

## Cleanup

Remove the unused remote-contact client, contact value object, their contract
test, contact response schema fields, obsolete configuration constants, and
README statements about downloading contacts from the website.

All translation catalogs remain direct PHP literal arrays with the same keys.
No translation-time data processing is introduced.

## Module Lifecycle

The core holds a per-module state lock while it calls enable and disable hooks.
Those hooks must never call `Processes::restartAllWorkers()`: a synchronous
global restart can inherit the open lock descriptor and permanently block
future lifecycle operations.

After enable and disable, the module manages only
`WorkerRemoteSupportTunnel` through `Processes::processPHPWorker()` with the
explicit `start` or `stop` action. The core remains responsible for rebuilding
its module provider and route registry.

## Security and Privacy

No third-party JavaScript executes inside the authenticated MikoPBX
administration origin. Contact actions are ordinary links. Telegram opens in a
new tab with `noopener noreferrer`; the phone link uses the `tel:` scheme.

## Verification

- Contract tests assert the shared-header logo, full-width grey segment, status
  summary, LED color mapping, absence of duplicate headings, and removal of the
  obsolete introductory translation.
- Contract tests assert that the website button, fallback text, and dynamic
  contact renderer are absent.
- Contract tests assert the exact phone and Telegram destinations.
- API tests assert that status responses and schemas contain session data only.
- Lifecycle tests prohibit global worker restart from module hooks and require
  explicit start/stop management of the tunnel worker.
- Compile the source JavaScript with the configured Airbnb Babel preset.
- Run the complete PHP contract suite, PHP syntax checks, PHPStan, and
  JavaScript syntax checks.
- Deploy to the demo station, restart the module through REST, and visually
  verify the `off` state without starting a remote-support session.
