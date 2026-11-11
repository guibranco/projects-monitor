<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\ShieldsIo;

/**
 * The Cloudflare tile: every domain (zone) of the account, with its status,
 * plan and activation date, read from the Cloudflare API, and the traffic of
 * the last days (requests, cache ratio, bandwidth, visitors and page views),
 * read from the GraphQL Analytics API.
 */
class Cloudflare
{
    private const CLOUDFLARE_API_URL = "https://api.cloudflare.com/client/v4/zones";

    private const CLOUDFLARE_DASHBOARD_URL = "https://dash.cloudflare.com";

    private const CLOUDFLARE_GRAPHQL_URL = "https://api.cloudflare.com/client/v4/graphql";

    /**
     * The days of traffic shown, today included.
     */
    public const ANALYTICS_DAYS = 7;

    /**
     * The zones asked for in one GraphQL query.
     */
    private const ZONES_PER_QUERY = 10;

    /**
     * The daily traffic of the given zones, summed over the period.
     */
    private const ANALYTICS_QUERY = <<<'GRAPHQL'
        query ZoneTraffic($zoneTags: [string!], $since: Date!, $until: Date!, $limit: uint64!) {
          viewer {
            zones(filter: { zoneTag_in: $zoneTags }) {
              zoneTag
              httpRequests1dGroups(limit: $limit, filter: { date_geq: $since, date_leq: $until }) {
                sum { requests cachedRequests bytes pageViews }
                uniq { uniques }
              }
            }
          }
        }
        GRAPHQL;

    private const NO_DATA = "-";

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
        $parameters = [];
        $parameters["account.id"] = $this->accountId;
        $parameters["per_page"]   = self::PAGE_SIZE;
        $parameters["page"]       = $page;
        $parameters["order"]      = "name";
        $parameters["direction"]  = "asc";

        $url      = sprintf("%s?%s", self::CLOUDFLARE_API_URL, http_build_query($parameters));
        $response = $this->request->get($url, $this->headers);

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
     * The daily traffic of some zones, as the GraphQL Analytics API answers it.
     *
     * @param array  $zoneIds The zone IDs (zone tags).
     * @param string $since   The first day, as Y-m-d.
     * @param string $until   The last day, as Y-m-d.
     *
     * @return object The decoded response.
     *
     * @throws RequestException When the API does not answer 200 without errors.
     */
    protected function fetchAnalytics(array $zoneIds, string $since, string $until)
    {
        $variables             = [];
        $variables["zoneTags"] = array_values($zoneIds);
        $variables["since"]    = $since;
        $variables["until"]    = $until;
        $variables["limit"]    = self::ANALYTICS_DAYS;

        $payload = json_encode(["query" => self::ANALYTICS_QUERY, "variables" => $variables]);

        $headers   = $this->headers;
        $headers[] = "Content-Type: application/json";

        $response = $this->request->post(self::CLOUDFLARE_GRAPHQL_URL, $headers, $payload);

        if ($response->getStatusCode() !== 200) {
            $error = $response->getBody();
            if ($response->getStatusCode() === -1) {
                $error = $response->getMessage();
            }

            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");
        }

        $body = json_decode($response->getBody());
        if (is_object($body) === false || empty($body->errors) === false) {
            $errors = json_encode(($body->errors ?? $response->getBody()));
            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$errors}");
        }

