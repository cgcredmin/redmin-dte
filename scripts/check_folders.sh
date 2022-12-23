#!/bin/sh

# set an array of folders to check
folders=(
  "storage/app"
  "storage/app/certs"
  "storage/app/pdf"
  "storage/app/tempfiles"
  "storage/app/tmp"
  "storage/app/xml"
  "storage/app/xml/dte"
  "storage/app/xml/folios"
  "storage/public"
  "storage/framework"
  "storage/cache"
  "storage/cache/data"
  "storage/sessions"
  "storage/testing"
  "storage/views"
  "storage/logs"
  "tests"
)

# loop through the array and check if the folder exists, if not, then create it and set permissions
for folder in "${folders[@]}"; do
  if [[ ! -d "$folder" ]]; then
    mkdir "$folder"
    echo "$folder created"
  fi
  chmod -R 777 "$folder"
  echo "$folder permissions set"
done
