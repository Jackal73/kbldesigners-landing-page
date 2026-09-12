<?php
// KBL Designers™ Landing Page Email Processor for HostGator
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

// Read JSON input or POST fields
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    $data = $_POST;
}

$name        = isset($data['name']) ? trim($data['name']) : '';
$email       = isset($data['email']) ? trim($data['email']) : '';
$projectType = isset($data['projectType']) ? trim($data['projectType']) : '';
$message     = isset($data['message']) ? trim($data['message']) : '';

if (empty($name) || empty($email) || empty($projectType) || empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'All form fields are required.']);
    exit();
}

$to = 'skebel@kbldesigners.com';
$subject = "🚀 New Project Inquiry: {$projectType} from {$name}";

$body = "New Project Inquiry - KBL Designers™\n";
$body .= "====================================\n\n";
$body .= "Client Name:  {$name}\n";
$body .= "Client Email: {$email}\n";
$body .= "Project Type: {$projectType}\n\n";
$body .= "Project Details & Requirements:\n";
$body .= "------------------------------------\n";
$body .= "{$message}\n\n";
$body .= "Sent via kbldesigners.com\n";

$headers = "From: webmaster@kbldesigners.com\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

if (mail($to, $subject, $body, $headers)) {
    echo json_encode(['status' => 'success', 'message' => 'Inquiry sent successfully!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to dispatch email via HostGator mailer.']);
}
?>
