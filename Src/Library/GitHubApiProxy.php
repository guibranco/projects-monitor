<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\Library\Configuration;

/**
 * Client for the github-api-proxy (guibranco/github-api-proxy) stats API —
 * the Go service every GitHub API call goes through, which records who
 * called what and when, plus each caller's rate limits. Its admin listener
 * is bearer-token protected (Admin--ApiToken in Grimoire) and limited to the
 * proxy's network allow-list, which includes this host.
 */
class GitHubApiProxy
{

    /**
     * Period the dashboard summarises.
     */
    public const PERIOD = '24h';

    /**
     * Base URL of the stats API, with a trailing slash.
     *
     * @var string
     */
    private $apiUrl;

    /**
     * Request headers sent on every call (bearer token, accept, user agent).
     *
     * @var array<int, string>
     */
    private $headers;

    /**
     * HTTP client.
     *
     * @var Request
     */
    private $request;


    /**
     * Loads the API URL and token from githubApiProxy.secrets.php.
     *
     * @throws SecretsFileNotFoundException When the secrets file is missing.
     */
    public function __construct()
    {
        global $githubApiProxyUrl, $githubApiProxyToken;

        $config = new Configuration();
        $config->init();

        $secretsFile = __DIR__.'/../secrets/githubApiProxy.secrets.php';
        if (file_exists($secretsFile) === false) {
            throw new SecretsFileNotFoundException('File not found: githubApiProxy.secrets.php');
        }

        include_once $secretsFile;

        // Trimmed for the same reason as WebhooksHandler: a stray newline in a
        // pasted secret would end up in the URL or the Authorization header.
        $this->apiUrl  = rtrim(trim((string) $githubApiProxyUrl), '/').'/';
        $this->headers = [
                          'Authorization: Bearer '.trim((string) $githubApiProxyToken),
                          'Accept: application/json',
                          'Cache-Control: no-cache',
                          constant('USER_AGENT'),
                         ];
        $this->request = new Request();

    }//end __construct()


    /**
     * Performs a GET against the stats API and decodes the JSON body.
     *
     * @param string $endpoint Path (and query) relative to the API base URL.
     *
     * @return array
     *
     * @throws RequestException When the API does not answer 200 with a JSON object.
     */
    private function doGet(string $endpoint): array
    {
        $response = $this->request->get($this->apiUrl.$endpoint, $this->headers);
        $status   = $response->getStatusCode();

        if ($status === 200) {
            $decoded = json_decode((string) $response->getBody(), true);
            if (is_array($decoded) === false) {
                throw new RequestException(
                    'Request to GitHub API proxy returned a non-JSON body: GET '.$endpoint
                );
            }

            return $decoded;
        }

        $error = $response->getBody();
        if ($status === -1) {
            $error = $response->getMessage();
        }

        throw new RequestException(
            'Request to GitHub API proxy failed: GET '.$endpoint.' ('.$status.'): '.$error
        );

    }//end doGet()


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
