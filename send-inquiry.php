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
    http_response_code(405);
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
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'All form fields are required.']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Please provide a valid email address.']);
    exit();
}

$to = 'skebel@kbldesigners.com';
$safeName = str_replace(["\r", "\n"], '', $name);
$safeProjectType = str_replace(["\r", "\n"], '', $projectType);
$subject = "New Project Inquiry: {$safeProjectType} from {$safeName}";

$htmlName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$htmlEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$htmlProjectType = htmlspecialchars($projectType, ENT_QUOTES, 'UTF-8');
$htmlMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

$body = <<<HTML
<!doctype html>
<html lang="en">
    <body style="margin:0; padding:24px 12px; background:#07090e; color:#f8fafc; font-family:Arial, Helvetica, sans-serif;">
        <div style="max-width:600px; margin:0 auto; background:#0d1421; border:1px solid #38bdf8; border-radius:12px; overflow:hidden;">
            <div style="padding:24px 24px 18px; border-bottom:1px solid #223148;">
                <div style="margin:0 0 6px; color:#38bdf8; font-size:12px; font-weight:bold; letter-spacing:1.4px; text-transform:uppercase;">KBL Designers</div>
                <h1 style="margin:0; color:#f8fafc; font-size:24px; line-height:1.25;">New Project Inquiry</h1>
            </div>
            <div style="padding:24px;">
                <table role="presentation" style="width:100%; border-collapse:collapse; font-size:14px;">
                    <tr>
                        <td style="padding:9px 0; color:#94a3b8; font-weight:bold; width:132px; vertical-align:top;">Client name</td>
                        <td style="padding:9px 0; color:#f8fafc;">{$htmlName}</td>
                    </tr>
                    <tr>
                        <td style="padding:9px 0; color:#94a3b8; font-weight:bold; vertical-align:top;">Email</td>
                        <td style="padding:9px 0;"><a href="mailto:{$htmlEmail}" style="color:#38bdf8; text-decoration:none;">{$htmlEmail}</a></td>
                    </tr>
                    <tr>
                        <td style="padding:9px 0; color:#94a3b8; font-weight:bold; vertical-align:top;">Project focus</td>
                        <td style="padding:9px 0; color:#10b981; font-weight:bold;">{$htmlProjectType}</td>
                    </tr>
                </table>
                <div style="margin-top:22px; padding:16px; background:#121b2b; border:1px solid #223148; border-radius:8px;">
                    <div style="margin-bottom:8px; color:#94a3b8; font-size:11px; font-weight:bold; letter-spacing:1px; text-transform:uppercase;">Project details and requirements</div>
                    <div style="color:#f8fafc; font-size:14px; line-height:1.65;">{$htmlMessage}</div>
                </div>
            </div>
            <div style="padding:14px 24px; border-top:1px solid #223148; color:#64748b; font-size:11px; text-align:center;">Sent from kbldesigners.com</div>
        </div>
    </body>
</html>
HTML;

$headers = [
    'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
    'From: KBL Designers Website <webmaster@kbldesigners.com>',
    "Reply-To: {$email}",
    'X-Mailer: PHP/' . phpversion(),
];

if (mail($to, $subject, $body, implode("\r\n", $headers))) {
    echo json_encode(['status' => 'success', 'message' => 'Inquiry sent successfully!']);
} else {
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'Failed to dispatch email via HostGator mailer.']);
}
?>
