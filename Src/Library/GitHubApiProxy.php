<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Client for the github-api-proxy (guibranco/github-api-proxy) stats API —
 * the Go service every GitHub API call goes through, which records who
 * called what and when, plus each caller's rate limits. Its admin listener
 * is bearer-token protected (Admin--ApiToken in Grimoire) and limited to the
 * proxy's network allow-list, which includes this host.
 */
class GitHubApiProxy extends BearerTokenApiClient
{

    /**
     * Total transfer timeout per call, in seconds; even if all seven calls
     * of one refresh ran to the limit, they would end inside the
     * dashboard's 60-second refresh.
     */
    private const TIMEOUT_SECONDS = 8;


    /**
     * Loads the API URL and token from githubApiProxy.secrets.php. The URL
     * must be HTTPS (the bearer token is the proxy's admin token).
     *
     * @throws SecretsFileNotFoundException When the secrets file is missing.
     * @throws RequestException             When the URL is not HTTPS.
     */
    public function __construct()
    {
        $this->initialise(
            'githubApiProxy.secrets.php',
            'githubApiProxyUrl',
            'githubApiProxyToken',
            self::TIMEOUT_SECONDS,
            true
        );

    }//end __construct()


    /**
     * Name used in error messages.
     *
     * @return string
     */
    protected function serviceName(): string
    {
        return 'GitHub API proxy';

    }//end serviceName()


    /**
     * Returns the `data` member of a stats API response.
     *
     * @param string $endpoint Path (and query) relative to the API base URL.
     *
     * @return array
     *
     * @throws RequestException When the response has no `data` member.
     */
    private function getData(string $endpoint): array
    {
        $body = $this->doGet($endpoint);
        if (isset($body['data']) === false || is_array($body['data']) === false) {
            throw new RequestException(
                'GitHub API proxy response has no data member: GET '.$endpoint
            );
        }

        return $body['data'];

    }//end getData()


    /**
     * Everything the dashboard shows for the proxy, rendered by
     * GitHubApiProxyPresenter: the range's totals, timeline and callers,
     * plus each caller's current GitHub rate limits and the service's
     * health (version, database, allow-list); and, rendered by
     * GitHubApiProxyTrafficPresenter, the range's busiest routes and its
     * requests grouped by token, route and status (top 50).
     *
     * @param GitHubApiProxyRange|null $range Time range; defaults to the last 24 hours.
     *
     * @return array
     *
     * @throws RequestException When any stats call fails.
     */
    public function getDashboard(?GitHubApiProxyRange $range=null): array
    {
        $range  = ($range ?? new GitHubApiProxyRange());
        $period = 'period='.$range->period();

        $summary    = $this->getData('api/v1/summary?'.$period);
        $timeline   = $this->getData('api/v1/timeline?'.$period.'&bucket='.$range->bucket());
        $callers    = $this->getData('api/v1/callers?'.$period.'&limit=50');
        $rateLimits = $this->getData('api/v1/rate-limits');
        $routes     = $this->getData('api/v1/routes?'.$period.'&limit=50');
        $requests   = $this->getData('api/v1/route-usage?'.$period.'&limit=50');
        $health     = $this->doGet('health');

        $presenter = new GitHubApiProxyPresenter();
        $traffic   = new GitHubApiProxyTrafficPresenter();

        $dashboard = $presenter->present($summary, $timeline, $callers, $rateLimits, $health, $range);

        return array_merge($dashboard, $traffic->present($routes, $requests));

    }//end getDashboard()


}//end class
