<?php
declare(strict_types=1);

// Minimal SMTP sender for Brevo (STARTTLS, port 587).
// Loads config from vars.env (if present), then environment variables, then defaults.

function load_env_file(string $path): void {
  if (!is_file($path) || !is_readable($path)) return;
  $lines = file($path, FILE_IGNORE_NEW_LINES);
  if ($lines === false) return;

  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) continue;

    // Allow optional "export KEY=VALUE"
    if (str_starts_with($line, 'export ')) {
      $line = trim(substr($line, 7));
    }

    $pos = strpos($line, '=');
    if ($pos === false) continue;

    $key = trim(substr($line, 0, $pos));
    $val = trim(substr($line, $pos + 1));
    if ($key === '') continue;

    // Strip surrounding quotes if present.
    if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
        (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
      $val = substr($val, 1, -1);
    }

    // Don't override real environment if already set.
    $existing = getenv($key);
    if ($existing !== false && $existing !== null && $existing !== '') continue;

    putenv("{$key}={$val}");
    $_ENV[$key] = $val;
  }
}

function env(string $key, string $default = ''): string {
  $val = getenv($key);
  if ($val === false || $val === null || $val === '') return $default;
  return (string)$val;
}

function sanitize_line(string $s): string {
  // Prevent header / SMTP injection by stripping CRLF.
  return trim(str_replace(["\r", "\n"], ' ', $s));
}

function read_smtp($fp): string {
  $data = '';
  while (!feof($fp)) {
    $line = fgets($fp, 515);
    if ($line === false) break;
    $data .= $line;
    // Multi-line responses have a hyphen after the status code (e.g. "250-").
    if (preg_match('/^\d{3}\s/', $line)) break;
  }
  return $data;
}

function expect_code(string $resp, int $code): void {
  if (!preg_match('/^' . $code . '\b/m', $resp)) {
    throw new RuntimeException("SMTP error, expected {$code}, got: " . trim($resp));
  }
}

function cmd($fp, string $command, int $expect): void {
  fwrite($fp, $command . "\r\n");
  $resp = read_smtp($fp);
  expect_code($resp, $expect);
}

function smtp_send(array $cfg, string $to, string $subject, string $textBody, string $replyToEmail, string $replyToName): void {
  $host = $cfg['host'];
  $port = (int)$cfg['port'];

  $fp = stream_socket_client(
    "tcp://{$host}:{$port}",
    $errno,
    $errstr,
    15,
    STREAM_CLIENT_CONNECT
  );
  if (!$fp) {
    throw new RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
  }

  stream_set_timeout($fp, 15);

  $greet = read_smtp($fp);
  expect_code($greet, 220);

  $local = 'givistudio';
  cmd($fp, "EHLO {$local}", 250);

  cmd($fp, "STARTTLS", 220);
  if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
    throw new RuntimeException("Failed to start TLS.");
  }

  cmd($fp, "EHLO {$local}", 250);

  // AUTH LOGIN
  cmd($fp, "AUTH LOGIN", 334);
  cmd($fp, base64_encode($cfg['username']), 334);
  cmd($fp, base64_encode($cfg['password']), 235);

  $from = sanitize_line($cfg['from_address']);
  $fromName = sanitize_line($cfg['from_name']);

  cmd($fp, "MAIL FROM:<{$from}>", 250);
  cmd($fp, "RCPT TO:<" . sanitize_line($to) . ">", 250);
  cmd($fp, "DATA", 354);

  $date = date('r');
  $msgId = '<' . bin2hex(random_bytes(12)) . '@givistudio.local>';

  $headers = [];
  $headers[] = "Date: {$date}";
  $headers[] = "Message-ID: {$msgId}";
  $headers[] = "From: " . ($fromName !== '' ? "{$fromName} <{$from}>" : "<{$from}>");
  $headers[] = "To: <" . sanitize_line($to) . ">";
  $headers[] = "Subject: " . sanitize_line($subject);
  $headers[] = "MIME-Version: 1.0";
  $headers[] = "Content-Type: text/plain; charset=UTF-8";
  if ($replyToEmail !== '') {
    $rtEmail = sanitize_line($replyToEmail);
    $rtName = sanitize_line($replyToName);
    $headers[] = "Reply-To: " . ($rtName !== '' ? "{$rtName} <{$rtEmail}>" : "<{$rtEmail}>");
  }

  // Dot-stuffing per SMTP rules (lines starting with "." must be prefixed).
  $bodyLines = preg_split("/\r\n|\n|\r/", $textBody);
  $safeBody = '';
  foreach ($bodyLines as $line) {
    if (str_starts_with($line, '.')) $line = '.' . $line;
    $safeBody .= $line . "\r\n";
  }

  $raw = implode("\r\n", $headers) . "\r\n\r\n" . $safeBody . "\r\n.\r\n";
  fwrite($fp, $raw);
  $resp = read_smtp($fp);
  expect_code($resp, 250);

  cmd($fp, "QUIT", 221);
  fclose($fp);
}

function respond(int $status, string $message): void {
  // If request expects JSON, return JSON; otherwise redirect back to the site.
  $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
  $wantsJson = stripos($accept, 'application/json') !== false;

  if ($wantsJson) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $status >= 200 && $status < 300, 'message' => $message], JSON_UNESCAPED_SLASHES);
    exit;
  }

  // Redirect to the contact page with a small query flag (non-JS fallback).
  $qs = $status >= 200 && $status < 300 ? 'sent=1' : 'error=1';
  header("Location: contact.html?{$qs}", true, 303);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  respond(405, 'Method not allowed.');
}

$name = sanitize_line((string)($_POST['name'] ?? ''));
$email = sanitize_line((string)($_POST['email'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($name === '' || $email === '' || $message === '') {
  respond(400, 'Missing required fields.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  respond(400, 'Invalid email address.');
}

// Load .env next to this file (if present)
load_env_file(__DIR__ . DIRECTORY_SEPARATOR . '.env');

$cfg = [
  'host' => env('MAIL_HOST', 'smtp-relay.brevo.com'),
  'port' => env('MAIL_PORT', '587'),
  'username' => env('MAIL_USERNAME', 'user@brevo.com'),
  'password' => env('MAIL_PASSWORD', 'password'),
  'from_address' => trim(env('MAIL_FROM_ADDRESS', 'alexey.radyuk@gmail.com'), "\"' "),
  'from_name' => trim(env('MAIL_FROM_NAME', 'Givi Studio'), "\"' "),
];

$to = 'aliakseiradziuk1@gmail.com';
$subject = 'WePhotograph: message from the website';

$body =
  "New message from WePhotograph website\n" .
  "----------------------------------\n" .
  "Name: {$name}\n" .
  "Email: {$email}\n\n" .
  "Message:\n{$message}\n";

try {
  smtp_send($cfg, $to, $subject, $body, $email, $name);
  respond(200, 'Message sent. We will get back to you soon.');
} catch (Throwable $e) {
  respond(500, 'Could not send your message. Please try again later.');
}

