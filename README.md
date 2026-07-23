# MikoPBX Remote Support

[Русская версия](README.ru.md)

ModuleRemoteSupport lets a MikoPBX administrator open one temporary, secure
remote support session for a MIKO engineer. You remain in control: the module
starts only after explicit confirmation, shows a code that you share manually,
and lets you end access at any time.

## What it does

- creates an outbound encrypted support tunnel;
- grants the support engineer temporary `root` access to this PBX;
- shows a one-time support code;
- removes the temporary key and private runtime files when access ends;
- disconnects automatically after exactly eight hours.

The module does not create an inbound firewall rule and does not require a
public IP address.

## Before you start

Starting a session means that:

- a MIKO support engineer receives temporary `root` access;
- the engineer's terminal activity may be recorded for security and audit;
- the session can last for up to eight hours;
- only one support session can be active on the PBX.

Start a session only while working with a support specialist you trust.

## Start a support session

1. Sign in to the MikoPBX administration interface.
2. Open **Remote Support** in the module section of the sidebar.
3. Read the access and recording notice.
4. Select **Allow temporary access**.
5. Wait until the page reports that remote access is active.

If preparation fails, the page shows a safe error message without exposing
keys or technical command output. Correct the reported problem and select
**Try again**.

## Share the support code

When the session becomes active, the page displays a short one-time code.
Select **Copy code** and send it manually to the MIKO specialist through the
support conversation you already use.

The module never adds the code to a website link, Telegram link, analytics
request, or log. Do not publish the code in a public issue or forum.

## End remote access

Select **End access** on the Remote Support page. The module closes the tunnel,
removes its temporary authorized key, deletes private runtime files, and
returns to the off state.

The same cleanup runs after a failure, unexpected disconnection, PBX reboot,
module disable, module uninstall, or the fixed eight-hour expiry.

## Security and privacy

- SSH host verification is pinned; it is never disabled.
- HTTPS certificate verification remains enabled.
- Private SSH keys are temporary and are never stored in the database.
- The support code, private key, raw SSH output, PBX inventory, call data, and
  configuration are not sent to contact or analytics services.
- The tunnel service can see the public source IP of the outbound connection.
- Support contacts are downloaded over HTTPS and treated as untrusted data.

## Network requirements

The PBX needs DNS resolution and outbound TCP access to:

- `support-tunnel.miko.ru:34022` for session allocation and the secure tunnel;
- `www.mikopbx.com:443` for optional support contact details.

MikoPBX 2025.1.1 or newer, OpenSSH `ssh`, `ssh-keygen`, Ed25519 support, and
writable private runtime storage are required.

## Troubleshooting

- **The session does not start:** verify DNS and both outbound destinations.
- **The page reports that the system is not ready:** confirm that `ssh` and
  `ssh-keygen` are installed and private runtime storage is writable.
- **The tunnel disconnects:** end the failed session, check network stability,
  and try again.
- **Access does not return to off:** do not disable or uninstall repeatedly.
  Contact support so temporary access can be verified and revoked safely.
- **Support contacts are missing:** use the support website link shown on the
  page; the tunnel can still be controlled independently.

## Contact support

Use the [MikoPBX support website](https://www.mikopbx.com/support/) or email
`help@miko.ru`. Never place the support code in a URL.

## For contributors

Run all module contracts:

```bash
php Tests/run-all.php
```

Check PHP syntax and level-max static analysis:

```bash
find App Lib Models Setup Tests bin -name '*.php' -print0 \
  | xargs -0 -n1 php -l

/Users/nb/Developement/mikopbx/Core/vendor/bin/phpstan analyse \
  --memory-limit=512M \
  -c phpstan.neon \
  --no-progress
```

Compile JavaScript only with the Babel command documented in the repository
agent instructions.
