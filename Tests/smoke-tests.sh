#!/bin/bash

RESULT=$(curl -s -o /dev/null -w "%{http_code}" --location "http://localhost:8000/index.php")
if [ "$RESULT" -ne 200 ]; then
    CONTENT=$(curl --location "http://localhost:8000/index.php")
    echo "::set-output name=content::$CONTENT"
    echo "::set-output name=error::true"
    exit 1
fi

RESULT=$(curl -s -o /dev/null -w "%{http_code}" --location "http://localhost:8000/api/v1/messages")
if [ "$RESULT" -ne 200 ]; then
    CONTENT=$(curl --location "http://localhost:8000/api/v1/messages")
    echo "::set-output name=content::$CONTENT"
    echo "::set-output name=error::true"
    exit 1
fi

RESULT=$(curl -s -o /dev/null -w "%{http_code}" --location "http://localhost:8000/api/v1/log-message")
if [ "$RESULT" -ne 401 ]; then
    CONTENT=$(curl --location "http://localhost:8000/api/v1/log-message")
    echo "content=$CONTENT" >>"$GITHUB_OUTPUT"
    echo "error=true" >>"$GITHUB_OUTPUT"
    exit 1
fi

RESULT=$(curl -s -o /dev/null -w "%{http_code}" --location 'http://localhost:8000/api/v1/log-message' \
    --header 'X-API-KEY: test_application_invalid' \
    --header 'X-API-TOKEN: 1234567890' \
    --header 'Content-Type: application/json' \
    --data '{}')
if [ "$RESULT" -ne 403 ]; then
    CONTENT=$(curl --location 'http://localhost:8000/api/v1/log-message' \
        --header 'X-API-KEY: test_application_invalid' \
        --header 'X-API-TOKEN: 1234567890' \
        --header 'Content-Type: application/json' \
        --data '{}')
    echo "content=$CONTENT" >>"$GITHUB_OUTPUT"
    echo "error=true" >>"$GITHUB_OUTPUT"
    exit 1
fi

RESULT=$(curl -s -o /dev/null -w "%{http_code}" --location 'http://localhost:8000/api/v1/log-message' \
    "details": "some details"
}')
if [ "$RESULT" -ne 202 ]; then
    CONTENT=$(curl --location 'http://localhost:8000/api/v1/log-message' \
        --header 'X-API-KEY: test_application' \
    --header 'X-API-KEY: test_application' \
    --header 'X-API-TOKEN: 1234567890' \
    --header 'Content-Type: application/json' \
    --data '{
    "class": "class-name",
    "function": "function-name",
    "file": "postman.php",
    "line": 1,
    "object": "",
    "type": "",
    "args": "",
    "message": "test",
        --header 'X-API-TOKEN: 1234567890' \
        --header 'Content-Type: application/json' \
        --data '{
    "class": "class-name",
    "function": "function-name",
    "file": "postman.php",
    "line": 1,
    "object": "",
    "type": "",
    "args": "",
    "message": "test",
    "details": "some details"
}')
    echo "content=$CONTENT" >>"$GITHUB_OUTPUT"
    echo "error=true" >>"$GITHUB_OUTPUT"
    exit 1
fi
RESULT=$(curl -s -o /dev/null -w "%{http_code}" --location 'http://localhost:8000/api/v1/log-message' \
    --header 'Content-Type: application/json' \
    --data '{"details": "some details"}'
) 
if [ "$RESULT" -ne 202 ]; then
    CONTENT=$(curl --location 'http://localhost:8000/api/v1/log-message' \
        --header 'X-API-KEY: test_application' \
        --header 'X-API-TOKEN: 1234567890' \
        --header 'Content-Type: application/json' \
