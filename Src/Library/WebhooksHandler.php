<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\Library\Configuration;

/**
 * Client for the WebhooksHandler (guibranco/WebhooksHandler) bearer-token
 * protected /broker endpoints — the C# service that forwards GitHub webhook
 * payloads to the LavinMQ brokers. Distinct from Webhooks (the PHP consumer's
 * API) and RabbitMq (the brokers' own management API).
 */
class WebhooksHandler
{

    /**
     * Base URL of the handler, with a trailing slash.
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
     * Loads the handler URL and token from webhooksHandler.secrets.php.
     *
     * @throws SecretsFileNotFoundException When the secrets file is missing.
     */
    public function __construct()
    {
        global $handlerApiUrl, $handlerApiToken;

        $config = new Configuration();
        $config->init();

        $secretsFile = __DIR__.'/../secrets/webhooksHandler.secrets.php';
        if (file_exists($secretsFile) === false) {
            throw new SecretsFileNotFoundException('File not found: webhooksHandler.secrets.php');
        }

        include_once $secretsFile;

        $this->apiUrl  = rtrim($handlerApiUrl, '/').'/';
        $this->headers = [
            'Authorization: Bearer '.$handlerApiToken,
            'Accept: application/json',
            'Cache-Control: no-cache',
            constant('USER_AGENT'),
        ];
        $this->request = new Request();

    }//end __construct()


    /**
     * Performs a GET against the handler and decodes the JSON body.
     *
     * @param string $endpoint Path relative to the handler base URL.
     *
     * @return array
     *
     * @throws RequestException When the handler does not answer 200.
     */
    private function doGet(string $endpoint): array
    {
        $response = $this->request->get($this->apiUrl.$endpoint, $this->headers);
        $status   = $response->getStatusCode();

        if ($status === 200) {
            $decoded = json_decode($response->getBody(), true);
            if (is_array($decoded) === true) {
                return $decoded;
            }

            return [];
        }

        $error = $response->getBody();
        if ($status === -1) {
            $error = $response->getMessage();
        }

        throw new RequestException(
            'Request to WebhooksHandler API failed: GET '.$endpoint.' ('.$status.'): '.$error
        );

    }//end doGet()


    /**
     * Current-month broker usage for every configured broker connection, as
     * reported by GET /broker/usage/current, rendered for the dashboard
     * (see BrokerUsagePresenter).
     *
     * @return array
     */
    public function getBrokerUsage(): array
    {
        $usage     = $this->doGet('broker/usage/current');
        $presenter = new BrokerUsagePresenter();

        return $presenter->present($usage);

    }//end getBrokerUsage()


}//end class
