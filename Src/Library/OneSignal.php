<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;

/**
 * Sends web push notifications through OneSignal's REST API to the
 * dashboard's subscribers. The App ID, the App API key, the segment and the
 * dashboard's URL come from Src/secrets/oneSignal.secrets.php; without that
 * file (or without a key) the client is not configured and sends nothing.
 */
class OneSignal
{
    public const API_URL = "https://api.onesignal.com/notifications?c=push";

    public const DEFAULT_SEGMENT = "Total Subscriptions";

    private const SECRETS_FILE = __DIR__."/../secrets/oneSignal.secrets.php";

    /**
     * The OneSignal App ID.
     *
     * @var string
     */
    private string $appId = "";

    /**
     * The App API key.
     *
     * @var string
     */
    private string $apiKey = "";

    /**
     * The segment that receives the pushes.
     *
     * @var string
     */
    private string $segment = self::DEFAULT_SEGMENT;

    /**
     * The dashboard's base URL, with a trailing slash, or "" when unknown.
     *
     * @var string
     */
    private string $dashboardUrl = "";

    /**
     * The HTTP client.
     *
     * @var Request
     */
    private Request $request;

    /**
     * Variables read from each secrets file, by path (a file is included once per request).
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $secretsCache = [];


    /**
     * Reads the secrets file, when there is one.
     *
     * @param Request|null $request     The HTTP client (a new one when null).
     * @param string|null  $secretsFile The secrets file (the default one when null).
     */
    public function __construct(?Request $request=null, ?string $secretsFile=null)
    {
        $this->request = ($request ?? new Request());
        $this->request->setTimeout(15);

        $path = ($secretsFile ?? self::SECRETS_FILE);
        if (file_exists($path) === false) {
            return;
        }

        $secrets = self::readSecrets($path);

        $this->appId  = trim((string) ($secrets["oneSignalAppId"] ?? ""));
        $this->apiKey = trim((string) ($secrets["oneSignalApiKey"] ?? ""));
        $segment      = trim((string) ($secrets["oneSignalSegment"] ?? ""));
        if ($segment !== "") {
            $this->segment = $segment;
        }

        $dashboardUrl = trim((string) ($secrets["oneSignalSiteUrl"] ?? ""));
        if ($dashboardUrl !== "") {
            $this->dashboardUrl = rtrim($dashboardUrl, "/")."/";
        }

    }//end __construct()


    /**
     * The variables a secrets file defines, read once per path.
     *
     * @param string $path The secrets file.
     *
     * @return array
     */
    private static function readSecrets(string $path): array
    {
        if (isset(self::$secretsCache[$path]) === false) {
            self::$secretsCache[$path] = (static function (string $secretsPath): array {
                include_once $secretsPath;

                $variables = get_defined_vars();
                unset($variables["secretsPath"]);

                return $variables;
            })($path);
        }

        return self::$secretsCache[$path];

    }//end readSecrets()


    /**
     * Whether an App ID and an App API key are set.
     *
     * @return boolean
     */
    public function isConfigured(): bool
    {
        return $this->appId !== "" && $this->apiKey !== "";

    }//end isConfigured()


    /**
     * The request body of a push.
     *
     * @param string $heading        The title.
     * @param string $content        The message.
     * @param string $link           A dashboard link (relative, e.g. "ops.php#healthchecks") or "".
     * @param string $idempotencyKey A UUID; OneSignal drops a repeat with the same key.
     *
     * @return array
     */
    public function buildPayload(string $heading, string $content, string $link, string $idempotencyKey): array
    {
        $payload = [];

        $payload["app_id"]            = $this->appId;
        $payload["included_segments"] = [$this->segment];
        $payload["headings"]          = ["en" => mb_substr($heading, 0, 100)];
        $payload["contents"]          = ["en" => mb_substr($content, 0, 400)];
        $payload["idempotency_key"]   = $idempotencyKey;

        if ($this->dashboardUrl !== "" && preg_match("#^https://#i", $this->dashboardUrl) === 1) {
            $payload["url"] = $this->dashboardUrl.ltrim($link, "/");
        }

        return $payload;

    }//end buildPayload()


    /**
     * Sends a push.
     *
     * @param string $heading        The title.
     * @param string $content        The message.
     * @param string $link           A dashboard link or "".
     * @param string $idempotencyKey A UUID.
     *
     * @return void
     *
     * @throws RequestException When it is not configured or OneSignal refuses the push.
     */
    public function send(string $heading, string $content, string $link, string $idempotencyKey): void
    {
        if ($this->isConfigured() === false) {
            throw new RequestException("OneSignal is not configured");
        }

        $headers  = [
                     "Authorization: Key ".$this->apiKey,
                     "Content-Type: application/json",
                     "Accept: application/json",
                    ];
        $body     = json_encode($this->buildPayload($heading, $content, $link, $idempotencyKey));
        $response = $this->request->post(self::API_URL, $headers, $body);
        if ($response->isSuccessStatusCode() === false) {
            throw new RequestException(sprintf("OneSignal push failed: HTTP %d %s", $response->getStatusCode(), (string) $response->getBody()));
        }

    }//end send()


}//end class
