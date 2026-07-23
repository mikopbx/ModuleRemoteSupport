# ModuleRemoteSupport Release Automation Design

Date: 2026-07-23

## Goal

Prepare ModuleRemoteSupport for automated MikoPBX packaging and publishing
while keeping the first source publication on the `develop` branch.

## Translation Catalog Contract

Every `Messages/<locale>.php` file is a standalone PHP catalog. A catalog may
contain only `declare(strict_types=1);` and a literal `return [...]` array. It
must not use `require`, `include`, `array_keys`, `array_combine`, or any other
runtime composition. MikoPBX loads and processes each catalog itself.

All 29 catalogs have the same keys, key order, placeholders, and non-empty
localized values. Russian remains the source catalog.

## Build and Publishing Workflow

Add `.github/workflows/build.yml` based on the current
ModuleZabbixAgent5 workflow:

- run on pushes to `develop` and `master`;
- support manual `workflow_dispatch`;
- call
  `mikopbx/.github-workflows/.github/workflows/extension-publish.yml@master`;
- pass `initial_version: "1.0"`;
- inherit repository secrets.

The shared workflow calculates the first version as `1.1`. A push to `develop`
creates a GitHub prerelease and module archive. A later push to `master`
creates the production GitHub release and publishes the archive to the MikoPBX
release service.

Do not add module-specific build steps. The shared workflow removes the
`mikopbx/core` Composer dependency before packaging, while this module's
integration contracts and PHPStan configuration require a Core checkout.
Those checks run locally and in the MikoPBX container before the first push.

## Module Metadata

Extend `module.json` with:

- `release_settings.publish_release: true`;
- `release_settings.changelog_enabled: true`;
- `release_settings.create_github_release: true`.

Extend `composer.json` with explicit PHP 8.4 and Phalcon 5 requirements so the
shared workflow selects the PHP 8.4 module builder.

## User Documentation

Ship two administrator-facing documents:

- `README.md` in English as the default repository page;
- `README.ru.md` in Russian with equivalent content.

Write both for non-programmers. Explain what remote support does, the explicit
root-access and recording consent, the fixed eight-hour limit, how to start,
share the code, copy it, stop access, and recover from an error. Document the
outbound-only network requirements, privacy boundary, automatic cleanup, and
where to contact MikoPBX support. Keep development commands in a short,
separate contributor section after the user guidance.

## Reusable Skill Guidance

Update the canonical MikoPBX module and translation skills with two reusable
requirements, and add a documentation requirement to the module skill:

1. Production modules must inspect and, when absent, add the shared
   build/publish workflow plus release settings using a current production
   module as the reference.
2. Module translation files must be standalone literal arrays and must never
   compose catalogs at runtime.
3. Production module repositories must provide equivalent `README.md`
   (English default) and `README.ru.md` documents written for administrators,
   with technical contributor instructions kept secondary.

Because this task explicitly forbids subagents, validate the skill updates
with deterministic RED/GREEN content checks instead of subagent pressure
tests.

## Verification

Before Git publication:

1. Verify all translation files are standalone literal arrays.
2. Run PHP syntax checks, translation contracts, and the full contract suite.
3. Run PHPStan at level max with the module-local configuration.
4. Compile JavaScript with the mandated Babel command and verify the generated
   artifact is unchanged.
5. Validate module installation and UI behavior in a MikoPBX container.
6. Validate workflow YAML, release settings, Composer metadata, and archive
   exclusions.
7. Check both README files for equivalent user-facing sections and correct
   links.

## Git Publication

After all non-destructive checks pass:

1. Initialize the local repository.
2. Create and use the `develop` branch.
3. Add `git@github.com:mikopbx/ModuleRemoteSupport.git` as `origin`.
4. Commit the complete verified module.
5. Push `develop` and set its upstream.
6. Inspect the GitHub Actions run and development prerelease.

Do not create or push `master` during this task. Production publication waits
for an explicit later request.
