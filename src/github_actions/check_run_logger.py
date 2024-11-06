import requests
import logging

class CheckRunLogger:
    def __init__(self, repo_owner, repo_name, check_run_id, token):
        self.repo_owner = repo_owner
        self.repo_name = repo_name
        self.check_run_id = check_run_id
        self.token = token
        self.api_url = f"https://api.github.com/repos/{repo_owner}/{repo_name}/check-runs/{check_run_id}/annotations"

    def fetch_annotations(self):
        headers = {'Authorization': f'token {self.token}'}
        response = requests.get(self.api_url, headers=headers)
        if response.status_code == 200:
            return response.json()
        else:
            logging.error(f"Failed to fetch annotations: {response.status_code}")
            return []

    def log_warnings(self):
        annotations = self.fetch_annotations()
        for annotation in annotations:
            if annotation['annotation_level'] == 'warning':
                logging.warning(f"Warning: {annotation['message']}")
