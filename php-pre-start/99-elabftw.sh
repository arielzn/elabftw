#!/bin/bash

# elabftw app customizable vars
export db_host=${ELABFTW_DB_HOST:-localhost}
export db_port=${ELABFTW_DB_PORT:-3306}
export db_name=${ELABFTW_DB_NAME:-elabftw}
export db_user=${ELABFTW_DB_USER:-elabftw}
export db_password=${ELABFTW_DB_PASSWORD}
export db_cert_path=${ELABFTW_DB_CERT_PATH:-}
export secret_key=${ELABFTW_SECRET_KEY}
export use_redis=${ELABFTW_USE_REDIS:-false}
export redis_host=${ELABFTW_REDIS_HOST:-redis}
export redis_port=${ELABFTW_REDIS_PORT:-6379}
export php_max_children=${ELABFTW_PHP_MAX_CHILDREN:-50}
export php_max_execution_time=${ELABFTW_PHP_MAX_EXECUTION_TIME:-120}
export php_timezone=${ELABFTW_PHP_TIMEZONE:-Europe/Paris}
export max_php_memory=${ELABFTW_MAX_PHP_MEMORY:-256M}
export max_upload_size=${ELABFTW_MAX_UPLOAD_SIZE:-10M}
export php_start_servers=${ELABFTW_PHP_START_SERVERS:-5}
export php_min_spare_servers=${ELABFTW_PHP_MIN_SPARE_SERVERS:-5}
export php_max_spare_servers=${ELABFTW_PHP_MAX_SPARE_SERVERS:-10}

# php-fpm config
phpfpmConf() {
    # increase max number of simultaneous requests
    sed -i "s/pm.max_children =.*$/pm.max_children = ${php_max_children}/g" /etc/php-fpm.d/www.conf
    # allow more idle server processes
    sed -i "s/pm.start_servers =.*$/pm.start_servers = ${php_start_servers}/g" /etc/php-fpm.d/www.conf
    sed -i "s/pm.min_spare_servers =.*$/pm.min_spare_servers = ${php_min_spare_servers}/g" /etc/php-fpm.d/www.conf
    sed -i "s/pm.max_spare_servers =.*$/pm.max_spare_servers = ${php_max_spare_servers}/g" /etc/php-fpm.d/www.conf
    # allow using more memory
    sed -i "s/;php_admin_value\[memory_limit\] =.*$/php_admin_value\[memory_limit\] = ${max_php_memory}/" /etc/php-fpm.d/www.conf
}

# php config
phpConf() {
    # we need this /tmp/ trick as sed cannot create temp files for the substition on /etc/
    cp -vp /etc/php.ini /tmp/php.ini
    sed -i "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/g" /tmp/php.ini
    sed -i "s/upload_max_filesize\s*=.*$/upload_max_filesize = ${max_upload_size}/g" /tmp/php.ini
    sed -i "s/post_max_size\s*=.*$/post_max_size = ${max_upload_size}/g" /tmp/php.ini
    # increase this value to allow pdf generation with big body (with base64 encoded images for instance)
    sed -i "s/;pcre.backtrack_limit=.*$/pcre.backtrack_limit=10000000/" /tmp/php.ini
    # we want a safe cookie/session
    sed -i "s/session.cookie_httponly.*/session.cookie_httponly = true/" /tmp/php.ini
    sed -i "s/session.cookie_secure.*/session.cookie_secure = 1/" /tmp/php.ini
    sed -i "s/session.use_strict_mode.*/session.use_strict_mode = 1/" /tmp/php.ini
    if ! $(grep -q cookie_samesite /tmp/php.ini); then
        echo "session.cookie_samesite = \"Strict\"" >> /tmp/php.ini
    else
        sed -i "s/session.cookie_samesite.*/session.cookie_samesite = \"Strict\"/" /tmp/php.ini
    fi
    # set redis as session handler if requested
    if ($use_redis); then
        sed -i "s:session.save_handler = files:session.save_handler = redis:" /tmp/php.ini
        sed -i "s|session.save_path =.*$|session.save_path = \"tcp://${redis_host}:${redis_port}\"|" /tmp/php.ini
    fi

    # disable url_fopen http://php.net/allow-url-fopen
    sed -i "s/allow_url_fopen = On/allow_url_fopen = Off/" /tmp/php.ini
    # enable opcache
    sed -i "s/;opcache.enable=1/opcache.enable=1/" /etc/php.d/10-opcache.ini
    # config for timezone, use : because timezone will contain /
    sed -i "s:date.timezone =.*$:date.timezone = $php_timezone:" /tmp/php.ini
    # enable open_basedir to restrict PHP's ability to read files
    # use # for separator because we cannot use : ; / or _
    sed -i "s#;open_basedir =#open_basedir = /opt/app-root/src/:/tmp/:/usr/bin/unzip#" /tmp/php.ini
    # disable some dangerous functions that we don't use
    sed -i "s/disable_functions =$/disable_functions = php_uname, getmyuid, getmypid, passthru, leak, listen, diskfreespace, tmpfile, link, ignore_user_abort, shell_exec, dl, system, highlight_file, source, show_source, fpaththru, virtual, posix_ctermid, posix_gtmpwd, posix_getegid, posix_geteuid, posix_getgid, posix_getgrgid, posix_getgrnam, posix_getgroups, posix_getlogin, posix_getpgid, posix_getpgrp, posix_getpid, posix_getppid, posix_getpwnam, posix_getpwuid, posix_getrlimit, posix_getsid, posix_getuid, posix_isatty, posix_kill, posix_mkfifo, posix_setegid, posix_seteuid, posix_setgid, posix_setpgid, posix_setsid, posix_setuid, posix_times, posix_ttyname, posix_uname, phpinfo/" /tmp/php.ini
    # allow longer requests execution time
    sed -i "s/max_execution_time\s*=.*$/max_execution_time = ${php_max_execution_time}/" /tmp/php.ini
    # replace the original php.ini file
    cp -v /tmp/php.ini /etc/php.ini
    rm -v /tmp/php.ini
}

# write config file from env vars
writeConfigFile() {
    config_path="config.php"
    config="<?php
    define('DB_HOST', '${db_host}');
    define('DB_PORT', '${db_port}');
    define('DB_NAME', '${db_name}');
    define('DB_USER', '${db_user}');
    define('DB_PASSWORD', '${db_password}');
    define('DB_CERT_PATH', '${db_cert_path}');
    define('SECRET_KEY', '${secret_key}');"
    echo "$config" > "$config_path"
}

# create work folders
createWorkDirs() {
    mkdir -v ./uploads ./cache
    chmod -v 0777 ./uploads ./cache
}


phpfpmConf
phpConf
writeConfigFile
createWorkDirs
