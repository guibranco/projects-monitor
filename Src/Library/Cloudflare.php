<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\ShieldsIo;

/**
 * The Cloudflare tile: every domain (zone) of the account, with its status,
 * plan and activation date, read from the Cloudflare API.
 */
class Cloudflare
{
    private const CLOUDFLARE_API_URL = "https://api.cloudflare.com/client/v4/zones";

    private const CLOUDFLARE_DASHBOARD_URL = "https://dash.cloudflare.com";

    /**
     * The largest page the zones endpoint accepts.
     */
    private const PAGE_SIZE = 50;

    /**
     * A safety net against an API that keeps reporting more pages.
     */
    private const MAX_PAGES = 20;

    private const STATE_PAUSED = "paused";

    private const STATE_UNKNOWN = "unknown";

    /**
     * The HTTP client of the API.
     *
     * @var Request
     */
    private $request;

    /**
     * The headers of every API request.
     *
     * @var array
     */
    private $headers;

    /**
     * The account whose zones are listed.
     *
     * @var string
     */
    private $accountId;


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
     * One page of the account's zones, as the API lists them.
     *
     * @param integer $page The page to fetch, starting at 1.
     *
     * @return object The decoded response.
     *
     * @throws RequestException When the API does not answer 200 with success.
     */
    protected function fetchZones(int $page)
    {
        $query = http_build_query(
            [
                "account.id" => $this->accountId,
                "per_page"   => self::PAGE_SIZE,
                "page"       => $page,
                "order"      => "name",
                "direction"  => "asc",
            ]
        );

        $response = $this->request->get(self::CLOUDFLARE_API_URL."?".$query, $this->headers);

        if ($response->getStatusCode() !== 200) {
            $error = $response->getBody();
            if ($response->getStatusCode() === -1) {
                $error = $response->getMessage();
            }

            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");
        }

        $body = json_decode($response->getBody());
        if (($body->success ?? false) !== true) {
            $errors = json_encode($body->errors ?? []);
            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$errors}");
        }

        return $body;

    }//end fetchZones()


    /**
     * Every zone of the account, across all pages.
     *
     * @return array
     */
    private function getAllZones(): array
    {
        $zones = [];
        $page  = 1;

        do {
            $response   = $this->fetchZones($page);
            $zones      = array_merge($zones, ($response->result ?? []));
            $totalPages = (int) ($response->result_info->total_pages ?? 1);
            $page++;
        } while ($page <= $totalPages && $page <= self::MAX_PAGES);

        return $zones;

    }//end getAllZones()


    /**
     * The state shown for a zone: paused wins over the zone status, which
     * stays "active" while Cloudflare only resolves DNS for a paused zone.
     *
     * @param object $zone A zone as the API lists it.
     *
     * @return string
     */
    private function getState($zone): string
    {
        if (($zone->paused ?? false) === true) {
            return self::STATE_PAUSED;
        }

        return ($zone->status ?? self::STATE_UNKNOWN);

    }//end getState()


    /**
     * The icon of a state.
     *
     * @param string $state The state shown for a zone.
     *
     * @return string
     */
    private function mapStatus(string $state): string
    {
        return match ($state) {
            "active" => "✅",
            "pending" => "⏳",
            "initializing" => "🆕",
            "moved" => "🚚",
            "deactivated", "deleted" => "❌",
            "read only" => "🔒",
            self::STATE_PAUSED => "⏸",
            default => "❓",
        };

    }//end mapStatus()


    /**
     * The badge colour of a state.
     *
     * @param string $state The state shown for a zone.
     *
     * @return string
     */
    private function mapColor(string $state): string
    {
        return match ($state) {
            "active" => "green",
            "pending" => "yellow",
            "initializing" => "blue",
            self::STATE_PAUSED, "read only" => "gray",
            "moved", "deactivated", "deleted" => "red",
            default => "lightgray",
        };

    }//end mapColor()


    /**
     * The row of a zone: its badge, linked to the zone in the Cloudflare
     * dashboard, its state, plan and activation date.
     *
     * @param object    $zone    A zone as the API lists it.
     * @param ShieldsIo $shields The badge builder.
     *
     * @return array
     */
    private function getRow($zone, ShieldsIo $shields): array
    {
        $state  = $this->getState($zone);
        $status = $this->mapStatus($state);
        $color  = $this->mapColor($state);
        $name   = htmlspecialchars($zone->name, ENT_QUOTES);

        $badgeUrl  = $shields->generateBadgeUrl($status, $zone->name, $color, "for-the-badge", "white", null);
        $accountId = rawurlencode(($zone->account->id ?? $this->accountId));
        $link      = self::CLOUDFLARE_DASHBOARD_URL."/{$accountId}/".rawurlencode($zone->name);
        $badgeLink = "<a href='{$link}' title='{$state}' target='_blank' rel='noopener noreferrer'>"
            ."<img src='{$badgeUrl}' alt='{$name}' /></a>";

        $activated = "-";
        if (empty($zone->activated_on) === false) {
            $activated = date("d/m/Y", strtotime($zone->activated_on));
        }

        $row   = [];
        $row[] = $badgeLink;
        $row[] = $state;
        $row[] = ($zone->plan->name ?? "-");
        $row[] = $activated;

        return $row;

    }//end getRow()


    /**
     * The rows of the tile: a header, then one row per zone sorted by name.
     *
     * @return array
     */
    public function getZones(): array
    {
        $zones = $this->getAllZones();

        usort($zones, fn ($first, $second) => strcasecmp($first->name, $second->name));

        $shields = new ShieldsIo();

        $header   = [];
        $header[] = "Domain";
        $header[] = "Status";
        $header[] = "Plan";
        $header[] = "Activated";
        $result   = [$header];

        foreach ($zones as $zone) {
            $result[] = $this->getRow($zone, $shields);
        }

        return $result;

    }//end getZones()


}//end class
