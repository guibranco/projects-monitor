#!/bin/bash

RESULT=$(curl -s -o /dev/null -w "%{http_code}" --location "http://localhost:8000/index.php")
if [ "$RESULT" -ne 200 ]; then
    CONTENT=$(curl --location "http://localhost:8000/index.php")
    echo "content=$CONTENT" >>"$GITHUB_OUTPUT"
    echo "error=true" >>"$GITHUB_OUTPUT"
    exit 1
fi

# The API reference is public: both the Swagger UI and the spec must render
# without a session (no redirect to the login page).
RESULT=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:8000/api/v1/swagger")
if [ "$RESULT" -ne 200 ]; then
    CONTENT=$(curl "http://localhost:8000/api/v1/swagger")
    echo "content=$CONTENT" >>"$GITHUB_OUTPUT"
    echo "error=true" >>"$GITHUB_OUTPUT"
    exit 1
fi

RESULT=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:8000/api/v1/openapi")
if [ "$RESULT" -ne 200 ]; then
    CONTENT=$(curl "http://localhost:8000/api/v1/openapi")
    echo "content=$CONTENT" >>"$GITHUB_OUTPUT"
    echo "error=true" >>"$GITHUB_OUTPUT"
    exit 1
fi

RESULT=$(curl -s -o /dev/null -w "%{http_code}" --location "http://localhost:8000/api/v1/messages")
if [ "$RESULT" -ne 200 ]; then
    CONTENT=$(curl --location "http://localhost:8000/api/v1/messages")
    echo "content=$CONTENT" >>"$GITHUB_OUTPUT"
    echo "error=true" >>"$GITHUB_OUTPUT"
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
    "details": "some details"
}')
if [ "$RESULT" -ne 202 ]; then
    CONTENT=$(curl --location 'http://localhost:8000/api/v1/log-message' \
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
    "details": "some details"
}')
    echo "content=$CONTENT" >>"$GITHUB_OUTPUT"
    echo "error=true" >>"$GITHUB_OUTPUT"
    exit 1
fi
