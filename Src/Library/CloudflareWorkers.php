<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\ShieldsIo;

/**
 * The Cloudflare Workers tile: every Worker of the account with the state of
 * its latest Workers Builds build, read from the Cloudflare API.
 *
 * A Worker not connected to Workers Builds (deployed with wrangler from CI,
 * say) has no builds: it shows as DEPLOYED, dated by its last change.
 *
 * Shares cloudflare.secrets.php with the other Cloudflare tiles: the token
 * needs the account permissions Workers Scripts Read and Workers CI Read.
 */
class CloudflareWorkers extends CloudflareDeploymentTile
{

    private const API_URL = "https://api.cloudflare.com/client/v4/accounts/%s/%s";

    private const CLOUDFLARE_DASHBOARD_URL = "https://dash.cloudflare.com/%s/workers/services/view/%s/production";

    /**
     * The builds read per Worker: the latest is picked among them.
     */
    private const BUILDS_PER_WORKER = 5;

    private const STATE_DEPLOYED = "DEPLOYED";

    private const STATE_UNKNOWN = "UNKNOWN";

    private const BUILD_STOPPED = "stopped";

    private const MESSAGE_NO_WORKERS = "No Workers in this account.";


    /**
     * A GET of the account's part of the API.
     *
     * @param string $path       The path after /accounts/{id}/.
     * @param array  $parameters The query string parameters.
     *
     * @return object The decoded response.
     *
     * @throws RequestException When the API does not answer 200 with success.
     */
    protected function fetch(string $path, array $parameters=[])
    {
        $url = sprintf(self::API_URL, rawurlencode($this->accountId), $path);
        if (empty($parameters) === false) {
            $url = sprintf("%s?%s", $url, http_build_query($parameters));
        }

        return $this->getJson($url);

    }//end fetch()


    /**
     * Every Worker script of the account.
     *
     * @return array
     */
    private function getScripts(): array
    {
        return ($this->fetch("workers/scripts")->result ?? []);

    }//end getScripts()


    /**
     * The account's workers.dev subdomain, or null when it cannot be read.
     *
     * @return string|null
     */
    private function getSubdomain(): ?string
    {
        try {
            $subdomain = ($this->fetch("workers/subdomain")->result->subdomain ?? null);
        } catch (RequestException) {
            return null;
        }

        return (empty($subdomain) === true) ? null : $subdomain;

    }//end getSubdomain()


    /**
     * The custom domains of each Worker, by Worker name; empty when they
     * cannot be read.
     *
     * @return array
     */
    private function getDomains(): array
    {
        try {
            $domains = ($this->fetch("workers/domains")->result ?? []);
        } catch (RequestException) {
            return [];
        }

        $byWorker = [];
        foreach ($domains as $domain) {
            if (empty($domain->service) === false && empty($domain->hostname) === false) {
                $byWorker[$domain->service][] = $domain->hostname;
            }
        }

        return $byWorker;

    }//end getDomains()


    /**
     * The latest Workers Builds build of a Worker, or null when it has none
     * or they cannot be read.
     *
     * @param object $script A Worker script as the API lists it.
     *
     * @return object|null
     */
    private function getLatestBuild($script)
    {
        if (empty($script->tag) === true) {
            return null;
        }

        $parameters             = [];
        $parameters["per_page"] = self::BUILDS_PER_WORKER;
        $parameters["page"]     = 1;

        try {
            $builds = ($this->fetch(sprintf("builds/workers/%s/builds", rawurlencode($script->tag)), $parameters)->result ?? []);
        } catch (RequestException) {
            return null;
        }

        $latest = null;
        foreach ($builds as $build) {
            if ($latest === null || strcmp(($build->created_on ?? ""), ($latest->created_on ?? "")) > 0) {
                $latest = $build;
            }
        }

        return $latest;

    }//end getLatestBuild()


    /**
     * The state shown for a Worker: SUCCESS once its latest build succeeded,
     * BUILD_ and the build status while it runs (BUILD_QUEUED,
     * BUILD_RUNNING...), BUILD_ and the outcome when it did not succeed
     * (BUILD_FAILURE, BUILD_CANCELED...), DEPLOYED without builds.
     *
     * @param object|null $build The latest build.
     *
     * @return string
     */
    private function getState($build): string
    {
        if ($build === null) {
            return self::STATE_DEPLOYED;
        }

        $status = strtolower(($build->status ?? ""));
        if ($status === "") {
            return self::STATE_UNKNOWN;
        }

        if ($status !== self::BUILD_STOPPED) {
            return strtoupper("build_{$status}");
        }

        return match (strtolower(($build->build_outcome ?? ""))) {
            "success" => "SUCCESS",
            "fail" => "BUILD_FAILURE",
            "cancelled" => "BUILD_CANCELED",
            "terminated" => "BUILD_TERMINATED",
            "skipped" => "BUILD_SKIPPED",
            default => self::STATE_UNKNOWN,
        };

    }//end getState()


    /**
     * The URL of a Worker: its first custom domain, falling back to its
     * workers.dev address, then to its page on the dashboard.
     *
     * @param object      $script    A Worker script as the API lists it.
     * @param array       $domains   The custom domains by Worker name.
     * @param string|null $subdomain The account's workers.dev subdomain.
     *
     * @return string
     */
    private function getLink($script, array $domains, ?string $subdomain): string
    {
        if (empty($domains[$script->id]) === false) {
            return "https://{$domains[$script->id][0]}";
        }

        if ($subdomain !== null) {
            return "https://{$script->id}.{$subdomain}.workers.dev";
        }

        return sprintf(self::CLOUDFLARE_DASHBOARD_URL, rawurlencode($this->accountId), rawurlencode($script->id));

    }//end getLink()


    /**
     * The rows of the tile: a header, then one row per Worker sorted by
     * name, or a note when the account has none.
     *
     * @return array
     */
    public function getWorkers(): array
    {
        $scripts = $this->getScripts();

        usort($scripts, fn ($first, $second) => strcasecmp($first->id, $second->id));

        $result = [$this->getHeader()];
        if (empty($scripts) === true) {
            $result[] = $this->getMessageRow(self::MESSAGE_NO_WORKERS);
            return $result;
        }

        $subdomain = $this->getSubdomain();
        $domains   = $this->getDomains();
        $shields   = new ShieldsIo();

        foreach ($scripts as $script) {
            $build  = $this->getLatestBuild($script);
            $branch = ($build->build_trigger_metadata->branch ?? null);
            $date   = ($build === null) ? ($script->modified_on ?? null) : ($build->stopped_on ?? $build->created_on ?? null);
            $link   = $this->getLink($script, $domains, $subdomain);

            $result[] = $this->getBadgeRow($script->id, $link, $this->getState($build), $branch, $date, $shields);
        }

        return $result;

    }//end getWorkers()


    /**
     * The name of the tile in messages.
     *
     * @return string
     */
    protected function getLabel(): string
    {
        return "Cloudflare Workers";

    }//end getLabel()


    /**
     * The title of the first column.
     *
     * @return string
     */
    protected function getFirstColumn(): string
    {
        return "Worker";

    }//end getFirstColumn()


    /**
     * The rows of the tile.
     *
     * @return array
     */
    protected function getRows(): array
    {
        return $this->getWorkers();

    }//end getRows()


}//end class
