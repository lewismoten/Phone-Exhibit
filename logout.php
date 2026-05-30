<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
logout_user();
header('Location: /?login=1');
exit;
