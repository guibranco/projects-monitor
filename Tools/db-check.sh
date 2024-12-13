#!/bin/bash

if [ $# -ne 3 ]; then
    echo "::error file=$0,line=$LINENO::Usage: $0 <host> <user> <database>"
    exit 1
fi

MYSQL_HOST="$1"
MYSQL_USER="$2"
MYSQL_DB="$3"

DB_ENV="PRD"

if [[ "$MYSQL_HOST" == "localhost" && "$MYSQL_USER" == "test" ]]; then
    DB_ENV="CI [Docker]"
fi

CONNECT=$(
    mysql -h "$MYSQL_HOST" --protocol tcp "--user=$MYSQL_USER" --batch --skip-column-names -e \
        "SHOW DATABASES LIKE '$MYSQL_DB';" | grep "$MYSQL_DB" >/dev/null
    echo "$?"
)

if [ "$CONNECT" -eq 1 ]; then
    MESSAGE=""
    echo "::error file=$0,line=$LINENO::The database does not exist or cannot be accessed using these credentials."
    exit 1
fi

EXISTS=$(mysql -h "$MYSQL_HOST" --protocol tcp "--user=$MYSQL_USER" "--database=$MYSQL_DB" -sse \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$MYSQL_DB' AND table_name='schema_version';")

echo "### Database check summary (\`$DB_ENV\` environment) :eyes:" >>"$GITHUB_STEP_SUMMARY"
echo "" >>"$GITHUB_STEP_SUMMARY"

if [ "$EXISTS" -eq 1 ]; then
    MESSAGE="The \`schema version\` table **does** exist."
    echo "::notice file=$0,line=$LINENO::$MESSAGE"
    echo ":thumbsup: $MESSAGE" >>"$GITHUB_STEP_SUMMARY"
    echo "not_found=false" >>"$GITHUB_OUTPUT"
else
    MESSAGE="The \`schema version\` table **does not** exist."
    if [ "$DB_ENV" == "PRD" ]; then
        echo "::warning file=$0,line=$LINENO::$MESSAGE"
    fi
    echo ":thumbsdown: $MESSAGE" >>"$GITHUB_STEP_SUMMARY"
    echo "not_found=true" >>"$GITHUB_OUTPUT"
fi
