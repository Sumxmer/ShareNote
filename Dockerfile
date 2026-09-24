# PHP 8 + Apache
FROM php:8.3-apache

# mysqli = เชื่อมต่อฐานข้อมูล (fileinfo เปิดมาให้อยู่แล้วใน image นี้)
# mod_rewrite/headers = สำหรับ .htaccess ของโปรเจค
RUN docker-php-ext-install mysqli pdo_mysql \
    && a2enmod rewrite headers

# สำคัญ: Debian/Apache ตั้ง AllowOverride เป็น None มาโดย default
# ถ้าไม่เปลี่ยนเป็น All ไฟล์ .htaccess ของโปรเจค (config/, database/, uploads/ และที่ราก)
# จะถูกเมินทั้งหมด -> ไฟล์ .sql/.yml หลุดทางเว็บ และ uploads/ จะรันสคริปต์ได้
RUN sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# ค่า php.ini: ให้ขนาด upload สอดคล้องกับ MAX_FILE_SIZE = 10MB ใน config/functions.php
# และปิดการโชว์ error ต่อผู้ใช้ (ข้อ 7: Error Handling)
# รวมถึงเสริมความปลอดภัยของ PHP session และปิดฟีเจอร์ที่ไม่จำเป็น/เสี่ยงต่อการถูกโจมตี
RUN { \
        echo 'upload_max_filesize = 12M'; \
        echo 'post_max_size = 12M'; \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
        echo 'expose_php = Off'; \
        echo 'session.use_strict_mode = 1'; \
        echo 'session.use_only_cookies = 1'; \
        echo 'session.cookie_httponly = 1'; \
        echo 'session.cookie_samesite = Strict'; \
        echo 'allow_url_fopen = Off'; \
        echo 'allow_url_include = Off'; \
        echo 'disable_functions = exec,passthru,shell_exec,system,proc_open,popen'; \
    } > /usr/local/etc/php/conf.d/zz-noteshare.ini

# ซ่อนเวอร์ชัน Apache/PHP ในทุกจุดที่ Apache แจ้งออกมาเอง (นอกเหนือจากที่ .htaccess ตั้งไว้ในระดับ Directory)
RUN { \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
        echo 'TraceEnable Off'; \
    } >> /etc/apache2/conf-available/security.conf \
    && a2enconf security

# ให้ Apache (www-data) เขียนไฟล์ลง uploads ได้ ตั้งแต่ตอน build
# (ถ้า bind mount ทับ ให้ใช้ entrypoint ด้านล่างจัดการสิทธิ์ซ้ำอีกชั้น)
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads

COPY docker-entrypoint-noteshare.sh /usr/local/bin/noteshare-entrypoint
RUN chmod +x /usr/local/bin/noteshare-entrypoint

ENTRYPOINT ["/usr/local/bin/noteshare-entrypoint"]
CMD ["apache2-foreground"]
