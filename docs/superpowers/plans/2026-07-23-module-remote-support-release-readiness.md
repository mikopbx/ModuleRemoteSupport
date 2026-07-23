# ModuleRemoteSupport Release Readiness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use
> `superpowers:executing-plans` and execute every task inline. The user
> explicitly prohibited subagents and requested sequential execution.

**Goal:** Make ModuleRemoteSupport release-ready with standalone translations,
administrator documentation, reusable MikoPBX build/publish automation, clean
static analysis, updated module skills, and a verified first push to
`develop`.

**Architecture:** Keep runtime module behavior unchanged. Convert translation
catalogs to literal data files, delegate packaging and releases to the shared
MikoPBX workflow, enforce repository contracts with local static tests, and
publish only after local and container validation.

**Tech Stack:** PHP 8.4, Phalcon 5, JavaScript/Babel Airbnb preset, GitHub
Actions, MikoPBX reusable extension workflow, Git.

## Global Constraints

- Process tasks sequentially without subagents.
- Do not create or push `master`; first publication is `develop`.
- First development prerelease is `v1.1` via `initial_version: "1.0"`.
- Every `Messages/*.php` file is a standalone literal `return [...]` catalog.
- `README.md` is English by default and `README.ru.md` is equivalent Russian
  administrator documentation.
- Compile JavaScript only with the mandated MikoPBXUtils Babel command.
- Run PHPStan at level max.
- Do not start a real remote-support session without separate explicit
  approval.

---

### Task 1: Enforce Standalone Translation Catalogs

**Files:**

- Modify: `Tests/check-translations-contract.php`
- Modify: `Messages/{az,cs,da,el,fi,hr,hu,ja,ka,ko,mn,nl,pl,pt,pt_BR,ro,sv,th,tr,uk,vi,zh_Hans,zh_TW}.php`

**Interfaces:**

- Consumes: the 49-key literal array in `Messages/ru.php`.
- Produces: 29 standalone catalogs loadable directly by MikoPBX.

- [ ] **Step 1: Add a failing source-shape assertion**

  For every catalog, tokenize its source and reject `T_REQUIRE`,
  `T_REQUIRE_ONCE`, `T_INCLUDE`, `T_INCLUDE_ONCE`, function calls before the
  top-level `return`, and the strings `array_keys` and `array_combine`.

- [ ] **Step 2: Run the contract and verify RED**

  Run:

  ```bash
  php Tests/check-translations-contract.php
  ```

  Expected: failure on the first catalog that composes values from `ru.php`.

- [ ] **Step 3: Replace computed catalogs with literal arrays**

  Preserve the existing localized strings and emit:

  ```php
  <?php

  declare(strict_types=1);

  return [
      'AdditionalMenuItemModuleRemoteSupport' => '...',
      // all remaining 48 keys in Russian source order
  ];
  ```

- [ ] **Step 4: Verify all catalogs**

  Run:

  ```bash
  find Messages -name '*.php' -print0 | xargs -0 -n1 php -l
  php Tests/check-translations-contract.php
  php Tests/run-all.php
  ```

  Expected: all syntax and contract checks pass.

### Task 2: Finish Level-Max Static Analysis

**Files:**

- Modify only PHP files reported by `phpstan.neon`
- Modify: `phpstan.neon` only when symbol discovery requires it

**Interfaces:**

- Consumes: Core source and Phalcon IDE stubs.
- Produces: zero module errors at PHPStan level max.

- [ ] **Step 1: Run focused PHPStan**

  ```bash
  /Users/nb/Developement/mikopbx/Core/vendor/bin/phpstan analyse \
    --memory-limit=512M \
    -c phpstan.neon \
    --no-progress
  ```

- [ ] **Step 2: Fix actual type and control-flow findings**

  Keep Phalcon ORM primary-key properties untyped, normalize REST request data
  to `array<string, mixed>`, use `ViewInterface::setVar()`, model process
  resources as nullable after close, and type repository transaction closures
  to return `RemoteSupportSession`.

- [ ] **Step 3: Re-run PHPStan and contracts**

  Expected: `[OK] No errors` and all contract tests pass.

### Task 3: Add Release Automation Contracts and Workflow

**Files:**

- Create: `Tests/check-release-contract.php`
- Modify: `Tests/run-all.php`
- Create: `.github/workflows/build.yml`
- Modify: `module.json`
- Modify: `composer.json`

**Interfaces:**

- Consumes:
  `mikopbx/.github-workflows/.github/workflows/extension-publish.yml@master`.
- Produces: develop prereleases and master production publications.

