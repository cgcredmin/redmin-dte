#!/bin/bash

PROJECT_PATH="$PWD"

if [[ ! -d "backup" ]]; then
    mkdir $PROJECT_PATH/backup
    mkdir $PROJECT_PATH/backup/Controllers
fi
rm -rf "$PROJECT_PATH/backup/Controllers/*.*"
cp -a "$PROJECT_PATH/src/app/Http/Controllers/." "$PROJECT_PATH/backup/Controllers/"
if [[ ! -d "$PROJECT_PATH/../yakpro-po" ]]; then
  git clone https://github.com/pk-fr/yakpro-po.git "$PROJECT_PATH/../yakpro-po"
  git clone https://github.com/nikic/PHP-Parser.git "$PROJECT_PATH/../yakpro-po/PHP-Parser"
fi
cp $PROJECT_PATH/config/obfuscation/yakpro/yakpro-po.php $PROJECT_PATH/../yakpro-po/yakpro-po.cnf
php $PROJECT_PATH/../yakpro-po/yakpro-po.php $PROJECT_PATH/src/app/Http/Controllers -o $PROJECT_PATH/obfuscated
cp -a $PROJECT_PATH/obfuscated/yakpro-po/obfuscated/. $PROJECT_PATH/src/app/Http/Controllers/
rm -rf $PROJECT_PATH/obfuscated