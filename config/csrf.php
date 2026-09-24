<?php
/**
 * config/csrf.php
 * ============================================================
 * ข้อ 9: CSRF Protection
 * ฟังก์ชันสำคัญ (Delete, Update, Create) ต้องใช้ POST และมี CSRF Token
 * ============================================================
 * ต้อง require_once ไฟล์นี้หลังจาก session.php ถูก include แล้วเท่านั้น
 */

/**
 * สร้าง CSRF token ใหม่ (ถ้ายังไม่มีใน session) แล้วคืนค่ากลับมา
 * ใช้ฝังในฟอร์มแบบ: <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * ตรวจสอบ CSRF token ที่ส่งมาจากฟอร์ม (เรียกใช้ทุกครั้งก่อนประมวลผล POST ที่มีผลต่อข้อมูล)
 * ถ้าไม่ถูกต้อง -> หยุดการทำงานทันที
 */
function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('คำขอไม่ถูกต้อง (CSRF token ไม่ตรงกันหรือหมดอายุ) กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}
