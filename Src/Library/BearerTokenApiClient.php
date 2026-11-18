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
     * Name used in error messages, e.g. "WebhooksHandler API".
     *
     * @return string
     */
    abstract protected function serviceName(): string;


    /**
     * Loads the URL and token from a secrets file in Src/secrets and prepares
     * the headers and HTTP client.
     *
     * @param string $secretsFile   File name inside Src/secrets.
     * @param string $urlVariable   Name of the variable holding the base URL.
     * @param string $tokenVariable Name of the variable holding the bearer token.
     *
     * @return void
     *
     * @throws SecretsFileNotFoundException When the secrets file is missing.
     */
    protected function initialise(string $secretsFile, string $urlVariable, string $tokenVariable): void
    {
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
        $this->apiUrl  = rtrim(trim((string) ($secrets[$urlVariable] ?? '')), '/').'/';
        $this->headers = [
                          'Authorization: Bearer '.trim((string) ($secrets[$tokenVariable] ?? '')),
                          'Accept: application/json',
                          'Cache-Control: no-cache',
                          constant('USER_AGENT'),
                         ];
        $this->request = new Request();

    }//end initialise()


    /**
     * Returns the variables a secrets file assigns. The file is included in
     * this isolated scope on every call, so several clients (or several
     * instances of one) can read it in the same request.
     *
     * @param string $path Absolute path of the secrets file.
     *
     * @return array<string, mixed>
     */
    private static function readSecrets(string $path): array
    {
        return (static function (string $secretsPath): array {
            include $secretsPath;

            $variables = get_defined_vars();
            unset($variables['secretsPath']);

            return $variables;
        })($path);

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
