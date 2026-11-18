<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\GitHubApiUrl;
use GuiBranco\ProjectsMonitor\Library\RequestException;

/** Tests the base URL GitHub API calls use: the github-api-proxy when configured, GitHub otherwise. */
class GitHubApiUrlTest extends TestCase
{
    public static function unsetProvider(): array
    {
        return [
            "null" => [null],
            "empty" => [""],
            "whitespace" => ["  \n"],
        ];
    }

    /** Without a configured URL, calls go straight to api.github.com. */
    #[DataProvider("unsetProvider")]
    public function testFallsBackToGitHubWhenUnset(mixed $configured): void
    {
        $this->assertSame("https://api.github.com/", GitHubApiUrl::resolve($configured));
    }

    public static function validProvider(): array
    {
        return [
            "proxy without slash" => ["https://github-proxy.gstraccini.dev", "https://github-proxy.gstraccini.dev/"],
            "proxy with slash" => ["https://github-proxy.gstraccini.dev/", "https://github-proxy.gstraccini.dev/"],
            "stray whitespace" => [" https://github-proxy.gstraccini.dev/ \n", "https://github-proxy.gstraccini.dev/"],
            "github directly" => ["https://api.github.com", "https://api.github.com/"],
            "loopback http" => ["http://127.0.0.1:8080", "http://127.0.0.1:8080/"],
            "localhost http" => ["http://localhost:8080/", "http://localhost:8080/"],
        ];
    }

    /** A configured URL is normalised to end with exactly one slash. */
    #[DataProvider("validProvider")]
    public function testNormalisesConfiguredUrl(string $configured, string $expected): void
    {
        $this->assertSame($expected, GitHubApiUrl::resolve($configured));
    }

    public static function insecureProvider(): array
    {
        return [
            "http remote" => ["http://github-proxy.gstraccini.dev/"],
            "other scheme" => ["ftp://github-proxy.gstraccini.dev/"],
            "no scheme" => ["github-proxy.gstraccini.dev"],
        ];
    }

    /** The GitHub token travels in the Authorization header, so a remote URL must be HTTPS. */
    #[DataProvider("insecureProvider")]
    public function testRejectsInsecureUrl(string $configured): void
    {
        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("GitHub API URL must use HTTPS");

        GitHubApiUrl::resolve($configured);
    }
}
