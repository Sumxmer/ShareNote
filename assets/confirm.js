/**
 * assets/confirm.js
 * ทำงานแทน inline onsubmit="return confirm(...)" เดิม
 * เพื่อให้ตั้ง Content-Security-Policy แบบ script-src 'self' (ไม่ต้องเปิด unsafe-inline) ได้
 *
 * วิธีใช้: เติม data-confirm="ข้อความที่จะถาม" ให้กับ <form> ที่ต้องการ
 * เช่น <form ... data-confirm="ยืนยันการลบ?">
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm') || 'ยืนยันการทำรายการนี้?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
});
