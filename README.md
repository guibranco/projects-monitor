# Projects Monitor

:gear: :bell: GitHub projects monitor.

[![wakatime](https://wakatime.com/badge/github/guibranco/projects-monitor.svg)](https://wakatime.com/badge/github/guibranco/projects-monitor)
[![Deploy via ftp](https://github.com/guibranco/Projects-Monitor/actions/workflows/deploy.yml/badge.svg)](https://github.com/guibranco/Projects-Monitor/actions/workflows/deploy.yml)
[![Maintainability](https://api.codeclimate.com/v1/badges/576a4ac11de09db48520/maintainability)](https://codeclimate.com/github/guibranco/projects-monitor/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/576a4ac11de09db48520/test_coverage)](https://codeclimate.com/github/guibranco/projects-monitor/test_coverage)
[![CodeFactor](https://www.codefactor.io/repository/github/guibranco/projects-monitor/badge)](https://www.codefactor.io/repository/github/guibranco/projects-monitor)

---

A dashboard to visualize and monitor my GitHub projects, my personal projects and infrastructure all in one place.

> [!Warning]
>  
> Currently a single page, but a UI kit is coming in place to give more life to this page! [#12](https://github.com/guibranco/projects-monitor/issues/12)

![dashboard](projects-monitor-dashboard.png)

# Codacy Integration Feature

This feature implements the ability to fetch and store relevant information from Codacy for each repository in our system. It involves querying repositories from our database, leveraging the existing Codacy integration class to call the Codacy API, parsing the received data, and storing essential details—such as repository name, code quality score, and issues count—in a new dedicated database table.

## Key Components

1. **Database Table**: A new table `codacy_info` is created to store Codacy data.
2. **CodacyDataService**: A service to fetch and store Codacy data.
3. **Scheduler Task**: A task to periodically update Codacy data.

## How to Use

1. Run the migration script in `Sql/Migrations/20231101_create_codacy_info_table.sql` to create the necessary database table.
2. Use the `CodacyDataService` to fetch and store data.
3. Schedule the `CodacyDataUpdateTask` to run at desired intervals.

## Configuration

Ensure that the Codacy API keys and endpoints are correctly configured in your environment variables.

