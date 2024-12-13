#!/bin/bash

if [[ $# -ne 4 && $# -ne 5 ]]; then
    echo "::error file=$0,line=$LINENO::Usage: $0 <path> <host> <user> <database>"
    echo "::error file=$0,line=$LINENO::Or: $0 <path> <host> <user> <database> --dry-run"
    exit 1
fi

if [ ! -d "$1" ]; then
    echo "::warning file=$0,line=$LINENO::Directory $1 does not exist."
    exit 0
fi

cd "$1" || exit

MYSQL_HOST="$2"
MYSQL_USER="$3"
MYSQL_DB="$4"
DRY_RUN="$5"

CONNECT=$(
    mysql -h "$MYSQL_HOST" --protocol tcp "--user=$MYSQL_USER" --batch --skip-column-names -e \
        "SHOW DATABASES LIKE '$MYSQL_DB';" | grep "$MYSQL_DB" >/dev/null
    echo "$?"
)

if [ "$CONNECT" -eq 1 ]; then
    MESSAGE="The database does not exist or is not accessible using this credentials."
    echo "::error file=$0,line=$LINENO::$MESSAGE"
    exit 1
fi

DB_ENV="PRD"

if [ "$DRY_RUN" == "--dry-run" ]; then
    DB_ENV="PRD [dry run]"
fi
if [[ "$MYSQL_HOST" == "localhost" && "$MYSQL_USER" == "test" ]]; then
    DB_ENV="CI [Docker]"
fi

echo "### Database migration summary (\`$DB_ENV\` environment) :rocket:" >>"$GITHUB_STEP_SUMMARY"
if [ "$DRY_RUN" == "--dry-run" ]; then
    {
        echo ""
        echo ":warning: This is a dry run. No SQL will be executed."
        echo ""
    } >>"$GITHUB_STEP_SUMMARY"
fi
echo "" >>"$GITHUB_STEP_SUMMARY"

FILES=""
INDEX=0

SCHEMA_TABLE=$(mysql -h "$MYSQL_HOST" --protocol tcp "--user=$MYSQL_USER" "--database=$MYSQL_DB" -sse \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$MYSQL_DB' AND table_name='schema_version';")

if [ "$SCHEMA_TABLE" -eq 1 ]; then
    if [ "$DRY_RUN" == "--dry-run" ]; then
        echo "The schema version table does exist."
    fi
    echo "- :thumbsup: The \`schema version\` table already exists." >>"$GITHUB_STEP_SUMMARY"
else
    if [ "$DRY_RUN" == "--dry-run" ]; then
        echo "The schema version table does not exist."
        echo "Dry run, not executing SQL"
        FILES="$FILES- schema-version.sql\n"
        echo "file[$INDEX]=schema-version.sql" >>"$GITHUB_OUTPUT"
        INDEX=$((INDEX + 1))
    else
        echo "::warning file=$0,line=$LINENO::The schema version table does not exist."
        mysql -h "$MYSQL_HOST" --protocol tcp "--user=$MYSQL_USER" "--database=$MYSQL_DB" <../Tools/schema-version.sql
    fi
    echo "- :white_check_mark: The \`schema version\` table was created." >>"$GITHUB_STEP_SUMMARY"
fi

WORKING_SQL_FILE="step.temp;"
echo "START TRANSACTION;" >$WORKING_SQL_FILE

for FILE in *.sql; do
    echo "Checking file $FILE"
    sha256=$(sha256sum "$FILE" | cut -d " " -f 1)
    EXISTS=0

    if [[ "$DRY_RUN" != "--dry-run" || $SCHEMA_TABLE -eq 1 ]]; then
        EXISTS=$(
            mysql -h "$MYSQL_HOST" --protocol tcp "--user=$MYSQL_USER" "--database=$MYSQL_DB" -sse \
                "SELECT COUNT(*) FROM schema_version WHERE \
             Filename='$FILE' AND Checksum='$sha256';"
        )
    fi

    if [ "$EXISTS" -eq 1 ]; then
        if [ "$DRY_RUN" == "--dry-run" ]; then
            echo "File $FILE already processed"
        fi
        echo "- :thumbsup: The file \`$FILE\` was already processed." >>"$GITHUB_STEP_SUMMARY"
    else
        if [ "$DRY_RUN" == "--dry-run" ]; then
            echo "Running file $FILE"
        fi
        cat "$FILE" >>$WORKING_SQL_FILE
        echo "INSERT INTO schema_version (Filename, Checksum) VALUES ('$FILE', '$sha256');" >>$WORKING_SQL_FILE
        echo "- :white_check_mark: The file \`$FILE\` was processed." >>"$GITHUB_STEP_SUMMARY"
        FILES="$FILES- $FILE\n"
        echo "file[$INDEX]=$FILE" >>"$GITHUB_OUTPUT"
        INDEX=$((INDEX + 1))
    fi
done

echo "COMMIT;" >>$WORKING_SQL_FILE

if [ "$DRY_RUN" == "--dry-run" ]; then
    echo "::warning file=$0,line=$LINENO::Dry run, not executing SQL"
else
    err=$(mysql -h "$MYSQL_HOST" --protocol tcp "--user=$MYSQL_USER" "--database=$MYSQL_DB" <$WORKING_SQL_FILE 2>&1)
    if [ "$err" != "" ]; then
        echo "::error file=$0,line=$LINENO::$err"
        echo "error=true" >>"$GITHUB_OUTPUT"        
        escaped_error=$(echo "$err" | sed 's/%/%25/g' | sed 's/\r/%0D/g' | sed 's/\n/%0A/g')
        echo "error_message=$escaped_error" >>"$GITHUB_OUTPUT"
        exit 1
    fi
fi

rm $WORKING_SQL_FILE

if [ "$FILES" != "" ]; then
    echo "The following database files were processed:"
    echo -e "$FILES"

    {
        echo "files<<EOF"
        echo -e "$FILES"
        echo "EOF"
    } >>"$GITHUB_OUTPUT"
fi
