# CodeClimate Integration

This feature integrates with the CodeClimate API to fetch and store key code quality metrics for each of our repositories.

## Setup Instructions

1. **Environment Variables**
   
   - Copy `.env.example` to `.env` and update the `CODECLIMATE_API_KEY` with your CodeClimate API key.

2. **Database Migration**

   - Run the migration to create the `codeclimate_metrics` table:
     ```
     php artisan migrate
     ```

3. **Cron Job Setup**

   - Set up a cron job to run the `scripts/cron_job.sh` script at your desired interval to update metrics periodically.
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
