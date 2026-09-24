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
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// ------------------------------------------------------------------
// ข้อ 10: Security Logging
// บันทึกเหตุการณ์สำคัญลงตาราง security_logs
// ------------------------------------------------------------------
function log_security_event(mysqli $conn, ?int $userId, ?string $usernameAttempt, string $action, string $detail = ''): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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
        header('Location: login.php');
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
        http_response_code(403);
        die('เฉพาะผู้ดูแลระบบเท่านั้นที่เข้าถึงหน้านี้ได้');
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
    if (strlen($p) > 100) return 'รหัสผ่านยาวเกินไป';
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
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png']);
define('ALLOWED_MIME_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'image/jpeg',
    'image/png',
]);
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB

/**
 * ตรวจสอบไฟล์อัปโหลดและคืนชื่อไฟล์ใหม่แบบสุ่มถ้าผ่าน (หรือคืน null พร้อม error message ถ้าไม่ผ่าน)
 */
function validate_and_store_upload(array $file, string $uploadDir, ?string &$error): ?array {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์';
        return null;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        $error = 'ขนาดไฟล์ต้องไม่เกิน 10MB';
        return null;
    }

    $originalName = $file['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        $error = 'ชนิดไฟล์ไม่ได้รับอนุญาต (รองรับ: ' . implode(', ', ALLOWED_EXTENSIONS) . ')';
        return null;
    }

    // ตรวจสอบ MIME type จริงของไฟล์ (ไม่เชื่อแค่ extension ที่ผู้ใช้ตั้งชื่อมา)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_MIME_TYPES, true)) {
        $error = 'ชนิดไฟล์ไม่ตรงกับที่อนุญาต (ตรวจสอบ MIME type ไม่ผ่าน)';
        return null;
    }

    // เปลี่ยนชื่อไฟล์ใหม่แบบสุ่มก่อนจัดเก็บ ป้องกัน path traversal / overwrite / เดาชื่อไฟล์
    $newFileName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = rtrim($uploadDir, '/') . '/' . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $error = 'ไม่สามารถบันทึกไฟล์ได้ กรุณาลองใหม่';
        return null;
    }

    return [
        'file_name'          => $newFileName,
        'original_file_name' => $originalName,
        'file_size'          => $file['size'],
        'file_type'          => $ext,
    ];
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
