<?php
/**
 * config/functions.php
 * ฟังก์ชันช่วยเหลือที่ใช้ร่วมกันทั้งระบบ
 */

require_once __DIR__ . '/db.php';

// ------------------------------------------------------------------
// ข้อ 8: ป้องกัน XSS ด้วย Output Encoding
// เรียกใช้ทุกครั้งก่อนแสดงข้อมูลที่มาจากผู้ใช้ลงบน HTML
// ------------------------------------------------------------------
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ------------------------------------------------------------------
// ข้อ 10: Security Logging
// บันทึกเหตุการณ์สำคัญลงตาราง security_logs
// ------------------------------------------------------------------
function log_security_event(mysqli $conn, ?int $userId, ?string $usernameAttempt, string $action, string $detail = ''): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $usernameAttempt = $usernameAttempt === null ? null : mb_substr($usernameAttempt, 0, 50);
    $detail = mb_substr($detail, 0, 255);
    $stmt = $conn->prepare(
        "INSERT INTO security_logs (user_id, username_attempt, action, detail, ip_address) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('issss', $userId, $usernameAttempt, $action, $detail, $ip);
    $stmt->execute();
    $stmt->close();
}

// ------------------------------------------------------------------
// ป้องกัน Brute-force: ล็อกบัญชีชั่วคราวเมื่อ login ผิดติดต่อกันหลายครั้ง
// นับจากตาราง security_logs (ไม่ต้องเพิ่มตารางใหม่) แยกทั้งตาม username และตาม IP
// เพื่อกันทั้งกรณี "เดารหัสของคนคนเดียว" และ "ยิง username หลายบัญชีจาก IP เดียว"
// ------------------------------------------------------------------
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_WINDOW_MINUTES', 15);

/**
 * คืนค่า true ถ้าบัญชี/IP นี้ต้องถูกล็อกชั่วคราว (login ผิดเกินกำหนดในช่วงเวลาล่าสุด)
 */
function is_login_locked_out(mysqli $conn, string $username, string $ip): bool {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS attempts FROM security_logs
         WHERE action = 'LOGIN_FAILED'
           AND (username_attempt = ? OR ip_address = ?)
           AND created_at > (NOW() - INTERVAL ? MINUTE)"
    );
    $window = LOGIN_LOCKOUT_WINDOW_MINUTES;
    $stmt->bind_param('ssi', $username, $ip, $window);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)$row['attempts'] >= LOGIN_MAX_ATTEMPTS;
}

// ------------------------------------------------------------------
// ข้อ 3: Authorization helpers
// ------------------------------------------------------------------
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . login_url());
        exit;
    }
}

