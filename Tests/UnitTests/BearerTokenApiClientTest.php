<?php

use GuiBranco\ProjectsMonitor\Library\RequestException;
use GuiBranco\ProjectsMonitor\Library\GitHubApiProxy;
use GuiBranco\ProjectsMonitor\Library\SecretsFileNotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * Tests the secrets loading shared by the bearer-token API clients
 * (BearerTokenApiClient, through GitHubApiProxy): values are trimmed, the URL
 * gets a trailing slash, several instances in one request all read the file,
 * and a missing file is reported.
 */
class BearerTokenApiClientTest extends TestCase
{

    private const SECRETS_FILE = __DIR__.'/../../Src/secrets/githubApiProxy.secrets.php';

    /**
     * Whether this test created the secrets file (and so must delete it).
     *
     * @var boolean
     */
    private $createdSecrets = false;


    /**
     * Writes a throw-away secrets file unless a real one is present.
     *
     * @return void
     */
    protected function setUp(): void
    {
        if (file_exists(self::SECRETS_FILE) === true) {
            $this->markTestSkipped('A real githubApiProxy.secrets.php is present; not overwriting it.');
        }

        if (is_dir(dirname(self::SECRETS_FILE)) === false) {
            mkdir(dirname(self::SECRETS_FILE), 0700, true);
        }

        $content  = "<?php\n";
        $content .= "\$githubApiProxyUrl   = ' https://api.github-proxy.example.test \n';\n";
        $content .= "\$githubApiProxyToken = \"secret-token\\n\";\n";
        file_put_contents(self::SECRETS_FILE, $content);
        $this->createdSecrets = true;

    }//end setUp()


    /**
     * Removes the throw-away secrets file.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->createdSecrets === true) {
            unlink(self::SECRETS_FILE);
        }

    }//end tearDown()


    /**
     * Reads a protected property of the base class.
     *
     * @param object $client   Client instance.
     * @param string $property Property name.
     *
     * @return mixed
     */
    private function property(object $client, string $property): mixed
    {
        $reflection = new ReflectionProperty(GitHubApiProxy::class, $property);

        return $reflection->getValue($client);

    }//end property()


    /**
     * Every instance reads the secrets, trimmed, with a trailing slash on the URL.
     *
     * @return void
     */
    public function testEveryInstanceLoadsTrimmedSecrets(): void
    {
        $first  = new GitHubApiProxy();
        $second = new GitHubApiProxy();

        foreach ([$first, $second] as $client) {
            $this->assertSame('https://api.github-proxy.example.test/', $this->property($client, 'apiUrl'));
            $this->assertSame('Authorization: Bearer secret-token', $this->property($client, 'headers')[0]);
        }

    }//end testEveryInstanceLoadsTrimmedSecrets()


    /**
     * The proxy client gets a finite transfer timeout, so a stalled call can
     * not hold the dashboard request open.
     *
     * @return void
     */
    public function testProxyClientHasAFiniteTimeout(): void
    {
        $request = $this->property(new GitHubApiProxy(), 'request');

        $this->assertSame(10, $request->getTimeout());

    }//end testProxyClientHasAFiniteTimeout()


    /**
     * URLs that may carry the bearer token: HTTPS, or HTTP on loopback.
     *
     * @return array<string, array{string}>
     */
    public static function secureUrls(): array
    {
        $urls = [
                 'https'          => ['https://api.github-proxy.example.test/'],
                 'http localhost' => ['http://localhost:8081/'],
                 'http 127.x'     => ['http://127.0.0.1:8081/'],
                 'http ::1'       => ['http://[::1]:8081/'],
                ];

        return $urls;

    }//end secureUrls()


    /**
     * URLs that must be refused before the token is sent.
     *
     * @return array<string, array{string}>
     */
    public static function insecureUrls(): array
    {
        $urls = [
                 'http remote'    => ['http://api.github-proxy.example.test/'],
                 'http lookalike' => ['http://localhost.example.test/'],
                 'empty'          => ['/'],
                 'other scheme'   => ['ftp://api.github-proxy.example.test/'],
                ];

        return $urls;

    }//end insecureUrls()


    /**
     * HTTPS and loopback HTTP URLs are accepted.
     *
     * @param string $url Base URL.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('secureUrls')]
    public function testSecureUrlsAreAccepted(string $url): void
    {
        (new GitHubApiProxy())->requireSecureUrl($url);

        $this->addToAssertionCount(1);

    }//end testSecureUrlsAreAccepted()


    /**
     * Plain HTTP to a remote host (or no URL at all) is refused.
     *
     * @param string $url Base URL.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('insecureUrls')]
    public function testInsecureUrlsAreRefused(string $url): void
    {
        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('GitHub API proxy URL must use HTTPS');

        (new GitHubApiProxy())->requireSecureUrl($url);

    }//end testInsecureUrlsAreRefused()


    /**
     * A missing secrets file is reported by name.
     *
     * @return void
     */
    public function testMissingSecretsFileIsReported(): void
    {
        unlink(self::SECRETS_FILE);
        $this->createdSecrets = false;

        $this->expectException(SecretsFileNotFoundException::class);
        $this->expectExceptionMessage('githubApiProxy.secrets.php');
        new GitHubApiProxy();

    }//end testMissingSecretsFileIsReported()


}//end class
