<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\Library\Configuration;

/**
 * Base for clients of the estate's bearer-token protected JSON APIs whose URL
 * and token live in a secrets file (WebhooksHandler, github-api-proxy): loads
 * the secrets, builds the request headers and performs authenticated GETs.
 */
abstract class BearerTokenApiClient
{

    /**
     * Base URL of the API, with a trailing slash.
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * Request headers sent on every call (bearer token, accept, user agent).
     *
     * @var array<int, string>
     */
    protected $headers;

    /**
     * HTTP client.
     *
     * @var Request
     */
    protected $request;

    /**
     * Variables read from each secrets file, by path, so every client
     * instance in a request gets them although the file is included once.
     *
     * @var array<string, array<string, mixed>>
     */
    private static $secretsCache = [];


    /**
     * Name used in error messages, e.g. "WebhooksHandler API".
     *
     * @return string
     */
    abstract protected function serviceName(): string;


    /**
     * Loads the URL and token from a secrets file in Src/secrets and prepares
     * the headers and HTTP client.
     *
     * @param string  $secretsFile    File name inside Src/secrets.
     * @param string  $urlVariable    Name of the variable holding the base URL.
     * @param string  $tokenVariable  Name of the variable holding the bearer token.
     * @param integer $timeoutSeconds Total transfer timeout per request; 0 keeps Pancake's default (no limit).
     * @param boolean $requireHttps   Refuse a plain-HTTP URL unless the host is loopback.
     *
     * @return void
     *
     * @throws SecretsFileNotFoundException When the secrets file is missing.
     * @throws RequestException             When $requireHttps is set and the URL is not HTTPS.
     */
    protected function initialise(
        string $secretsFile,
        string $urlVariable,
        string $tokenVariable,
        int $timeoutSeconds=0,
        bool $requireHttps=false
    ): void {
        $config = new Configuration();
        $config->init();

        $path = __DIR__.'/../secrets/'.$secretsFile;
        if (file_exists($path) === false) {
            throw new SecretsFileNotFoundException('File not found: '.$secretsFile);
        }

        $secrets = self::readSecrets($path);

        // Secrets pasted with a stray newline or space would put a control
        // character into the URL or the Authorization header; older libcurl
        // sends it as-is and nginx answers a bare 400 Bad Request.
        $this->apiUrl = rtrim(trim((string) ($secrets[$urlVariable] ?? '')), '/').'/';
        if ($requireHttps === true) {
            self::requireSecureUrl($this->apiUrl, $this->serviceName());
        }

        $this->headers = [
                          'Authorization: Bearer '.trim((string) ($secrets[$tokenVariable] ?? '')),
                          'Accept: application/json',
                          'Cache-Control: no-cache',
                          constant('USER_AGENT'),
                         ];
        $this->request = new Request();
        if ($timeoutSeconds > 0) {
            $this->request->setTimeout($timeoutSeconds);
        }

    }//end initialise()


    /**
     * Refuses to send a bearer token over plain HTTP: the URL must be HTTPS,
     * except for loopback hosts (local development, same-host services).
     *
     * @param string $url     Base URL.
     * @param string $service Service name for the error message.
     *
     * @return void
     *
     * @throws RequestException When the URL is not HTTPS and not loopback.
     */
    public static function requireSecureUrl(string $url, string $service): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host   = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));

        if ($scheme === 'https' && $host !== '') {
            return;
        }

        $loopback = ($host === 'localhost' || $host === '::1' || str_starts_with($host, '127.'));
        if ($scheme === 'http' && $loopback === true) {
            return;
        }

        throw new RequestException(
            $service.' URL must use HTTPS (plain HTTP is only allowed for loopback hosts): '.$scheme.'://'.$host
        );

    }//end requireSecureUrl()


    /**
     * Returns the variables a secrets file assigns. The file is included once,
     * in an isolated scope, and its variables are cached for later instances.
     *
     * @param string $path Absolute path of the secrets file.
     *
     * @return array<string, mixed>
     */
    private static function readSecrets(string $path): array
    {
        if (isset(self::$secretsCache[$path]) === false) {
            self::$secretsCache[$path] = (static function (string $secretsPath): array {
                include_once $secretsPath;

                $variables = get_defined_vars();
                unset($variables['secretsPath']);

                return $variables;
            })($path);
        }

        return self::$secretsCache[$path];

    }//end readSecrets()


    /**
     * Performs a GET against the API and decodes the JSON body.
     *
     * @param string $endpoint Path (and query) relative to the API base URL.
     *
     * @return array
     *
     * @throws RequestException When the API does not answer 200 with a JSON object.
     */
    protected function doGet(string $endpoint): array
    {
        $response = $this->request->get($this->apiUrl.$endpoint, $this->headers);
        $status   = $response->getStatusCode();

        if ($status === 200) {
            $decoded = json_decode((string) $response->getBody(), true);
            if (is_array($decoded) === false) {
                throw new RequestException(
                    'Request to '.$this->serviceName().' returned a non-JSON body: GET '.$endpoint
                );
            }

            return $decoded;
        }

        $error = $response->getBody();
        if ($status === -1) {
            $error = $response->getMessage();
        }

        throw new RequestException(
            'Request to '.$this->serviceName().' failed: GET '.$endpoint.' ('.$status.'): '.$error
        );

    }//end doGet()


}//end class
