<?php
/**
 * config/db.php
 * ============================================================
 * ข้อ 5: Database Security
 * - ห้ามเชื่อมต่อฐานข้อมูลด้วย root
 * - ใช้ Database User เฉพาะของระบบ ที่ได้รับสิทธิ์แบบ Least Privilege
 *   (เฉพาะ SELECT, INSERT, UPDATE, DELETE บน sheetapp_db เท่านั้น)
 * ============================================================
 *
 * ก่อนใช้งานจริง ให้สร้าง user นี้ใน MySQL/MariaDB ก่อน เช่น:
 *
 *   CREATE USER 'sheetapp_user'@'localhost' IDENTIFIED BY 'ChangeThisPassword!123';
 *   GRANT SELECT, INSERT, UPDATE, DELETE ON sheetapp_db.* TO 'sheetapp_user'@'localhost';
 *   FLUSH PRIVILEGES;
 *
 * ห้ามใช้ root และห้ามให้สิทธิ์ GRANT ALL / DROP / CREATE USER แก่ user นี้
 *
 * หมายเหตุ: ถ้ารันด้วย Docker (docker compose up -d) ขั้นตอนข้างบนไม่ต้องทำเอง
 * user ตัวนี้ถูกสร้าง + จำกัดสิทธิ์ให้แล้วโดย database/app_user_privileges.sql
 */

// ตั้งค่าการเชื่อมต่อ - อ่านจาก environment variable ก่อน (ตามที่ comment ข้างบนแนะนำ)
// - รันด้วย Docker (docker-compose.yml) จะได้ DB_HOST = 'db' อัตโนมัติ ไม่ต้องแก้ไฟล์นี้
// - รันบน XAMPP/Laragon ที่ไม่ได้ตั้ง env จะ fallback ไปใช้ค่า default ด้านล่าง
//   (ถ้าเปลี่ยนรหัสผ่านจริง ให้แก้ที่ env หรือที่ค่า default ในบรรทัดเหล่านี้)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_USER', getenv('DB_USER') ?: 'sheetapp_user');       // ไม่ใช่ root
define('DB_PASS', getenv('DB_PASS') ?: 'ChangeThisPassword!123');
define('DB_NAME', getenv('DB_NAME') ?: 'sheetapp_db');

// ปิดการแสดง error ของ mysqli แบบ verbose ไปหน้าเว็บ (ข้อ 7: Error Handling)
mysqli_report(MYSQLI_REPORT_OFF);

function getDbConnection(): mysqli {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    if ($conn->connect_error) {
        // ไม่แสดงรายละเอียด error จริงของ DB ให้ผู้ใช้เห็น (ข้อ 7)
        error_log('DB Connection Error: ' . $conn->connect_error);
        die('ขณะนี้ระบบไม่สามารถให้บริการได้ กรุณาลองใหม่ภายหลัง');
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}
