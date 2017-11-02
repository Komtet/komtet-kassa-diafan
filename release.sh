#!/bin/sh

if [ -z "$1" ]; then
    echo "Usage $0 <version>";
    exit 1;
fi;

composer install
rm -rf src/plugins/komtetkassa/lib/*
cp -R vendor/* src/plugins/komtetkassa/lib/

filename="dist/komtet-kassa-diafan-$1.zip";
rm -f $filename;
cd src
zip -r "../$filename" *;
