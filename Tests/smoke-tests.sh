#!/bin/bash

RESULT=$(curl -s -o /dev/null -w "%{http_code}" --location "http://localhost:8000/index.php")
if [ "$RESULT" -ne 200 ]; then
    CONTENT=$(curl --location "http://localhost:8000/index.php")
    echo "::set-output name=content::$CONTENT"
    echo "::set-output name=error::true"
    exit 1
fi

RESULT=$(curl -s -o /dev/null -w "%{http_code}" --location "http://localhost:8000/api/v1/logs.php")
if [ "$RESULT" -ne 401 ]; then
    CONTENT=$(curl --location "http://localhost:8000/api/v1/logs.php")
    echo "::set-output name=content::$CONTENT"
    echo "::set-output name=error::true"
    exit 1
fi
