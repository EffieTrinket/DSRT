<?php
session_start();

// Only allow logged-in Admins to view ID images
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    exit('Access denied.');
}

$file = $_GET['file'] ?? '';

// Sanitize: strip any directory traversal attempts
$file = basename($file);

// Only allow image files
$allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if (!in_array($ext, $allowed_ext)) {
    http_response_code(400);
    exit('Invalid file type.');
}

$full_path = __DIR__ . '/../uploads/ids/' . $file;

if (!file_exists($full_path)) {
    http_response_code(404);
    exit('File not found.');
}

// Serve the image with the correct content type
$mime_types = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];

header('Content-Type: ' . $mime_types[$ext]);
header('Content-Length: ' . filesize($full_path));
readfile($full_path);
exit;
