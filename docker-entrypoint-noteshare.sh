#!/bin/sh
# ============================================================
# แก้ปัญหา "อัปโหลดไฟล์ไม่ได้ / Permission denied" ตอนรันด้วย Docker
# ------------------------------------------------------------
# โฟลเดอร์ uploads/ ถูก bind mount มาจากเครื่อง host สิทธิ์ของไฟล์จึงมาจาก host
# ไม่ใช่จาก chown ตอน build image ดังนั้นต้องตั้งสิทธิ์ให้ www-data ใหม่ทุกครั้งที่ container เริ่ม
# ============================================================

UPLOAD_DIR=/var/www/html/uploads

if [ -d "$UPLOAD_DIR" ]; then
    chown -R www-data:www-data "$UPLOAD_DIR" 2>/dev/null || true
    chmod -R 775 "$UPLOAD_DIR" 2>/dev/null || true

    if su -s /bin/sh www-data -c "test -w $UPLOAD_DIR"; then
        echo "[noteshare] uploads/ เขียนได้ปกติ"
    else
        echo "[noteshare] คำเตือน: www-data เขียนลง uploads/ ไม่ได้"
        echo "[noteshare] ให้รันคำสั่งนี้บนเครื่อง host แล้ว restart: chmod -R 777 uploads"
    fi
fi

exec "$@"
