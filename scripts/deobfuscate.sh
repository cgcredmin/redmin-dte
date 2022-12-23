#!/bin/bash

PROJECT_PATH="$PWD"

rm -rf $PROJECT_PATH/src/app/Http/Controllers
  
if [[ -d "backup/Controllers" ]]; then
  cp -a $PROJECT_PATH/backup/Controllers/. $PROJECT_PATH/src/app/Http/Controllers/
fi