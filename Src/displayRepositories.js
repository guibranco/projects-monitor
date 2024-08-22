const GitHubApiClient = require('./GitHubApiClient');

async function displayRepositories(username) {
    const client = new GitHubApiClient('your_access_token');
    try {
        const repos = await client.get_repositories(username);
        repos.forEach(repo => {
            console.log(`Name: ${repo.name}`);
            console.log(`Description: ${repo.description}`);
            console.log(`URL: ${repo.html_url}`);
            console.log('---------------------------');
        });
    } catch (error) {
        client.handle_error(error);
    }
}

// Example usage
displayRepositories('guibranco');

module.exports = displayRepositories;

// Note: Replace 'your_access_token' with a valid GitHub personal access token.
// This script logs repository information to the console.
// Further integration with UI components can be done as needed.
