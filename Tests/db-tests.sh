#!/bin/bash

if [ $# -ne 3 ]; then
    echo "::error file=$0,line=$LINENO::Usage: $0 <host> <user> <database>"
    exit 1
fi

MYSQL_HOST="$1"
MYSQL_USER="$2"
MYSQL_DB="$3"

CONNECT=$(
    mysql -h "$MYSQL_HOST" --protocol tcp "--user=$MYSQL_USER" --batch --skip-column-names -e \
        "SHOW DATABASES LIKE '$MYSQL_DB';" | grep "$MYSQL_DB" >/dev/null
    echo "$?"
)

if [ "$CONNECT" -eq 1 ]; then
    echo "::error file=$0,line=$LINENO::The database does not exist or cannot be accessed using these credentials."
    exit 1
fi

err=$(mysql -h "$MYSQL_HOST" --protocol tcp "--user=$MYSQL_USER" "--database=$MYSQL_DB" <"data-for-testing.sql" 2>&1)
if [ "$err" != "" ]; then
    echo "::error file=$0,line=$LINENO::$err"
    echo "error=$err" >>"$GITHUB_OUTPUT"
    exit 1
fi
