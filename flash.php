<?php
session_start();

$flash = $_SESSION['contact_flash'] ?? null;
unset($_SESSION['contact_flash']);

header('Content-Type: application/json');
echo json_encode($flash);