function require_login_admin_path(): void {
    // ใช้ในโฟลเดอร์ admin/ ที่ path ไป login.php ต่างระดับ
    if (empty($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

function is_admin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function require_admin(): void {
    require_login_admin_path();
    if (!is_admin()) {
        abort_request(403, 'เฉพาะผู้ดูแลระบบเท่านั้นที่เข้าถึงหน้านี้ได้');
    }
}

// ------------------------------------------------------------------
// ข้อ 7: Input Validation
// ------------------------------------------------------------------
function validate_username(string $u): ?string {
    $u = trim($u);
    if ($u === '') return 'กรุณากรอกชื่อผู้ใช้';
    if (!preg_match('/^[a-zA-Z0-9_]{4,50}$/', $u)) {
        return 'ชื่อผู้ใช้ต้องเป็นตัวอักษร/ตัวเลข/ขีดล่าง ความยาว 4-50 ตัวอักษร';
    }
    return null;
}

function validate_email(string $email): ?string {
    $email = trim($email);
    if ($email === '') return 'กรุณากรอกอีเมล';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'รูปแบบอีเมลไม่ถูกต้อง';
    if (strlen($email) > 100) return 'อีเมลยาวเกินไป';
    return null;
}

function validate_password(string $p): ?string {
    if (strlen($p) < 8) return 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    if (strlen($p) > 72) return 'รหัสผ่านต้องไม่เกิน 72 ไบต์';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/[0-9]/', $p)) {
        return 'รหัสผ่านต้องมีทั้งตัวอักษรและตัวเลขอย่างน้อย 1 ตัว';
    }
    return null;
}

function validate_title(string $t): ?string {
    $t = trim($t);
    if ($t === '') return 'กรุณากรอกชื่อชีท';
    if (mb_strlen($t) > 200) return 'ชื่อชีทยาวเกินไป (สูงสุด 200 ตัวอักษร)';
    return null;
}

function validate_comment(string $c): ?string {
    $c = trim($c);
    if ($c === '') return 'กรุณากรอกข้อความคอมเมนต์';
    if (mb_strlen($c) > 1000) return 'คอมเมนต์ยาวเกินไป (สูงสุด 1000 ตัวอักษร)';
    return null;
}

// ------------------------------------------------------------------
// หาหรือสร้าง subject (INSERT IGNORE + SELECT ป้องกัน race condition)
// ------------------------------------------------------------------
function getOrCreateSubject(mysqli $conn, string $name): int {
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Subject name cannot be empty');
    }
    if (mb_strlen($name) > 150) {
        throw new InvalidArgumentException('Subject name too long (max 150 characters)');
    }

    // Try to insert (will ignore if duplicate on UNIQUE subject_name)
    $stmt = $conn->prepare("INSERT IGNORE INTO subjects (subject_name) VALUES (?)");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $stmt->close();

    // Fetch the subject_id (either existing or newly inserted)
    $stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_name = ?");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new RuntimeException('Failed to create or find subject: ' . $name);
    }

    return (int)$row['subject_id'];
}

// ------------------------------------------------------------------
// หมายเหตุท้ายเอกสาร: ตรวจสอบไฟล์อัปโหลด (Extension/MIME/ขนาด) + เปลี่ยนชื่อไฟล์
// ------------------------------------------------------------------
define('UPLOAD_MIME_MAP', [
    'pdf' => ['application/pdf'],
    'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/CDFV2'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
]);
define('ALLOWED_EXTENSIONS', array_keys(UPLOAD_MIME_MAP));
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

function validate_and_store_upload(array $file, string $uploadDir, ?string &$error): ?array {
    foreach (['name', 'tmp_name'] as $key) {
        if (!isset($file[$key]) || !is_string($file[$key])) {
            $error = 'ข้อมูลไฟล์ไม่ถูกต้อง กรุณาเลือกไฟล์ใหม่'; return null;
        }
    }
    if (!isset($file['error']) || !is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'อัปโหลดไม่สำเร็จ กรุณาเลือกไฟล์ขนาดไม่เกิน 10 MB'; return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $error = 'ข้อมูลไฟล์ไม่ถูกต้อง'; return null;
    }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size < 1 || $size > MAX_FILE_SIZE) {
        $error = 'ไฟล์ต้องมีข้อมูลและมีขนาดไม่เกิน 10 MB'; return null;
    }
    $originalName = basename(str_replace(chr(92), '/', $file['name']));
    if ($originalName === '' || mb_strlen($originalName) > 255 || preg_match('/[\x00-\x1F\x7F]/', $originalName)) {
        $error = 'ชื่อไฟล์ไม่ถูกต้องหรือยาวเกินไป'; return null;
    }
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(UPLOAD_MIME_MAP[$ext]) || !in_array($mime, UPLOAD_MIME_MAP[$ext], true)) {
        $error = 'ชนิดไฟล์หรือเนื้อหาไฟล์ไม่ตรงกับที่อนุญาต'; return null;
    }
    if (in_array($ext, ['docx', 'pptx'], true)) {
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            $error = 'เอกสาร Office ไม่ถูกต้อง'; return null;
        }
        $main = $ext === 'docx' ? 'word/document.xml' : 'ppt/presentation.xml';
        $valid = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName($main) !== false && $zip->numFiles <= 2000;
        $expandedSize = 0;
        for ($i = 0; $i < $zip->numFiles && $valid; $i++) {
            $entry = $zip->statIndex($i);
            $expandedSize += $entry['size'];
            if ($expandedSize > 100 * 1024 * 1024 || preg_match('~(?:^|/)\.\.(?:/|$)|vbaProject\.bin$~i', $entry['name'])) $valid = false;
        }
        $zip->close();
        if (!$valid) { $error = 'เอกสารไม่ตรงชนิดไฟล์หรือมีเนื้อหาที่ไม่อนุญาต'; return null; }
    }
    if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        $image = @getimagesize($file['tmp_name']);
        if (!$image || $image['mime'] !== $mime) { $error = 'ไฟล์รูปภาพไม่ถูกต้อง'; return null; }
    }
    $newFileName = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], rtrim($uploadDir, '/') . '/' . $newFileName)) {
        $error = 'ไม่สามารถบันทึกไฟล์ได้ กรุณาลองใหม่'; return null;
    }
    return ['file_name' => $newFileName, 'original_file_name' => $originalName, 'file_size' => $size, 'file_type' => $ext];
}

