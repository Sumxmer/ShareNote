"""Integration checks against a fresh, disposable MySQL/PHP environment.

Build first: docker compose build web
Run: python tests/security_regression.py --image notesharing-web
Add --keep to leave a local preview with synthetic sample data.
Never connects to the real Compose database or its volume.
"""
import argparse
import html
import io
import json
import re
import shutil
import subprocess
import sys
import time
import uuid
import zipfile
from http.cookiejar import CookieJar
from http.client import RemoteDisconnected
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.parse import quote, urlencode
from urllib.request import HTTPCookieProcessor, Request, build_opener

ROOT = Path(__file__).resolve().parents[1]
PASSWORD = "StudyTest0930"
PDF = b"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n"
passed = 0


def docker(*args, input=None, check=True):
    result = subprocess.run(["docker", *args], input=input, capture_output=True, text=True, encoding="utf-8")
    if check and result.returncode:
        raise RuntimeError(result.stderr or result.stdout)
    return result


def expect(condition, message):
    global passed
    if not condition:
        raise AssertionError(message)
    passed += 1
    print("PASS", message, flush=True)


class Client:
    def __init__(self, base):
        self.base = base
        self.jar = CookieJar()
        self.opener = build_opener(HTTPCookieProcessor(self.jar))

    def request(self, path, data=None, headers=None):
        if isinstance(data, dict):
            data = urlencode(data).encode()
        try:
            response = self.opener.open(Request(self.base + path, data=data, headers=headers or {}), timeout=10)
        except HTTPError as error:
            response = error
        raw = response.read()
        return response.code, raw.decode("utf-8", errors="replace"), dict(response.headers), response.url, raw

    def token(self, path):
        status, body, *_ = self.request(path)
        if status != 200:
            raise AssertionError(f"Cannot get form {path}: {status}")
        return re.search(r'name="csrf_token" value="([^"]+)"', body).group(1)

    def login(self, username):
        token = self.token("login.php")
        before = self.sid()
        status, _, _, url, _ = self.request("login.php", {"csrf_token": token, "username": username, "password": PASSWORD})
        expect(status == 200 and url.endswith("dashboard.php"), f"login {username}")
        expect(before != self.sid(), f"session ID rotated for {username}")

    def sid(self):
        return next(cookie.value for cookie in self.jar if cookie.name == "PHPSESSID")

    def upload(self, title, name="study.pdf", payload=PDF, subject="Database and Web Security"):
        boundary = "NoteShareBoundary" + uuid.uuid4().hex
        fields = {"csrf_token": self.token("upload.php"), "title": title, "description": "สรุปจากการเรียน พร้อมทบทวนก่อนสอบ", "subject_name": subject}
        parts = []
        for key, value in fields.items():
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="note_file"; filename="{name}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode() + payload + b"\r\n")
        parts.append(f"--{boundary}--\r\n".encode())
        return self.request("upload.php", b"".join(parts), {"Content-Type": "multipart/form-data; boundary=" + boundary})


