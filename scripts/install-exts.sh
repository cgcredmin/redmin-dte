#!/bin/sh

#write red text
function red() {
  echo -e "\033[31m$1\033[0m"
}

# apk add --no-cache php
# apk add --no-cache php-common
apk add --no-cache php-curl
apk add --no-cache php-fpm
apk add --no-cache php-gd
apk add --no-cache php-zip
apk add --no-cache php-iconv
apk add --no-cache php-mbstring
apk add --no-cache php-mysqli
apk add --no-cache php-fileinfo
apk add --no-cache php-json
apk add --no-cache php-dom
apk add --no-cache php-opcache
apk add --no-cache php-openssl
apk add --no-cache php-phar
apk add --no-cache php-pdo
apk add --no-cache php-pdo_mysql
apk add --no-cache php-pdo_sqlite
apk add --no-cache php-tokenizer
apk add --no-cache php-simplexml
apk add --no-cache php-xml
apk add --no-cache php-soap
apk add --no-cache php-xmlreader
apk add --no-cache php-xmlwriter
apk add --no-cache php-xsl
apk add --no-cache php-zlib
apk add --no-cache php-zip
apk add --no-cache php-pear
apk add --no-cache php-session
apk add --no-cache php-sockets

red ">>> THIS SCRIPT IS FOR INSTALL PHP EXTs <<<"
