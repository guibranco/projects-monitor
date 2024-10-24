# Codecov Integration

This feature integrates with the Codecov API to fetch and store code coverage information for each repository. It involves querying the existing repositories from the database, calling the Codecov API for each repository to retrieve key coverage metrics, and storing these metrics in a new dedicated table within our database.

## Setup Instructions

1. **Codecov API Token**: Obtain your Codecov API token and replace `'your_codecov_api_token'` in `CronJobHandler.php`.

2. **Database Configuration**: Update the DSN, username, and password in `CronJobHandler.php` to match your database configuration.

3. **Run the Cron Job**: Set up a cron job to execute `CronJobHandler.php` periodically to keep the coverage data up to date.

## Components

- **CodecovIntegration.php**: Handles authentication and communication with the Codecov API.
- **DatabaseHandler.php**: Manages database operations, including creating the `codecov_info` table and inserting/updating coverage data.
- **CronJobHandler.php**: Orchestrates the process of fetching coverage data and storing it in the database.

## Database

The `codecov_info` table stores the following fields:
- `repository_id`, `coverage_percentage`, `lines_covered`, `total_lines`
