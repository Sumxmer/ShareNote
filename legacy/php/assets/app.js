document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm || 'ยืนยันการทำรายการนี้?')) event.preventDefault();
        });
    });
    document.querySelectorAll('select[data-submit]').forEach(function (select) {
        select.addEventListener('change', function () { select.form.requestSubmit(); });
    });
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.dataset.passwordToggle);
            var visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', visible ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
        });
    });
    document.querySelectorAll('.dropzone').forEach(function (zone) {
        var input = zone.querySelector('input[type=file]');
        var output = zone.querySelector('[data-file-label]');
        function update() {
            var file = input.files[0];
            output.textContent = file ? file.name + ' · ' + (file.size / 1048576).toFixed(2) + ' MB' : '';
        }
        input.addEventListener('change', update);
        zone.addEventListener('dragover', function (event) { event.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', function () { zone.classList.remove('drag-over'); });
        zone.addEventListener('drop', function (event) {
            event.preventDefault(); zone.classList.remove('drag-over');
            if (event.dataTransfer.files.length === 1) { input.files = event.dataTransfer.files; update(); }
            else output.textContent = 'กรุณาเลือกครั้งละหนึ่งไฟล์';
        });
    });
    // Keep wide data tables scrollable without expanding the page on phones.
    document.querySelectorAll('table').forEach(function (table) {
        if (table.parentElement.classList.contains('table-wrap')) return;
        var wrap = document.createElement('div'); wrap.className = 'table-wrap';
        wrap.setAttribute('tabindex', '0'); wrap.setAttribute('role', 'region');
        wrap.setAttribute('aria-label', 'ตารางข้อมูล เลื่อนแนวนอนเพื่อดูเพิ่มเติม');
        table.parentNode.insertBefore(wrap, table); wrap.appendChild(table);
    });
});
