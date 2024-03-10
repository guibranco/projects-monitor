#!/bin/bash

if [[ $# -ne 1 ]]; then
    echo "::error file=$0,line=$LINENO::Usage: $0 <path>"
    exit 1
fi

if [ ! -d "$1" ]; then
    echo "::warning file=$0,line=$LINENO::Directory $1 does not exist."
    exit 0
fi

cd "$1" || exit

FAILED=""

echo "### Request tests summary :eyes:" >>"$GITHUB_STEP_SUMMARY"
echo "" >>"$GITHUB_STEP_SUMMARY"

for FILE in *.json; do

    HANDLER="${FILE/.json/}"
    ENDPOINT="http://localhost:8000/${HANDLER%%_*}"
    HEADERS="${FILE/.json/".txt"}"
    RESULT=$(curl -s -o /dev/null -w "%{http_code}" --location "$ENDPOINT" --header @"$HEADERS" --data @"$FILE")

    if [ "$RESULT" -eq 202 ]; then
        echo "- :white_check_mark: The request \`$FILE\` succeeded." >>"$GITHUB_STEP_SUMMARY"
    else
        MESSAGE="- :x: The request \`$FILE\` failed with **HTTP status $RESULT**."
        RESPONSE=$(curl -s --location "$ENDPOINT" --header @"$HEADERS" --data @"$FILE")
        echo "$MESSAGE" >>"$GITHUB_STEP_SUMMARY"
        FAILED="$FAILED $MESSAGE\n"
        {
            echo "response<<EOF"
            echo -e "$RESPONSE"
            echo "EOF"
        } >>"$GITHUB_OUTPUT"
    fi
done

if [ "$FAILED" != "" ]; then
    echo "The following requests failed:"
    echo -e "$FAILED"

    {
        echo "requests_failed<<EOF"
        echo -e "$FAILED"
        echo "EOF"
    } >>"$GITHUB_OUTPUT"
    echo "error=true" >>"$GITHUB_OUTPUT"
    exit 1
fi
