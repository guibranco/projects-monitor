<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;

/**
 * What a visitor gets from each Vercel production URL, cached per URL.
 *
 * A project the platform stopped serving keeps its deployment in the READY
 * state: paused by hand or by spend management it answers 503
 * DEPLOYMENT_PAUSED, disabled once the plan's usage limits are exceeded it
 * answers 402 DEPLOYMENT_DISABLED. Requesting the URL itself is the only way
 * to see that, so each URL is requested once and the answer reused for
 * CACHE_TTL_SECONDS: the dashboard refreshes the Vercel tile every 60
 * seconds, and every probe is a request (and, for function-backed projects,
 * an invocation) counted against the quota that caused the pause.
 */
class VercelProbe
{
    public const CACHE_TTL_SECONDS = 600;

    private const BATCH_SIZE = 10;

    /**
     * The cache file, one entry per URL.
     *
     * @var string
     */
    private $cachePath;


    /**
     * Builds the probe on the given cache file, or on the default one.
     *
     * @param string|null $cachePath The cache file to read and write.
     */
    public function __construct(?string $cachePath=null)
    {
        $this->cachePath = $cachePath;
        if ($this->cachePath === null) {
            $this->cachePath = __DIR__."/../cache/vercel_probe.json";
        }

    }//end __construct()


    /**
     * The answer of each link, served from the cache while fresh and
     * requested again only for the links missing from it or expired.
     *
     * @param array $links URL keyed by project name.
     *
     * @return array Answer (or null) keyed by project name: {checkedAt: integer, statusCode: integer, vercelError: string|null}.
     */
    public function probe(array $links): array
    {
        $cached  = $this->readCache();
        $pending = array_values(array_unique(array_diff($links, array_keys($cached))));

        if (count($pending) > 0) {
            $cached = array_merge($cached, $this->executeProbe($pending));
            $this->writeCache($cached);
        }

        $results = [];
        foreach ($links as $name => $url) {
            $results[$name] = ($cached[$url] ?? null);
        }

        return $results;

    }//end probe()


    /**
     * Requests the URLs the way a visitor would, in parallel batches, and
     * describes what each one answered.
     *
     * @param array $urls The URLs to request.
     *
     * @return array Answer keyed by URL.
     */
    protected function executeProbe(array $urls): array
    {
        $results = [];
        foreach (array_chunk($urls, self::BATCH_SIZE) as $chunk) {
            foreach ($this->sendBatch($chunk) as $url => $response) {
                $results[$url] = $this->describe($response);
            }
        }

        return $results;

    }//end executeProbe()


    /**
     * Sends one batch of GET requests in parallel. Only the User-Agent goes
     * with them: these are the projects themselves, not the API.
     *
     * @param array $urls At most BATCH_SIZE URLs.
     *
     * @return array Response keyed by URL.
     */
    protected function sendBatch(array $urls): array
    {
        $request = new Request();
        foreach ($urls as $url) {
            $request->addRequest($url, $url, [constant("USER_AGENT")]);
        }

        return $request->executeBatch();

    }//end sendBatch()


    /**
     * The status code and the platform's error code of an answer.
     *
     * @param Response $response The answer of one URL.
     *
     * @return array {checkedAt: integer, statusCode: integer, vercelError: string|null}
     */
    private function describe(Response $response): array
    {
        $result = [];

        $result["checkedAt"]   = time();
        $result["statusCode"]  = $response->getStatusCode();
        $result["vercelError"] = $this->getHeader($response, "x-vercel-error");

        return $result;

    }//end describe()


    /**
     * A response header by name, whatever its case, or null when absent.
     *
     * @param Response $response The answer of one URL.
     * @param string   $name     The header name.
     *
     * @return string|null
     */
    private function getHeader(Response $response, string $name): ?string
    {
        $headers = $response->getHeaders();
        if ($headers === null) {
            return null;
        }

        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return trim((string) $value);
            }
        }

        return null;

    }//end getHeader()


    /**
     * The cached answers still within the TTL, keyed by URL. A missing,
     * unreadable or malformed file counts as empty.
     *
     * @return array
     */
    private function readCache(): array
    {
        if (file_exists($this->cachePath) === false) {
            return [];
        }

        $contents = file_get_contents($this->cachePath);
        if ($contents === false) {
            return [];
        }

        $entries = json_decode($contents, true);
        if (is_array($entries) === false) {
            return [];
        }

        $threshold = (time() - self::CACHE_TTL_SECONDS);
        $fresh     = [];
        foreach ($entries as $url => $entry) {
            if (is_array($entry) === true && ($entry["checkedAt"] ?? 0) > $threshold) {
                $fresh[$url] = $entry;
            }
        }

        return $fresh;

    }//end readCache()


    /**
     * Writes the answers through a temporary file and a rename, so a
     * concurrent request never reads a half-written file. Requests that
     * failed (status code -1) are left out, so they are retried on the next
     * refresh instead of being reported for the whole TTL.
     *
     * @param array $entries Answer keyed by URL.
     *
     * @return void
     */
    private function writeCache(array $entries): void
    {
        $kept = [];
        foreach ($entries as $url => $entry) {
            if ($entry["statusCode"] >= 0) {
                $kept[$url] = $entry;
            }
        }

        $directory = dirname($this->cachePath);
        if (is_dir($directory) === false) {
            mkdir($directory, 0755, true);
        }

        $temporary = sprintf("%s.%s.tmp", $this->cachePath, uniqid("", true));
        if (file_put_contents($temporary, json_encode($kept)) === false) {
            return;
        }

        rename($temporary, $this->cachePath);

    }//end writeCache()


}//end class
