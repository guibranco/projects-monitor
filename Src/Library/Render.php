<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use GuiBranco\Pancake\ShieldsIo;

/**
 * The Render tile: every service of the account with the state of its latest
 * deploy, overridden by the service's suspended flag.
 */
class Render
{
    private const RENDER_API_URL = "https://api.render.com/v1";

    private const PAGE_SIZE = 100;

    private const BATCH_SIZE = 10;

    private const STATE_SUSPENDED = "SUSPENDED";

    private const STATE_UNKNOWN = "UNKNOWN";

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
     * Reads the API token from the secrets file.
     *
     * @throws SecretsFileNotFoundException When the secrets file is missing.
     */
    public function __construct()
    {
        $config = new Configuration();
        $config->init();

        global $renderToken;

        $secrets = __DIR__."/../secrets/render.secrets.php";
        if (file_exists($secrets) === false) {
            throw new SecretsFileNotFoundException("File not found: render.secrets.php");
        }

        include_once $secrets;

        $this->request   = new Request();
        $this->headers   = [];
        $this->headers[] = "Authorization: Bearer {$renderToken}";
        $this->headers[] = "Accept: application/json";
        $this->headers[] = constant("USER_AGENT");

    }//end __construct()


    /**
     * Fails with the status code and the body, or the transport error, of
     * an answer other than 200.
     *
     * @param Response $response The answer of the API.
     *
     * @return void
     *
     * @throws RequestException When the API does not answer 200.
     */
    private function ensureSuccess(Response $response): void
    {
        if ($response->getStatusCode() === 200) {
            return;
        }

        $error = $response->getBody();
        if ($response->getStatusCode() === -1) {
            $error = $response->getMessage();
        }

        throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");

    }//end ensureSuccess()


    /**
     * The services of the account, as the API lists them: every page,
     * following the cursor of the last item until a page comes back short.
     *
     * @return array The services, without the cursor wrapper.
     *
     * @throws RequestException When the API does not answer 200.
     */
    protected function fetchServices(): array
    {
        $services = [];
        $cursor   = null;

        do {
            $url = sprintf("%s/services?limit=%d", self::RENDER_API_URL, self::PAGE_SIZE);
            if ($cursor !== null) {
                $url .= "&cursor=".rawurlencode($cursor);
            }

            $response = $this->request->get($url, $this->headers);
            $this->ensureSuccess($response);

            $page = json_decode($response->getBody());
            if (is_array($page) === false) {
                break;
            }

            foreach ($page as $item) {
                if (isset($item->service) === true) {
                    $services[] = $item->service;
                }
            }

            $cursor = (end($page)->cursor ?? null);
        } while (count($page) === self::PAGE_SIZE && $cursor !== null);

        return $services;

    }//end fetchServices()


    /**
     * The latest deploy of each service, requested in parallel batches. A
     * service whose request failed, or that was never deployed, is left
     * out.
     *
     * @param array $serviceIds The IDs of the services.
     *
     * @return array Deploy keyed by service ID.
     */
    protected function fetchLatestDeploys(array $serviceIds): array
    {
        $deploys = [];
        foreach (array_chunk($serviceIds, self::BATCH_SIZE) as $chunk) {
            $request = new Request();
            foreach ($chunk as $serviceId) {
                $url = sprintf("%s/services/%s/deploys?limit=1", self::RENDER_API_URL, rawurlencode($serviceId));
                $request->addRequest($serviceId, $url, $this->headers);
            }

            foreach ($request->executeBatch() as $serviceId => $response) {
                if ($response->getStatusCode() !== 200) {
                    continue;
                }

                $list = json_decode($response->getBody());
                if (isset($list[0]->deploy) === true) {
                    $deploys[$serviceId] = $list[0]->deploy;
                }
            }
        }

        return $deploys;

    }//end fetchLatestDeploys()


    /**
     * The URL of a service: the public URL of a web service or static site,
     * falling back to its page on the dashboard (background workers, cron
     * jobs and private services have no public URL).
     *
     * @param object $service A service as the API lists it.
     *
     * @return string|null
     */
    private function getLink($service): ?string
    {
        return ($service->serviceDetails->url ?? $service->dashboardUrl ?? null);

    }//end getLink()


