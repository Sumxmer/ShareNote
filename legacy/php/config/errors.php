<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Bangkok');

function abort_request(int $status, string $message): never {
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    $safe = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>NoteShare · ไม่สามารถทำรายการได้</title><body style="margin:0;background:#f5f7f3;color:#183d36;font-family:Tahoma,sans-serif;display:grid;min-height:100vh;place-items:center">';
    echo '<main style="max-width:480px;margin:24px;padding:40px;background:white;border-radius:24px;box-shadow:0 16px 60px #183d3612"><p style="color:#78877e">NOTESHARE · ' . $status . '</p><h1 style="font-size:24px">ไม่สามารถทำรายการได้</h1><p style="line-height:1.9">' . $safe . '</p><p>กรุณากลับไปยังหน้าก่อนหน้าแล้วลองอีกครั้ง</p></main></body></html>';
    exit;
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(static function (Throwable $error): void {
    error_log((string)$error);
    abort_request(500, 'ระบบไม่สามารถทำรายการได้ในขณะนี้ กรุณาลองใหม่ภายหลัง');
});
