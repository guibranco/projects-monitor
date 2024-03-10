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
        "SHOW DATABASES LIKE '$DBNAME';" | grep "$DBNAME" >/dev/null
    echo "$?"
)

if [ "$CONNECT" -eq 1 ]; then
    echo "::error file=$0,line=$LINENO::The database does not exist or cannot be accessed using these credentials."
    exit 1
fi

SqlFiles=(Sql/*.sql)
Files=${#SqlFiles[@]}
Schema=$(mysql -h "$MYSQL_HOST" --protocol tcp "--user=$MYSQL_USER" "--database=$MYSQL_DB" -sse "SELECT COUNT(1) FROM schema_version;")
Applications=$(mysql -h "$MYSQL_HOST" --protocol tcp "--user=$MYSQL_USER" "--database=$MYSQL_DB" -sse "SELECT COUNT(1) FROM applications;")
Errors=$(mysql -h "$MYSQL_HOST" --protocol tcp "--user=$MYSQL_USER" "--database=$MYSQL_DB" -sse "SELECT COUNT(1) FROM errors;")

ExpectedApplications=1
ExpectedErrors=1

LOG="Database integrity check\n"
LOG="$LOG|Table|Expected|Current|\n"
LOG="$LOG|---|---|---|\n"
LOG="$LOG|Schema|$Files|$Schema|\n"
LOG="$LOG|Applications|$ExpectedApplications|$Applications|\n"
LOG="$LOG|Errors|$ExpectedErrors|$Errors|\n"

echo -e "$LOG"
{
    echo "log<<EOF"
    echo -e "$LOG"
    echo "EOF"
} >>"$GITHUB_OUTPUT"

if [ "$Schema" -eq "$Files" ] &&
    [ "$Applications" -eq "$ExpectedApplications" ] &&
    [ "$Errors" -eq "$ExpectedErrors" ]; then
    echo "Database is correct."
else
    echo "Database is incorrect."
    echo "error=true" >>"$GITHUB_OUTPUT"
    exit 1
fi