    /**
     * The state shown for a service. The suspended flag wins over the
     * deploy: a suspended service keeps its last deploy live while nothing
     * is served. Deploy statuses are shown in upper case (LIVE,
     * BUILD_FAILED, UPDATE_IN_PROGRESS...).
     *
     * @param object      $service A service as the API lists it.
     * @param object|null $deploy  Its latest deploy.
     *
     * @return string
     */
    private function getState($service, $deploy): string
    {
        if (($service->suspended ?? null) === "suspended") {
            return self::STATE_SUSPENDED;
        }

        if (empty($deploy->status) === true) {
            return self::STATE_UNKNOWN;
        }

        return strtoupper($deploy->status);

    }//end getState()


    /**
     * The icon of a state.
     *
     * @param string $state The state shown for a service.
     *
     * @return string
     */
    private function mapStatus(string $state): string
    {
        return match ($state) {
            "LIVE" => "✅",
            "BUILD_FAILED", "UPDATE_FAILED", "PRE_DEPLOY_FAILED" => "❌",
            "BUILD_IN_PROGRESS", "UPDATE_IN_PROGRESS", "PRE_DEPLOY_IN_PROGRESS" => "🔨",
            "CREATED", "QUEUED" => "⏳",
            "CANCELED" => "⏸",
            "DEACTIVATED" => "💤",
            self::STATE_SUSPENDED => "⛔",
            default => "❓",
        };

    }//end mapStatus()


    /**
     * The badge colour of a state: red for every failure and for a
     * suspended service.
     *
     * @param string $state The state shown for a service.
     *
     * @return string
     */
    private function mapColor(string $state): string
    {
        return match ($state) {
            "LIVE" => "green",
            "BUILD_IN_PROGRESS", "UPDATE_IN_PROGRESS", "PRE_DEPLOY_IN_PROGRESS" => "yellow",
            "CREATED", "QUEUED" => "blue",
            "CANCELED", "DEACTIVATED" => "gray",
            "BUILD_FAILED", "UPDATE_FAILED", "PRE_DEPLOY_FAILED", self::STATE_SUSPENDED => "red",
            default => "lightgray",
        };

    }//end mapColor()


    /**
     * The date of a deploy: when it finished, or when it was created while
     * it is still running.
     *
     * @param object|null $deploy The latest deploy of a service.
     *
     * @return string
     */
    private function getLastDeployment($deploy): string
    {
        $date = ($deploy->finishedAt ?? $deploy->createdAt ?? null);
        if ($date === null) {
            return "-";
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return "-";
        }

        return date("H:i:s d/m/Y", $timestamp);

    }//end getLastDeployment()


    /**
     * The row of a service: its badge, linked to its URL, its state and
     * the date of its latest deploy.
     *
     * @param object      $service A service as the API lists it.
     * @param object|null $deploy  Its latest deploy.
     * @param ShieldsIo   $shields The badge builder.
     *
     * @return array
     */
    private function getRow($service, $deploy, ShieldsIo $shields): array
    {
        $state  = $this->getState($service, $deploy);
        $status = $this->mapStatus($state);
        $color  = $this->mapColor($state);

        $badgeUrl  = $shields->generateBadgeUrl($status, $service->name, $color, "for-the-badge", "white", null);
        $badgeLink = "<img src='{$badgeUrl}' alt='{$service->name}' />";
        $link      = $this->getLink($service);
        if ($link !== null) {
            $badgeLink = "<a href='{$link}' title='{$state}' target='_blank' rel='noopener noreferrer'>{$badgeLink}</a>";
        }

        $row   = [];
        $row[] = $badgeLink;
        $row[] = $state;
        $row[] = $this->getLastDeployment($deploy);

        return $row;

    }//end getRow()


    /**
     * The rows of the tile: a header, then one row per service sorted by
     * name.
     *
     * @return array
     */
    public function getServices(): array
    {
        $services = $this->fetchServices();

        usort($services, fn ($first, $second) => strcasecmp($first->name, $second->name));

        $deploys = $this->fetchLatestDeploys(array_map(fn ($service) => $service->id, $services));
        $shields = new ShieldsIo();

        $header   = [];
        $header[] = "Service";
        $header[] = "Status";
        $header[] = "Last Deployment";
        $result   = [$header];

        foreach ($services as $service) {
            $result[] = $this->getRow($service, ($deploys[$service->id] ?? null), $shields);
        }

        return $result;

    }//end getServices()


}//end class
