<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;

/**
 * The base of the Cloudflare tiles: the account ID and the API token read
 * from cloudflare.secrets.php, the HTTP client and headers built from them,
 * and the decoding of a v4 API answer.
 */
abstract class CloudflareApiClient
{

    /**
     * The HTTP client of the API.
     *
     * @var Request
     */
    protected $request;

    /**
     * The headers of every API request.
     *
     * @var array
     */
    protected $headers;

    /**
     * The account whose resources are listed.
     *
     * @var string
     */
    protected $accountId;


    /**
     * Reads the account ID and the API token from the secrets file.
     *
     * @throws SecretsFileNotFoundException When the secrets file is missing.
     */
    public function __construct()
    {
        $config = new Configuration();
        $config->init();

        global $cloudflareAccountId, $cloudflareApiToken;

        $secrets = __DIR__."/../secrets/cloudflare.secrets.php";
        if (file_exists($secrets) === false) {
            throw new SecretsFileNotFoundException("File not found: cloudflare.secrets.php");
        }

        include_once $secrets;

        $this->accountId = $cloudflareAccountId;
        $this->request   = new Request();
        $this->headers   = [];
        $this->headers[] = "Authorization: Bearer {$cloudflareApiToken}";
        $this->headers[] = "Accept: application/json";
        $this->headers[] = constant("USER_AGENT");

    }//end __construct()


    /**
     * Sends a GET request to the v4 API and decodes its answer.
     *
     * @param string $url The URL, query string included.
     *
     * @return object The decoded response.
     *
     * @throws RequestException When the API does not answer 200 with success.
     */
    protected function getJson(string $url)
    {
        $response = $this->request->get($url, $this->headers);

        return $this->decodeResponse($response);

    }//end getJson()


    /**
     * The decoded answer of the v4 API.
     *
     * @param Response $response The answer.
     *
     * @return object
     *
     * @throws RequestException When the API does not answer 200 with success.
     */
    protected function decodeResponse(Response $response)
    {
        if ($response->getStatusCode() !== 200) {
            $error = $response->getBody();
            if ($response->getStatusCode() === -1) {
                $error = $response->getMessage();
            }

            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");
        }

        $body = json_decode($response->getBody());
        if (($body->success ?? false) !== true) {
            $errors = json_encode(($body->errors ?? []));
            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$errors}");
        }

        return $body;

    }//end decodeResponse()


}//end class