function flash(string $key, ?string $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return;
    }
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

// Reject arrays and invalid encodings before passing input into typed helpers.
function input_text(array $source, string $key, string $default = ''): string {
    $value = $source[$key] ?? $default;
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || str_contains($value, chr(0))) {
        abort_request(400, 'ข้อมูลที่ส่งมาไม่ถูกต้อง กรุณาตรวจสอบแล้วลองอีกครั้ง');
    }
    return $value;
}
function input_id(array $source, string $key, int $default = 0): int {
    if (!array_key_exists($key, $source)) return $default;
    $value = $source[$key];
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,9}$/D', $value) || (float)$value > 2147483647) {
        abort_request(400, 'รหัสข้อมูลไม่ถูกต้อง');
    }
    return (int)$value;
}
function file_badge(string $type): string {
    return in_array($type, ['jpg', 'jpeg', 'png'], true) ? 'image' : (in_array($type, ['doc', 'docx'], true) ? 'word' : (in_array($type, ['ppt', 'pptx'], true) ? 'slides' : 'pdf'));
}
function format_bytes(int $bytes): string {
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : number_format($bytes / 1024, 1) . ' KB';
}

function icon(string $name): string {
    $paths = [
        'book' => '<path d="M4 4h6a3 3 0 0 1 3 3v14a4 4 0 0 0-4-3H4z"/><path d="M20 4h-4a3 3 0 0 0-3 3v14a4 4 0 0 1 4-3h3z"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>',
        'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'upload' => '<path d="M12 16V3m-5 5 5-5 5 5M4 16v5h16v-5"/>',
        'download' => '<path d="M12 3v13m-5-5 5 5 5-5M4 17v4h16v-4"/>',
        'shield' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6z"/><path d="m8 12 3 3 5-6"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'file' => '<path d="M5 3h9l5 5v13H5zM14 3v5h5M9 12h6m-6 4h6"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'comment' => '<path d="M4 4h16v13H9l-5 4zM8 9h8m-8 4h5"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'logout' => '<path d="M9 4H4v16h5m2-8h10m-4-4 4 4-4 4"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? $paths['file']) . '</svg>';
}

// Refresh on every request, including public pages showing account controls.
if (!empty($_SESSION['user_id'])) {
    $authConn = getDbConnection();
    $authStmt = $authConn->prepare('SELECT username, full_name, role, status FROM users WHERE user_id = ?');
    $authStmt->bind_param('i', $_SESSION['user_id']);
    $authStmt->execute();
    $currentUser = $authStmt->get_result()->fetch_assoc();
    $authStmt->close(); $authConn->close();
    if (!$currentUser || $currentUser['status'] !== 'active') {
        destroy_user_session();
        header('Location: ' . login_url() . '?revoked=1'); exit;
    }
    if (($_SESSION['role'] ?? '') !== $currentUser['role']) {
        session_regenerate_id(true);
        unset($_SESSION['csrf_token']);
    }
    foreach (['username', 'full_name', 'role'] as $key) $_SESSION[$key] = $currentUser[$key];
}