- [ ] **Step 1: Write the failing release contract**

  Assert exact triggers for `develop`, `master`, and `workflow_dispatch`;
  reusable workflow identity; `initial_version: "1.0"`; `secrets: inherit`;
  all three `release_settings` flags; PHP `~8.4`; and `ext-phalcon: ^5.0`.

- [ ] **Step 2: Run the contract and verify RED**

  ```bash
  php Tests/check-release-contract.php
  ```

- [ ] **Step 3: Add metadata and workflow**

  Use:

  ```yaml
  name: Build and Publish

  on:
    push:
      branches:
        - master
        - develop
    workflow_dispatch:

  jobs:
    build:
      uses: mikopbx/.github-workflows/.github/workflows/extension-publish.yml@master
      with:
        initial_version: "1.0"
      secrets: inherit
  ```

- [ ] **Step 4: Validate JSON, YAML source contract, and full tests**

  Expected: valid metadata and a passing release contract.

### Task 4: Write Administrator-Facing English and Russian README Files

**Files:**

- Modify: `README.md`
- Create: `README.ru.md`
- Create: `Tests/check-readme-contract.php`
- Modify: `Tests/run-all.php`

**Interfaces:**

- Produces equivalent English and Russian user guidance.

- [ ] **Step 1: Write a failing README contract**

  Require both files and equivalent sections for overview, security consent,
  start/share/stop instructions, eight-hour expiry, network requirements,
  privacy, troubleshooting, support, and contributor checks.

- [ ] **Step 2: Run the contract and verify RED**

  ```bash
  php Tests/check-readme-contract.php
  ```

- [ ] **Step 3: Write both README files**

  Lead with administrator workflows and plain-language safety boundaries.
  Keep development commands in the last section.

- [ ] **Step 4: Verify links, parity, and full tests**

  Expected: both READMEs pass the static content contract.

### Task 5: Correct the Canonical MikoPBX Skills

**Files:**

- Modify:
  `/Users/nb/Developement/mikopbx/Core/.claude/skills/mikopbx-module/SKILL.md`
- Modify:
  `/Users/nb/Developement/mikopbx/Core/.claude/skills/translations/SKILL.md`

**Interfaces:**

- Produces reusable guidance for production module CI, bilingual README files,
  and standalone translation catalogs.

- [ ] **Step 1: Record deterministic RED checks**

  Confirm the current skills do not state the standalone-array contract,
  production workflow contract, or bilingual administrator README contract.

- [ ] **Step 2: Add minimal reusable rules**

  Add explicit positive output contracts:

  - every module `Messages/<locale>.php` directly returns a literal array and
    performs no includes, function calls, merges, or runtime composition;
  - every production module checks the current shared build/publish workflow,
    release settings, and branch behavior against a maintained module;
  - every production module provides English-default and Russian administrator
    README documents.

- [ ] **Step 3: Run GREEN checks**

  Use `rg` assertions for all three rules and validate Markdown frontmatter is
  unchanged.

### Task 6: Final Verification, Git Initialization, and Develop Publication

**Files:**

- Modify: `README.md` or code only for verified findings
- Create: `.git/` through Git

**Interfaces:**

- Produces a pushed `develop` branch and inspected GitHub Actions prerelease.

- [ ] **Step 1: Run syntax, contracts, PHPStan, and Babel**

  Recompile with:

  ```bash
  INPUT_FILE=/Volumes/DevDisk/Developement/mikopbx/Extensions/ModuleRemoteSupport/public/assets/js/src/module-remote-support.js
  OUTPUT_DIR=/Volumes/DevDisk/Developement/mikopbx/Extensions/ModuleRemoteSupport/public/assets/js
  /Users/nb/PhpstormProjects/mikopbx/MikoPBXUtils/node_modules/.bin/babel \
    "$INPUT_FILE" \
    --out-dir "$OUTPUT_DIR" \
    --source-maps inline \
    --presets airbnb
  ```

- [ ] **Step 2: Validate in the MikoPBX container**

  Install or mount the module, verify lifecycle hooks, REST status, worker
  registration, menu rendering, and browser UI without starting a real support
  session.

- [ ] **Step 3: Inspect final files and secrets**

  Run the repository secret scan before publication. Confirm no private keys,
  support codes, runtime files, caches, or test artifacts are included.

- [ ] **Step 4: Initialize and commit**

  ```bash
  git init
  git switch -c develop
  git remote add origin git@github.com:mikopbx/ModuleRemoteSupport.git
  git add .
  git commit
  ```

- [ ] **Step 5: Push develop and inspect automation**

  ```bash
  git push -u origin develop
  ```

  Inspect the workflow run through `gh-system` and confirm the `v1.1`
  development prerelease and ZIP artifact. Do not create or push `master`.
