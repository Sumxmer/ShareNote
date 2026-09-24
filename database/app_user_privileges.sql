-- =====================================================
-- ข้อ 5: Database Security — Least Privilege
-- =====================================================
-- Docker สร้าง user 'sheetapp_user' ให้อัตโนมัติจากค่า MYSQL_USER (สิทธิ์ ALL บน sheetapp_db)
-- ไฟล์นี้รันต่อทันทีหลัง schema.sql เพื่อ "ลดสิทธิ์" ให้เหลือเท่าที่แอปใช้จริงเท่านั้น
-- (แอปไม่ต้องใช้ DDL เลย จึงไม่ควรมี CREATE / DROP / ALTER / GRANT)
-- =====================================================

REVOKE ALL PRIVILEGES ON sheetapp_db.* FROM 'sheetapp_user'@'%';

GRANT SELECT, INSERT, UPDATE, DELETE ON sheetapp_db.* TO 'sheetapp_user'@'%';

FLUSH PRIVILEGES;