def run(args):
    suffix = uuid.uuid4().hex[:8]
    network = "noteshare-check-" + suffix
    db = network + "-db"
    web = network + "-web"
    directory = ROOT / "tmp" / network
    uploads = directory / "uploads"
    uploads.mkdir(parents=True)
    shutil.copyfile(ROOT / "uploads" / ".htaccess", uploads / ".htaccess")
    (uploads / "fixture.pdf").write_bytes(PDF)
    base = f"http://127.0.0.1:{args.port}/"
    success = False

    def sql(statement):
        return docker("exec", db, "mysql", "--default-character-set=utf8mb4", "-uroot", "-pTestRoot0930", "sheetapp_db", "-N", "-e", statement).stdout.strip()

    try:
        docker("network", "create", network)
        docker("run", "-d", "--name", db, "--network", network,
               "--tmpfs", "/var/lib/mysql:rw,nosuid,noexec,size=1g",
               "-e", "MYSQL_ROOT_PASSWORD=TestRoot0930", "-e", "MYSQL_DATABASE=sheetapp_db",
               "-e", "MYSQL_USER=sheetapp_user", "-e", "MYSQL_PASSWORD=TestApp0930",
               "--mount", f"type=bind,source={ROOT / 'database/schema.sql'},target=/docker-entrypoint-initdb.d/01-schema.sql,readonly",
               "--mount", f"type=bind,source={ROOT / 'database/app_user_privileges.sql'},target=/docker-entrypoint-initdb.d/02-privileges.sql,readonly", "mysql:8.0")
        deadline = time.monotonic() + 60
        while time.monotonic() < deadline:
            ready = docker("exec", db, "mysqladmin", "ping", "-h", "127.0.0.1", "-uroot", "-pTestRoot0930", check=False)
            if ready.returncode == 0:
                break
            time.sleep(1)
        else:
            raise RuntimeError(docker("logs", db).stdout)
        expect(sql("SHOW TABLES").count("\n") + 1 == 6, "fresh database initializes all six tables")
        grants = sql("SHOW GRANTS FOR 'sheetapp_user'@'%'")
        expect("ALL PRIVILEGES" not in grants and "GRANT OPTION" not in grants, "least privilege replaces Docker's initial ALL grant")
        expect("security_logs" in grants and "SELECT, INSERT ON" in grants, "security logs are append-only for the app")
        forbidden = docker("exec", db, "mysql", "-h", "127.0.0.1", "-usheetapp_user", "-pTestApp0930", "sheetapp_db", "-e", "CREATE TABLE forbidden_probe (id INT)", check=False)
        expect(forbidden.returncode != 0, "application account cannot create tables")
        docker("run", "-d", "--name", web, "--network", network, "-p", f"127.0.0.1:{args.port}:80",
               "-e", "DB_HOST=" + db, "-e", "DB_USER=sheetapp_user", "-e", "DB_PASS=TestApp0930",
               "--mount", f"type=bind,source={ROOT},target=/var/www/html,readonly",
               "--mount", f"type=bind,source={uploads},target=/var/www/html/uploads", args.image)
        guest = Client(base)
        for _ in range(15):
            try:
                home = guest.request("index.php")
                if home[0] == 200:
                    break
            except (URLError, RemoteDisconnected, ConnectionError, TimeoutError):
                pass
            running = docker("inspect", "--format", "{{.State.Running}}", web).stdout.strip()
            if running != 'true':
                raise RuntimeError(docker("logs", web).stderr)
            time.sleep(1)
        else:
            raise RuntimeError(docker("logs", web).stderr)
        expect("search-panel" in home[1], "homepage renders successfully")
        expect("HttpOnly" in home[2].get("Set-Cookie", "") and "SameSite=Strict" in home[2].get("Set-Cookie", ""), "session cookie has HttpOnly and SameSite")
        expect("script-src 'self'" in home[2].get("Content-Security-Policy", ""), "CSP enabled from the actual Dockerfile")
        for path in [".git/HEAD", ".env.example", "config/db.php", "database/schema.sql", "uploads/fixture.pdf", "tests/security_regression.py"]:
            expect(guest.request(path)[0] == 403, "direct access blocked: " + path)
        for path in ["dashboard.php", "upload.php", "download.php?id=1", "admin/index.php"]:
            expect("login.php" in guest.request(path)[3], "login required: " + path)
        expect(guest.request("index.php?q[]=bad")[0] == 400, "array search input rejected without a server error")
        expect(guest.request("index.php?page=1abc")[0] == 400, "malformed numeric ID rejected")

        clients = {}
        for username, display in [("student_one", "พิมพ์ชนก"), ("student_two", "ณัฐวุฒิ"), ("demo_admin", "ผู้ดูแล NoteShare")]:
            client = Client(base)
            data = {"csrf_token": client.token("register.php"), "username": username, "email": username + "@example.test", "full_name": display, "password": PASSWORD, "confirm_password": PASSWORD}
            expect(client.request("register.php", data)[3].endswith("login.php"), "registration " + username)
            clients[username] = Client(base)
        expect(sql("SELECT LEFT(password_hash,4) FROM users WHERE username='student_one'") == "$2y$", "stored password is a hash")
        expect(sql("SELECT full_name FROM users WHERE username='student_one'") == 'พิมพ์ชนก', "Thai names stored and read as UTF-8")
        sql("UPDATE users SET role='admin' WHERE username='demo_admin'")
        for username, client in clients.items():
            client.login(username)
        owner, other, admin = clients.values()
        expect(other.request("admin/manage_users.php")[0] == 403, "ordinary user cannot access administration")
        users_page = admin.request("admin/manage_users.php")[1]
        expect('value="toggle_role"' not in users_page and 'ตั้งเป็น Admin' not in users_page and 'ลดเป็น User' not in users_page, "user management has no role-changing controls")
        other_id = sql("SELECT user_id FROM users WHERE username='student_two'")
        rejected = admin.request("admin/manage_users.php", {"csrf_token": admin.token("admin/manage_users.php"), "user_id": other_id, "action": "toggle_role"})
        expect(rejected[0] == 400 and sql(f"SELECT role FROM users WHERE user_id={other_id}") == "user", "forged admin promotion rejected without changing role")
        upload = owner.upload("Security regression note", name="ชีทเรียน.pdf")
        expect(upload[3].endswith("dashboard.php"), "valid PDF upload succeeds")
        note_id = int(sql("SELECT note_id FROM notes WHERE title='Security regression note'"))
        filename = sql(f"SELECT file_name FROM notes WHERE note_id={note_id}")
        expect(bool(re.fullmatch(r"[0-9a-f]{32}\.pdf", filename)), "uploaded file renamed randomly")
        for label, name, payload in [("script extension", "bad.php", PDF), ("wrong MIME", "bad.pdf", b"<html>not a pdf</html>"), ("extension/MIME mismatch", "bad.png", PDF), ("oversized file", "large.pdf", PDF + b"x" * (10 * 1024 * 1024)), ("empty file", "empty.pdf", b"")]:
            result = owner.upload("Should be rejected", name=name, payload=payload)
            expect("upload.php" in result[3] and sql("SELECT COUNT(*) FROM notes WHERE title='Should be rejected'") == "0", "reject upload: " + label)
        archive = io.BytesIO()
        with zipfile.ZipFile(archive, "w") as package:
            package.writestr("[Content_Types].xml", "<Types/>")
            package.writestr("word/document.xml", "<document/>")
        expect(owner.upload("Office validation note", "sample.docx", archive.getvalue())[3].endswith("dashboard.php"), "valid DOCX structure accepted")
        expect("upload.php" in owner.upload("Wrong office structure", "sample.pptx", archive.getvalue())[3], "DOCX cannot masquerade as PPTX")
        expect(other.request(f"edit_note.php?id={note_id}")[0] == 403, "user cannot edit another owner's note")
        expect(admin.request(f"edit_note.php?id={note_id}")[0] == 200, "admin can edit another owner's note")
        expect(owner.request("delete_note.php")[0] == 405, "GET cannot delete data")
        expect(owner.request("logout.php")[0] == 405, "GET cannot log a user out")
        for path, data in [(f"edit_note.php?id={note_id}", {"title": "forged"}), ("delete_note.php", {"note_id": note_id}), ("logout.php", {})]:
            expect(owner.request(path, data)[0] == 403, "CSRF token required: " + path)
        t = owner.token("upload.php")
        expect(owner.request("upload.php", {"csrf_token": t, "title[]": "bad"})[0] == 400, "array form input rejected")
        expect(owner.request("upload.php", {"csrf_token[]": t})[0] == 403, "array CSRF token rejected")
        expect(other.request("delete_note.php", {"csrf_token": other.token("dashboard.php"), "note_id": note_id})[0] == 403, "user cannot delete another owner's note")
        edit = {"csrf_token": owner.token(f"edit_note.php?id={note_id}"), "title": "Updated regression note", "description": "edited", "subject_name": "Database and Web Security"}
        expect(owner.request(f"edit_note.php?id={note_id}", edit)[3].endswith("dashboard.php"), "owner can update a note")
        expect(sql(f"SELECT title FROM notes WHERE note_id={note_id}") == "Updated regression note", "update persisted")
        payload = '<img src=x onerror=alert(1)>'
        result = other.request(f"note_detail.php?id={note_id}", {"csrf_token": other.token(f"note_detail.php?id={note_id}"), "action": "add_comment", "content": payload})
        expect(payload not in result[1] and html.escape(payload) in result[1], "stored comment XSS encoded")
        comment_id = sql(f"SELECT comment_id FROM comments WHERE note_id={note_id}")
        expect(owner.request("delete_comment.php", {"csrf_token": owner.token("dashboard.php"), "comment_id": comment_id})[0] == 403, "comment deletion checks comment ownership")
        other.request("delete_comment.php", {"csrf_token": other.token("dashboard.php"), "comment_id": comment_id})
        expect(sql(f"SELECT COUNT(*) FROM comments WHERE comment_id={comment_id}") == "0", "comment owner can delete their comment")
        expect("Updated regression note" not in guest.request("index.php?q=" + quote("' OR '1'='1"))[1], "SQL injection cannot return all search results")
        search_xss = guest.request("index.php?q=" + quote(payload))
        expect(payload not in search_xss[1] and html.escape(payload) in search_xss[1], "reflected search XSS encoded")
        downloaded = owner.request(f"download.php?id={note_id}")
        expect(downloaded[4] == PDF and "filename*=UTF-8''" in downloaded[2].get("Content-Disposition", ""), "authenticated download and Unicode filename")
        expect(sql(f"SELECT download_count FROM notes WHERE note_id={note_id}") == "1", "download counter updated")
        expect(guest.request("uploads/" + filename)[0] == 403, "randomly named upload still requires authentication")
        owner.request("delete_note.php", {"csrf_token": owner.token("dashboard.php"), "note_id": note_id})
        expect(owner.request(f"download.php?id={note_id}")[0] == 404, "removed note cannot be downloaded")
        expect(guest.request("uploads/" + filename)[0] == 403, "removed file cannot bypass download checks")
        expect(owner.request(f"edit_note.php?id={note_id}")[0] == 404, "removed note cannot be edited")
        before = sql(f"SELECT COUNT(*) FROM comments WHERE note_id={note_id}")
        expect(other.request(f"note_detail.php?id={note_id}", {"csrf_token": other.token("dashboard.php"), "action": "add_comment", "content": "hidden comment"})[0] == 404, "removed note cannot receive comments")
        expect(sql(f"SELECT COUNT(*) FROM comments WHERE note_id={note_id}") == before, "no hidden comment was written")

        # Exercise status revocation through the admin handler and role refresh
        # after maintenance through the database, using existing sessions.
        uid = sql("SELECT user_id FROM users WHERE username='student_one'")
        admin.request("admin/manage_users.php", {"csrf_token": admin.token("admin/manage_users.php"), "user_id": uid, "action": "toggle_status"})
        expect("revoked=1" in owner.request("dashboard.php")[3], "suspension revokes an existing session")
        admin.request("admin/manage_users.php", {"csrf_token": admin.token("admin/manage_users.php"), "user_id": uid, "action": "toggle_status"})
        sql("UPDATE users SET role='admin' WHERE username='student_two'")
        expect(other.request("admin/index.php")[0] == 200, "role promotion refreshed on next request")
        admin_id = sql("SELECT user_id FROM users WHERE username='demo_admin'")
        rejected = other.request("admin/manage_users.php", {"csrf_token": other.token("admin/manage_users.php"), "user_id": admin_id, "action": "toggle_role"})
        expect(rejected[0] == 400 and sql(f"SELECT role FROM users WHERE user_id={admin_id}") == "admin", "forged admin demotion rejected without changing role")
        sql("UPDATE users SET role='user' WHERE username='demo_admin'")
        expect(admin.request("admin/manage_users.php")[0] == 403, "demotion revokes existing admin permissions")
        sql("UPDATE users SET role='admin' WHERE username='demo_admin'")
        sql("UPDATE users SET role='user' WHERE username='student_two'")
        sid = other.sid()
        code = '<?php session_id("' + sid + '");session_start();$_SESSION["last_activity"]=time()-1801;session_write_close();'
        docker("exec", "-i", web, "php", input=code)
        expect("timeout=1" in other.request("dashboard.php")[3], "idle session expires after 30 minutes")
        failed = Client(base)
        for _ in range(5):
            failed.request("login.php", {"csrf_token": failed.token("login.php"), "username": "unknown_user", "password": "wrong"})
        expect(failed.request("login.php", {"csrf_token": failed.token("login.php"), "username": "student_two", "password": PASSWORD})[0] == 429, "brute-force limit rejects further login attempts")
        actions = set(sql("SELECT DISTINCT action FROM security_logs").splitlines())
        expect({"LOGIN_SUCCESS", "LOGIN_FAILED", "ACCOUNT_LOCKOUT", "NOTE_CREATE", "NOTE_UPDATE", "NOTE_DELETE", "COMMENT_CREATE", "COMMENT_DELETE", "FILE_UPLOAD", "FILE_DOWNLOAD", "ADMIN_USER_STATUS_CHANGE"}.issubset(actions), "all security events persisted")
        sql("UPDATE security_logs SET created_at=NOW()-INTERVAL 16 MINUTE WHERE action='LOGIN_FAILED'")
        owner = Client(base); owner.login("student_one")
        logs = admin.request("admin/security_logs.php?action=LOGIN_FAILED")
        expect(logs[0] == 200 and "onchange=" not in logs[1] and "data-submit" in logs[1], "log filter works with strict CSP")
        # Preserve the original table and then force a real database failure.
        sql("RENAME TABLE comments TO comments_failure_probe")
        error = owner.request("dashboard.php")
        expect(error[0] == 500 and not re.search(r"/var/www|SQLSTATE|Stack trace|mysqli", error[1]), "database failure returns a generic response without internal details")
        sql("RENAME TABLE comments_failure_probe TO comments")
        logout = owner.request("logout.php", {"csrf_token": owner.token("dashboard.php")})
        expect(logout[3].endswith("login.php") and owner.request("dashboard.php")[3].endswith("login.php"), "POST logout destroys authenticated session")

        if args.keep:
            # Synthetic fixtures for visual review; never inserted into the real DB.
            (uploads / "preview.pdf").write_bytes(PDF)
            titles = ["สรุป SQL และการออกแบบฐานข้อมูล", "รู้จัก XSS และวิธีป้องกัน", "Session & Login ฉบับเข้าใจง่าย", "ทบทวนโครงสร้างข้อมูลก่อนสอบ", "Computer Networks: บทสรุป TCP/IP", "สรุป HTML Forms และ PHP"]
            owner_id = sql("SELECT user_id FROM users WHERE username='student_one'")
            for i, title in enumerate(titles):
                subject_id = 1 if i < 3 else (2 if i == 3 else 3)
                sql("INSERT INTO notes (user_id,subject_id,title,description,file_name,original_file_name,file_size,file_type,download_count) VALUES (" + owner_id + "," + str(subject_id) + ",'" + title + "','สรุปประเด็นสำคัญจากการเรียน พร้อมตัวอย่างสำหรับทบทวนก่อนสอบ','preview.pdf','ชีทตัวอย่าง.pdf'," + str(len(PDF)) + ",'pdf'," + str((i + 1) * 7) + ")")
            (directory / "preview.json").write_text(json.dumps({"web": web, "db": db, "network": network, "url": base, "username": "demo_admin", "password": PASSWORD}, indent=2), encoding="utf-8")
        success = True
        print(f"\n{passed} checks passed.", flush=True)
        if args.keep:
            print(f"Preview: {base}\nSynthetic admin: demo_admin / {PASSWORD}\nResources: {web}, {db}, {network}", flush=True)
    finally:
        if not (args.keep and success):
            docker("rm", "-f", "-v", web, db, check=False)
            docker("network", "rm", network, check=False)
            shutil.rmtree(directory, ignore_errors=True)


if __name__ == "__main__":
    sys.stdout.reconfigure(encoding="utf-8")
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--image", default="notesharing-web")
    parser.add_argument("--port", type=int, default=18093)
    parser.add_argument("--keep", action="store_true")
    run(parser.parse_args())
