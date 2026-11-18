<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Base URL for GitHub REST API calls. In production this is the
 * github-api-proxy (guibranco/github-api-proxy) pass-through host, so every
 * call the dashboard makes is recorded there; the proxy forwards it to
 * api.github.com unchanged and the client keeps its own token.
 *
 * The URL comes from $gitHubApiUrl in gitHub.secrets.php (written by the
 * deploy workflow). When it is missing or empty, calls go straight to
 * api.github.com, so local development works without the proxy.
 *
 * Not to be confused with $githubApiProxyUrl (githubApiProxy.secrets.php),
 * which is the proxy's stats API read by GitHubApiProxy.
 */
class GitHubApiUrl
{

    /**
     * GitHub's own API host, used when no proxy URL is configured.
     */
    public const DIRECT = 'https://api.github.com/';


    /**
     * Normalises the configured base URL: trims stray whitespace, adds the
     * trailing slash, and refuses plain HTTP to a remote host (the GitHub
     * token travels in the Authorization header).
     *
     * @param mixed $configured Value of $gitHubApiUrl, or null when unset.
     *
     * @return string Base URL ending with "/".
     *
     * @throws RequestException When the URL is not HTTPS (plain HTTP is only allowed for loopback hosts).
     */
    public function resolve(mixed $configured): string
    {
        $url = trim((string) ($configured ?? ''));
        if ($url === '') {
            return self::DIRECT;
        }

        $url = rtrim($url, '/').'/';

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host   = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));

        if ($scheme === 'https' && $host !== '') {
            return $url;
        }

        $loopback = ($host === 'localhost' || $host === '::1' || str_starts_with($host, '127.'));
        if ($scheme === 'http' && $loopback === true) {
            return $url;
        }

        throw new RequestException(
            'GitHub API URL must use HTTPS (plain HTTP is only allowed for loopback hosts): '.$scheme.'://'.$host
        );

    }//end resolve()


}//end class
