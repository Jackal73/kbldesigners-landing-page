<?php
// KBL Designers™ Landing Page Email Processor for HostGator
header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

$origin = isset($_SERVER['HTTP_ORIGIN']) ? strtolower(rtrim($_SERVER['HTTP_ORIGIN'], '/')) : '';
$allowedOrigins = ['https://kbldesigners.com', 'https://www.kbldesigners.com'];
if ($origin !== '' && !in_array($origin, $allowedOrigins, true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Request origin is not allowed.']);
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
$website     = isset($data['website']) ? trim($data['website']) : '';
$startedAt   = isset($data['startedAt']) ? (int) $data['startedAt'] : 0;

// Bots commonly fill hidden fields or submit immediately after loading the page.
if ($website !== '') {
    echo json_encode(['status' => 'success', 'message' => 'Inquiry received.']);
    exit();
}

$nowMilliseconds = (int) round(microtime(true) * 1000);
if ($startedAt <= 0 || ($nowMilliseconds - $startedAt) < 2500 || ($nowMilliseconds - $startedAt) > 7200000) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Please reload the page and try again.']);
    exit();
}

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

$allowedProjectTypes = [
    'Custom Web Application',
    'Enterprise PWA',
    'Backend Security Audit',
    'Full Website Build',
];
if (!in_array($projectType, $allowedProjectTypes, true)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Please select a valid project type.']);
    exit();
}

if (strlen($name) > 100 || strlen($email) > 254 || strlen($projectType) > 100 || strlen($message) > 5000) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'One or more form fields are too long.']);
    exit();
}

$clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
$rateKey = hash('sha256', $clientIp);
$rateFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kbl-inquiry-' . $rateKey . '.json';
$rateHandle = fopen($rateFile, 'c+');

if ($rateHandle === false || !flock($rateHandle, LOCK_EX)) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Inquiry service is temporarily unavailable.']);
    exit();
}

$storedRateData = stream_get_contents($rateHandle);
$rateData = $storedRateData ? json_decode($storedRateData, true) : [];
$now = time();
$recentAttempts = array_values(array_filter(
    isset($rateData['attempts']) && is_array($rateData['attempts']) ? $rateData['attempts'] : [],
    static function ($timestamp) use ($now) {
        return is_int($timestamp) && ($now - $timestamp) < 900;
    }
));

if (count($recentAttempts) >= 3) {
    flock($rateHandle, LOCK_UN);
    fclose($rateHandle);
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many inquiries. Please try again later.']);
    exit();
}

$globalRateFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kbl-inquiry-global.json';
$globalRateHandle = fopen($globalRateFile, 'c+');
if ($globalRateHandle === false || !flock($globalRateHandle, LOCK_EX)) {
    flock($rateHandle, LOCK_UN);
    fclose($rateHandle);
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Inquiry service is temporarily unavailable.']);
    exit();
}

$storedGlobalData = stream_get_contents($globalRateHandle);
$globalAttempts = $storedGlobalData ? json_decode($storedGlobalData, true) : [];
$globalAttempts = array_values(array_filter(
    is_array($globalAttempts) ? $globalAttempts : [],
    static function ($timestamp) use ($now) {
        return is_int($timestamp) && ($now - $timestamp) < 3600;
    }
));

if (count($globalAttempts) >= 8) {
    flock($globalRateHandle, LOCK_UN);
    fclose($globalRateHandle);
    flock($rateHandle, LOCK_UN);
    fclose($rateHandle);
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Inquiry capacity reached. Please try again later.']);
    exit();
}

$payloadFingerprint = hash('sha256', strtolower($email) . '|' . strtolower($projectType) . '|' . $message);
$lastFingerprint = isset($rateData['fingerprint']) ? $rateData['fingerprint'] : '';
$lastSentAt = isset($rateData['lastSentAt']) ? (int) $rateData['lastSentAt'] : 0;

if ($payloadFingerprint === $lastFingerprint && ($now - $lastSentAt) < 1800) {
    flock($globalRateHandle, LOCK_UN);
    fclose($globalRateHandle);
    flock($rateHandle, LOCK_UN);
    fclose($rateHandle);
    echo json_encode(['status' => 'success', 'message' => 'Inquiry already received.']);
    exit();
}

$recentAttempts[] = $now;
$globalAttempts[] = $now;
$rateData = [
    'attempts' => $recentAttempts,
    'fingerprint' => $payloadFingerprint,
    'lastSentAt' => $lastSentAt,
];
ftruncate($rateHandle, 0);
rewind($rateHandle);
fwrite($rateHandle, json_encode($rateData));
fflush($rateHandle);
ftruncate($globalRateHandle, 0);
rewind($globalRateHandle);
fwrite($globalRateHandle, json_encode($globalAttempts));
fflush($globalRateHandle);
flock($globalRateHandle, LOCK_UN);
fclose($globalRateHandle);

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
    $rateData['lastSentAt'] = $now;
    ftruncate($rateHandle, 0);
    rewind($rateHandle);
    fwrite($rateHandle, json_encode($rateData));
    fflush($rateHandle);
    flock($rateHandle, LOCK_UN);
    fclose($rateHandle);
    echo json_encode(['status' => 'success', 'message' => 'Inquiry sent successfully!']);
} else {
    flock($rateHandle, LOCK_UN);
    fclose($rateHandle);
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'Failed to dispatch email via HostGator mailer.']);
}
?>
