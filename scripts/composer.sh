#!/bin/sh

#write red text
function red() {
  echo -e "\033[31m$1\033[0m"
}

red "# Installing composer"
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm -rf composer-setup.php

update=$1 #true or false
# if $1 is empty, then it will be false
if [ -z "$update" ]; then
  update=true
fi
if [ "$update" = "true" ]; then
  red "# RUN composer update"
  composer update
fi

red ">>> END <<<"
