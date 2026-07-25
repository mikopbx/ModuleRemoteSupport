# MikoPBX Remote Support

[Русская версия](README.ru.md)

ModuleRemoteSupport lets a MikoPBX administrator open one temporary, secure
remote support session for a MIKO engineer. You remain in control: the module
starts only after explicit confirmation, shows a code that you share manually,
and lets you end access at any time.

## What it does

- creates an outbound encrypted support tunnel;
- grants the support engineer temporary `root` access to this PBX over SSH;
- grants the support engineer temporary full administrator access to the web
  interface with a one-time login and password shown on the page;
- shows a one-time support code;
- removes the temporary key, web credential, and private runtime files when
  access ends;
- disconnects automatically after exactly eight hours.

The module does not create an inbound firewall rule and does not require a
public IP address.

## Before you start

Starting a session means that:

- a MIKO support engineer receives temporary `root` access over SSH;
- the engineer also receives temporary full administrator access to the web
  interface through a one-time login and password;
- SSH activity is fully recorded; web-interface activity is logged, but the
  screen is not recorded;
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

## Web access for the engineer

When the session is active, the page also shows a one-time web login and
password. Dictate them to the MIKO specialist; they let the engineer sign in to
this PBX web interface as a full administrator through the MIKO support gateway.

- The credential works only while the session is active and is revoked with the
  rest of the session on stop, expiry, disconnect, reboot, disable, or
  uninstall.
- Only the password hash is stored in the database. The plaintext password is
  kept in a private runtime file readable by the web-server user so the page can
  show it again after a reload; it is readable for the whole active session and
  is deleted on cleanup.
- The engineer's web login appears in the MikoPBX logs, providing attribution
  that the loopback path does not.
- The web tunnel forwards to the real station address, not to loopback, so the
  ephemeral password is always required; no unauthenticated bypass is used.
- Older support servers that offer only SSH degrade gracefully: the session
  stays SSH-only and no web credential is issued.

## Security and privacy

- SSH host verification is pinned; it is never disabled.
- HTTPS certificate verification remains enabled.
- Private SSH keys are temporary and are never stored in the database.
- The web password is stored only as a hash in the database; its plaintext lives
  in a private runtime file readable by the web-server user and is extractable
  for the whole active session.
- The support code, web password hash, private key, raw SSH output, PBX
  inventory, call data, and configuration are not sent to contact or analytics
  services.
- The tunnel service can see the public source IP of the outbound connection.
- Support contact links never include the temporary session code or credential.

## Network requirements

The PBX needs DNS resolution and outbound TCP access to:

- `support-tunnel.miko.ru:34022` for session allocation and the secure tunnel.

MikoPBX 2025.1.1 or newer, OpenSSH `ssh`, `ssh-keygen`, Ed25519 support, and
writable private runtime storage are required.

## Troubleshooting

- **The session does not start:** verify DNS and outbound access to
  `support-tunnel.miko.ru:34022`.
- **The page reports that the system is not ready:** confirm that `ssh` and
  `ssh-keygen` are installed and private runtime storage is writable.
- **The tunnel disconnects:** end the failed session, check network stability,
  and try again.
- **Access does not return to off:** do not disable or uninstall repeatedly.
  Contact support so temporary access can be verified and revoked safely.
## Contact support

Call `+7 495 229-30-42` or open
[Telegram](https://t.me/Telefon1CBot?start=[mkpbx]). The module never appends
the temporary support code to either contact link. Never place the support code
in a URL.

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
