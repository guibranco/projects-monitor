# Sonar Cloud Integration

## Overview
This document provides instructions on how to configure and use the Sonar Cloud integration in the project.

## Configuration
1. **API Token**: Obtain an API token from Sonar Cloud.
2. **Organization**: Note the organization key from your Sonar Cloud account.
3. **Project Key**: Note the project key for the project you want to monitor.

## Usage
- The integration is handled by the `SonarCloudIntegration` class located in `Src/sonar_cloud_integration.py`.
- Initialize the class with your API token, organization, and project key.
- Use the `fetch_project_metrics` method to retrieve metrics such as code smells, bugs, vulnerabilities, and coverage.

## Testing
- Tests are available in `Tests/test_sonar_cloud_integration.py`.
- Use `unittest` to run the tests and ensure the integration works as expected.

## Notes
- Ensure your API token has the necessary permissions to access the project metrics.