<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

function clean(string $key, int $max = 2000): string {
    $value = trim((string)($_POST[$key] ?? ''));
    $value = str_replace(["\r", "\0"], '', $value);
    return mb_substr($value, 0, $max);
}

$formType = clean('form_type', 30);
$returnPage = $formType === 'assessment' ? 'request.html' : 'contact.html';

// Honeypot: bots commonly fill hidden fields.
if (clean('website', 200) !== '') {
    header("Location: {$returnPage}?sent=1");
    exit;
}

$name = clean('name', 120);
$company = clean('company', 160);
$email = clean('email', 254);

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: {$returnPage}?error=1");
    exit;
}

// Prevent email-header injection.
if (preg_match('/[\r\n]/', $email)) {
    header("Location: {$returnPage}?error=1");
    exit;
}

$to = 'projects@digestercheck.com';
$subject = $formType === 'assessment'
    ? 'New DigesterCheck Assessment Request'
    : 'New DigesterCheck Website Inquiry';

$fields = [
    'Name' => $name,
    'Company / Facility' => $company,
    'Email' => $email,
    'Province' => clean('province', 100),
];

if ($formType === 'assessment') {
    $fields += [
        'Facility type' => clean('facility', 120),
        'Digester type / OEM' => clean('type', 160),
        'Year commissioned' => clean('year', 40),
        'Last cleanout' => clean('cleanout', 100),
        'Primary feedstock(s)' => clean('feedstock', 500),
        'Concern / observations' => clean('concern', 3000),
    ];
} else {
    $fields['Message'] = clean('message', 3000);
}

$body = "A new submission was received from digestercheck.com\n\n";
foreach ($fields as $label => $value) {
    $body .= $label . ": " . ($value !== '' ? $value : 'Not provided') . "\n";
}
$body .= "\nSubmitted: " . date('Y-m-d H:i:s T') . "\n";

$host = $_SERVER['HTTP_HOST'] ?? 'digestercheck.com';
$host = preg_replace('/[^a-z0-9.-]/i', '', $host);
$headers = [
    'From: DigesterCheck Website <website@digestercheck.com>',
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion(),
];

$sent = mail($to, $subject, $body, implode("\r\n", $headers));
header('Location: ' . $returnPage . ($sent ? '?sent=1' : '?error=1'));
exit;
?>