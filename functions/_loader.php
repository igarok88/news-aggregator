<?php

// цикл для автоматического подключения

$files = glob(__DIR__ . '/*.php');
foreach ($files as $file) {
    if (basename($file) !== '_loader.php') {
        require_once $file;
    }
}
