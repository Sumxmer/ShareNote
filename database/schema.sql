-- =====================================================
-- Database: sheetapp_db
-- ระบบแชร์โน้ต/ชีทสรุปสำหรับติวสอบ
-- CP423324 Database and Web Security
-- =====================================================

CREATE DATABASE IF NOT EXISTS sheetapp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sheetapp_db;

-- =====================================================
-- 1. ตาราง users
-- =====================================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,   -- เก็บด้วย password_hash() เท่านั้น ห้ามเก็บ plaintext
    full_name VARCHAR(100) NOT NULL,
    role ENUM('user','admin') NOT NULL DEFAULT 'user',
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 2. ตาราง subjects (รายวิชา / หมวดหมู่ชีท)
-- =====================================================
CREATE TABLE subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(150) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 3. ตาราง notes (ชีทสรุป)
-- =====================================================
CREATE TABLE notes (
    note_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_name VARCHAR(255) NOT NULL,        -- ชื่อไฟล์จริงบน disk (สุ่มใหม่ ไม่ใช้ชื่อเดิมของผู้ใช้)
    original_file_name VARCHAR(255) NOT NULL, -- ชื่อไฟล์เดิมไว้แสดงผล (ต้อง encode ตอนแสดง)
    file_size INT NOT NULL,                 -- bytes
    file_type VARCHAR(50) NOT NULL,
    download_count INT NOT NULL DEFAULT 0,
    status ENUM('active','removed') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE RESTRICT,
    FULLTEXT KEY ft_title_desc (title, description)
) ENGINE=InnoDB;

-- =====================================================
-- 4. ตาราง comments
-- =====================================================
CREATE TABLE comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(note_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 5. ตาราง download_logs
-- =====================================================
CREATE TABLE download_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(note_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 6. ตาราง security_logs (ข้อ 10: Security Logging)
-- =====================================================
CREATE TABLE security_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,                        -- NULL ได้ กรณี login ไม่สำเร็จและไม่รู้ user
    username_attempt VARCHAR(50) NULL,        -- เก็บ username ที่ใช้พยายาม login (สำหรับกรณีไม่สำเร็จ)
    action VARCHAR(50) NOT NULL,              -- เช่น LOGIN_SUCCESS, LOGIN_FAILED, NOTE_CREATE, NOTE_UPDATE, NOTE_DELETE, FILE_UPLOAD
    detail VARCHAR(255),
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- ข้อมูลตัวอย่าง (Seed data)
-- =====================================================
INSERT INTO subjects (subject_name) VALUES
('Database and Web Security'),
('Data Structures and Algorithms'),
('Computer Networks'),
('ภาษาไทยเพื่อการสื่อสาร');

-- หมายเหตุ: บัญชี admin เริ่มต้นให้สร้างผ่านหน้า register.php แล้วไปแก้ role ใน DB เป็น 'admin'
-- หรือรัน insert เอง เช่น (ตัวอย่าง hash เป็นของคำว่า "Admin@1234" -- ควรสร้างใหม่ด้วย password_hash() จริงในระบบ)
-- INSERT INTO users (username, email, password_hash, full_name, role) VALUES
-- ('admin', 'admin@example.com', '<ใส่ผลลัพธ์จาก password_hash() จริง>', 'System Admin', 'admin');
