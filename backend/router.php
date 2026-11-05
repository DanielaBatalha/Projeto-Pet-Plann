<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filename = __DIR__ . $uri;

if ($uri !== '/' && file_exists($filename)) {
    return false;
}

require __DIR__ . '/index.php';
