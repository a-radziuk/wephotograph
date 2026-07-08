<?php
session_start();

function redirectBack(string $status, string $reason = ''): void
{
    $_SESSION['contact_flash'] = [
        'status' => $status,
        'reason' => $reason,
    ];
    header('Location: contact.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('error', 'method');
}

$phone = trim($_POST['phone'] ?? '');
$name = trim(strip_tags($_POST['name'] ?? ''));
$email = trim($_POST['email'] ?? '');
$message = trim(strip_tags($_POST['message'] ?? ''));

if ($phone !== '') {
    redirectBack('error', 'invalid_submission');
}

if ($name === '' || $email === '') {
    redirectBack('error', 'validation');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectBack('error', 'validation');
}

if ($message === '') {
    redirectBack('error', 'validation');
}

if (strlen($message) > 5000) {
    redirectBack('error', 'validation');
}

$configPath = __DIR__ . '/config.php';
if (!is_readable($configPath)) {
    redirectBack('error', 'config');
}

$config = require $configPath;
$notificatorPath = rtrim($config['path'] ?? '', '/');
$telegramConfigName = $config['telegramConfigName'] ?? '';
$artisanPath = $notificatorPath . '/artisan';

if ($notificatorPath === '' || $telegramConfigName === '') {
    redirectBack('error', 'config');
}

if (!is_file($artisanPath)) {
    redirectBack('error', 'config');
}

$notificationMessage = "WePhotograph:\n" .
    "Name: {$name}\n" .
    "Email: {$email}\n\n" .
    $message;

$cmd = sprintf(
    'php %s telegram:message %s %s 2>&1',
    escapeshellarg($artisanPath),
    escapeshellarg($telegramConfigName),
    escapeshellarg($notificationMessage)
);

exec($cmd, $output, $exitCode);

if ($exitCode !== 0) {
    redirectBack('error', 'notificator');
}

redirectBack('success');
