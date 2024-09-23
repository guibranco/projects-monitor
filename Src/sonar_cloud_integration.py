import requests

class SonarCloudIntegration:
    def __init__(self, token, organization, project_key):
        self.base_url = "https://sonarcloud.io/api"
        self.token = token
        self.organization = organization
        self.project_key = project_key

    def get_headers(self):
        return {
            'Authorization': f'Bearer {self.token}'
        }

    def fetch_project_metrics(self):
        endpoint = f"/measures/component?component={self.project_key}&metricKeys=code_smells,bugs,vulnerabilities,coverage"
        response = requests.get(self.base_url + endpoint, headers=self.get_headers())
        if response.status_code == 200:
            return response.json()
        else:
            response.raise_for_status()