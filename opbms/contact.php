<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/#contact');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('Your contact form expired. Please refresh the page and try again.', 'danger');
    redirect(BASE_URL . '/#contact');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');
if (strlen($name) < 2 || strlen($name) > 120 || strlen($email) > 180
    || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($message) < 10 || strlen($message) > 5000) {
    setFlash('Please provide a valid name, email address, and message of at least 10 characters.', 'danger');
    redirect(BASE_URL . '/#contact');
}

$recipient = getenv('OPBMS_CONTACT_EMAIL');
if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    setFlash('Contact email delivery is not configured yet. Set the OPBMS_CONTACT_EMAIL server environment variable and try again.', 'danger');
    redirect(BASE_URL . '/#contact');
}

$subject = 'OPBMS website enquiry';
$body = "Name: {$name}\nEmail: {$email}\n\nMessage:\n{$message}\n";
$headers = "From: OPBMS website <noreply@localhost>\r\n"
    . "Reply-To: {$email}\r\n"
    . "Content-Type: text/plain; charset=UTF-8";

if (mail($recipient, $subject, $body, $headers)) {
    setFlash('Your message has been sent. Thank you for getting in touch.', 'success');
} else {
    setFlash('We could not send your message right now. Please try again later.', 'danger');
}
redirect(BASE_URL . '/#contact');
