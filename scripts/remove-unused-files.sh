#!/bin/bash


if [[ -d "public/html" ]]; then
  rm -rf "public/html"
  echo "public/html removed"
fi
if [[ -d "public/assets" ]]; then
  rm -rf "public/assets"
  echo "public/assets removed"
fi
if [[ -d "public/lib" ]]; then
  rm -rf "public/lib"
  echo "public/lib removed"
fi
if [[ -d "public/css" ]]; then
  rm -rf "public/css"
  echo "public/css removed"
fi
if [[ -d "public/js" ]]; then
  rm -rf "public/js"
  echo "public/js removed"
fi
if [[ -f "public/[0-9]*.js" ]]; then
  rm "public/[0-9]*.js"
  echo "public/[0-9]*.js removed"
fi



