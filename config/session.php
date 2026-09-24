<?php
/**
 * config/session.php
 * ============================================================
 * ข้อ 4: Session Security
 * - session_regenerate_id() หลัง Login (ทำในไฟล์ login.php ตอน login สำเร็จ)
 * - กำหนด Session Timeout
 * - ตั้งค่า Session Cookie อย่างเหมาะสม เช่น HttpOnly และ SameSite
 * ============================================================
 * ไฟล์นี้ต้องถูก include ("require_once") เป็นบรรทัดแรกสุด
 * ของทุกหน้าที่ใช้ session ก่อนมีการ output ใด ๆ ออกไปยัง browser
 */

// ป้องกันการ start session ซ้ำ
if (session_status() === PHP_SESSION_NONE) {

    // ตั้งค่า Cookie ให้ปลอดภัยก่อน start session
    session_set_cookie_params([
        'lifetime' => 0,           // หมดอายุเมื่อปิด browser
        'path'     => '/',
        'domain'   => '',          // ใช้ domain ปัจจุบัน
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', // true เมื่อใช้ HTTPS
        'httponly' => true,        // JavaScript อ่าน cookie นี้ไม่ได้ -> กัน XSS ขโมย session
        'samesite' => 'Strict',    // กัน CSRF ระดับ cookie
    ]);

    session_start();
}

// ---------------------------------------------------------
// Session Timeout: หากไม่มีการใช้งานเกิน 30 นาที ให้ตัดออกจากระบบอัตโนมัติ
// ---------------------------------------------------------
define('SESSION_TIMEOUT_SECONDS', 30 * 60); // 30 นาที

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {

        // Session หมดอายุ -> เคลียร์ทิ้งทั้งหมด
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: ' . (strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../login.php' : 'login.php') . '?timeout=1');
        exit;
    }
    // อัปเดตเวลาล่าสุดที่มีการใช้งาน
    $_SESSION['last_activity'] = time();
}
