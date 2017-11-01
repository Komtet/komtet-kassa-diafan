#!/bin/sh

if [ -z "$1" ]; then
    echo "Usage $0 <version>";
    exit 1;
fi;

filename="komtet-kassa-$1.zip";
rm -f $filename;
zip -r $filename src;
