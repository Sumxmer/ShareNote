-- =====================================================
-- ข้อ 5: Database Security — Least Privilege
-- =====================================================
-- Docker สร้าง user 'sheetapp_user' ให้อัตโนมัติจากค่า MYSQL_USER (สิทธิ์ ALL บน sheetapp_db)
-- ไฟล์นี้รันต่อทันทีหลัง schema.sql เพื่อ "ลดสิทธิ์" ให้เหลือเท่าที่แอปใช้จริงเท่านั้น
-- (แอปไม่ต้องใช้ DDL เลย จึงไม่ควรมี CREATE / DROP / ALTER / GRANT)
-- =====================================================

-- Reset all scopes, so Docker's escaped underscore grant is removed as well.
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'sheetapp_user'@'%';

GRANT SELECT, INSERT, UPDATE ON sheetapp_db.users TO 'sheetapp_user'@'%';
GRANT SELECT, INSERT ON sheetapp_db.subjects TO 'sheetapp_user'@'%';
GRANT SELECT, INSERT, UPDATE ON sheetapp_db.notes TO 'sheetapp_user'@'%';
GRANT SELECT, INSERT, DELETE ON sheetapp_db.comments TO 'sheetapp_user'@'%';
GRANT SELECT, INSERT ON sheetapp_db.download_logs TO 'sheetapp_user'@'%';
GRANT SELECT, INSERT ON sheetapp_db.security_logs TO 'sheetapp_user'@'%';

FLUSH PRIVILEGES;