        return $body;

    }//end fetchAnalytics()


    /**
     * The traffic of the last ANALYTICS_DAYS days of every zone, summed per
     * zone and keyed by zone ID. Visitors are the sum of the daily unique IPs,
     * like the dashboard's daily rollup.
     *
     * @param array $zones The zones as the API lists them.
     *
     * @return array
     */
    private function getAnalytics(array $zones): array
    {
        $zoneIds = array_values(array_filter(array_map(fn ($zone) => ($zone->id ?? null), $zones)));
        $until   = gmdate("Y-m-d");
        $since   = gmdate("Y-m-d", strtotime(sprintf("-%d days", (self::ANALYTICS_DAYS - 1))));
        $traffic = [];

        foreach (array_chunk($zoneIds, self::ZONES_PER_QUERY) as $chunk) {
            $body = $this->fetchAnalytics($chunk, $since, $until);

            foreach (($body->data->viewer->zones ?? []) as $zone) {
                $totals = [
                    "requests"       => 0,
                    "cachedRequests" => 0,
                    "bytes"          => 0,
                    "pageViews"      => 0,
                    "uniques"        => 0,
                ];

                foreach (($zone->httpRequests1dGroups ?? []) as $day) {
                    $totals["requests"]       += (int) ($day->sum->requests ?? 0);
                    $totals["cachedRequests"] += (int) ($day->sum->cachedRequests ?? 0);
                    $totals["bytes"]          += (int) ($day->sum->bytes ?? 0);
                    $totals["pageViews"]      += (int) ($day->sum->pageViews ?? 0);
                    $totals["uniques"]        += (int) ($day->uniq->uniques ?? 0);
                }

                $traffic[$zone->zoneTag] = $totals;
            }
        }

        return $traffic;

    }//end getAnalytics()


    /**
     * A count in a short form: 950, 1.2K, 3.4M, 5.6B.
     *
     * @param integer $value The count.
     *
     * @return string
     */
    private function formatNumber(int $value): string
    {
        $units = ["", "K", "M", "B", "T"];
        $index = 0;
        $short = (float) $value;

        while ($short >= 1000 && $index < (count($units) - 1)) {
            $short /= 1000;
            $index++;
        }

        if ($index === 0) {
            return (string) $value;
        }

        return rtrim(rtrim(number_format($short, 1, ".", ""), "0"), ".").$units[$index];

    }//end formatNumber()


    /**
     * A size in bytes in a short form: 512 B, 1.5 KB, 2.3 GB.
     *
     * @param integer $bytes The size.
     *
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ["B", "KB", "MB", "GB", "TB", "PB"];
        $index = 0;
        $size  = (float) $bytes;

        while ($size >= 1024 && $index < (count($units) - 1)) {
            $size /= 1024;
            $index++;
        }

        if ($index === 0) {
            return "{$bytes} B";
        }

        return rtrim(rtrim(number_format($size, 1, ".", ""), "0"), ".")." ".$units[$index];

    }//end formatBytes()


    /**
     * The traffic cells of a zone: requests, cache ratio, bandwidth,
     * visitors and page views, or a dash for each when there is no data.
     *
     * @param array|null $totals The zone's traffic, or null.
     *
     * @return array
     */
    private function getTrafficCells(?array $totals): array
    {
        if ($totals === null) {
            return array_fill(0, 5, self::NO_DATA);
        }

        $cached = self::NO_DATA;
        if ($totals["requests"] > 0) {
            $cached = sprintf("%d%%", round(($totals["cachedRequests"] * 100 / $totals["requests"])));
        }

        return [
            $this->formatNumber($totals["requests"]),
            $cached,
            $this->formatBytes($totals["bytes"]),
            $this->formatNumber($totals["uniques"]),
            $this->formatNumber($totals["pageViews"]),
        ];

    }//end getTrafficCells()


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

        if (isset($zone->status) === false) {
            return self::STATE_UNKNOWN;
        }

        return $zone->status;

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
     * dashboard, its state, plan, activation date and traffic.
     *
     * @param object     $zone    A zone as the API lists it.
     * @param ShieldsIo  $shields The badge builder.
     * @param array|null $totals  The zone's traffic, or null.
     *
     * @return array
     */
    private function getRow($zone, ShieldsIo $shields, ?array $totals): array
    {
        $state  = $this->getState($zone);
        $status = $this->mapStatus($state);
        $color  = $this->mapColor($state);
        $name   = htmlspecialchars($zone->name, ENT_QUOTES);

        $badgeUrl  = $shields->generateBadgeUrl($status, $zone->name, $color, "for-the-badge", "white", null);
        $accountId = rawurlencode(($zone->account->id ?? $this->accountId));
        $link      = sprintf("%s/%s/%s", self::CLOUDFLARE_DASHBOARD_URL, $accountId, rawurlencode($zone->name));
        $image     = "<img src='{$badgeUrl}' alt='{$name}' />";
        $badgeLink = "<a href='{$link}' title='{$state}' target='_blank' rel='noopener noreferrer'>{$image}</a>";

        $activated = self::NO_DATA;
        if (empty($zone->activated_on) === false) {
            $activated = date("d/m/Y", strtotime($zone->activated_on));
        }

        $row   = [];
        $row[] = $badgeLink;
        $row[] = $state;
        $row[] = ($zone->plan->name ?? self::NO_DATA);
        $row[] = $activated;

        return array_merge($row, $this->getTrafficCells($totals));

    }//end getRow()


    /**
     * The rows of the tile: a header, then one row per zone sorted by name.
     * When the analytics cannot be read (for example, a token without the
     * Zone Analytics read permission) the zones are still listed, with a
     * dash in every traffic column.
     *
     * @return array
     */
    public function getZones(): array
    {
        $zones = $this->getAllZones();

        $traffic = [];
        try {
            $traffic = $this->getAnalytics($zones);
        } catch (RequestException $exception) {
            LogStream::warning("Cloudflare analytics unavailable", ["error" => $exception->getMessage()], "cloudflare");
        }

        usort($zones, fn ($first, $second) => strcasecmp($first->name, $second->name));

        $shields = new ShieldsIo();

        $header   = [];
        $header[] = "Domain";
        $header[] = "Status";
        $header[] = "Plan";
        $header[] = "Activated";
        $header[] = sprintf("Requests (%dd)", self::ANALYTICS_DAYS);
        $header[] = "Cached";
        $header[] = "Bandwidth";
        $header[] = "Visitors";
        $header[] = "Page views";
        $result   = [$header];

        foreach ($zones as $zone) {
            $result[] = $this->getRow($zone, $shields, ($traffic[($zone->id ?? "")] ?? null));
        }

        return $result;

    }//end getZones()


}//end class
