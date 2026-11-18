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
     * Period the dashboard summarises.
     */
    public const PERIOD = '24h';

    /**
     * Total transfer timeout per call, in seconds; the five calls of one
     * refresh stay well inside the dashboard's 60-second refresh.
     */
    private const TIMEOUT_SECONDS = 10;


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
     * GitHubApiProxyPresenter: the last 24 hours' totals, hourly timeline and
     * callers, plus each caller's current GitHub rate limits and the
     * service's health (version, database, allow-list).
     *
     * @return array
     *
     * @throws RequestException When any stats call fails.
     */
    public function getDashboard(): array
    {
        $period = 'period='.self::PERIOD;

        $summary    = $this->getData('api/v1/summary?'.$period);
        $timeline   = $this->getData('api/v1/timeline?'.$period.'&bucket=hour');
        $callers    = $this->getData('api/v1/callers?'.$period.'&limit=50');
        $rateLimits = $this->getData('api/v1/rate-limits');
        $health     = $this->doGet('health');

        $presenter = new GitHubApiProxyPresenter();

        return $presenter->present($summary, $timeline, $callers, $rateLimits, $health);

    }//end getDashboard()


}//end class
