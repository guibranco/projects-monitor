import requests

class GitHubApiClient:
    def __init__(self, token):
        self.base_url = 'https://api.github.com'
        self.headers = {
            'Authorization': f'token {token}',
            'Accept': 'application/vnd.github.v3+json'
        }

    def get_repositories(self, username):
        url = f'{self.base_url}/users/{username}/repos'
        response = requests.get(url, headers=self.headers)
        response.raise_for_status()
        return response.json()

    def get_repository_details(self, owner, repo):
        url = f'{self.base_url}/repos/{owner}/{repo}'
        response = requests.get(url, headers=self.headers)
        response.raise_for_status()
        return response.json()

    def handle_error(self, error):
        if error.response:
            print(f'Error: {error.response.status_code} - {error.response.json()}')
        else:
            print(f'Error: {error}')

# Example usage:
# client = GitHubApiClient(token='your_access_token')
# repos = client.get_repositories('username')
# details = client.get_repository_details('owner', 'repo')
