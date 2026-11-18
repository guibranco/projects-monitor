<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\GitHubApiUrl;
use GuiBranco\ProjectsMonitor\Library\RequestException;

/**
 * Tests the base URL GitHub API calls use: the github-api-proxy when
 * configured, GitHub otherwise.
 */
class GitHubApiUrlTest extends TestCase
{


    /**
     * Values meaning "no URL configured".
     *
     * @return array
     */
    public static function unsetProvider(): array
    {
        $values = [
                   'null'       => [null],
                   'empty'      => [''],
                   'whitespace' => ["  \n"],
                  ];

        return $values;

    }//end unsetProvider()


    /**
     * Without a configured URL, calls go straight to api.github.com.
     *
     * @param mixed $configured Configured value.
     *
     * @return void
     */
    #[DataProvider('unsetProvider')]
    public function testFallsBackToGitHubWhenUnset(mixed $configured): void
    {
        $resolver = new GitHubApiUrl();

        $this->assertSame('https://api.github.com/', $resolver->resolve($configured));

    }//end testFallsBackToGitHubWhenUnset()


    /**
     * Configured URLs and their normalised form.
     *
     * @return array
     */
    public static function validProvider(): array
    {
        $urls = [
                 'proxy without slash' => [
                                           'https://github-proxy.gstraccini.dev',
                                           'https://github-proxy.gstraccini.dev/',
                                          ],
                 'proxy with slash'    => [
                                           'https://github-proxy.gstraccini.dev/',
                                           'https://github-proxy.gstraccini.dev/',
                                          ],
                 'stray whitespace'    => [
                                           " https://github-proxy.gstraccini.dev/ \n",
                                           'https://github-proxy.gstraccini.dev/',
                                          ],
                 'github directly'     => [
                                           'https://api.github.com',
                                           'https://api.github.com/',
                                          ],
                 'loopback http'       => [
                                           'http://127.0.0.1:8080',
                                           'http://127.0.0.1:8080/',
                                          ],
                 'localhost http'      => [
                                           'http://localhost:8080/',
                                           'http://localhost:8080/',
                                          ],
                ];

        return $urls;

    }//end validProvider()


    /**
     * A configured URL is normalised to end with exactly one slash.
     *
     * @param string $configured Configured value.
     * @param string $expected   Expected base URL.
     *
     * @return void
     */
    #[DataProvider('validProvider')]
    public function testNormalisesConfiguredUrl(string $configured, string $expected): void
    {
        $resolver = new GitHubApiUrl();

        $this->assertSame($expected, $resolver->resolve($configured));

    }//end testNormalisesConfiguredUrl()


    /**
     * URLs that would send the token unencrypted.
     *
     * @return array
     */
    public static function insecureProvider(): array
    {
        $urls = [
                 'http remote'  => ['http://github-proxy.gstraccini.dev/'],
                 'other scheme' => ['ftp://github-proxy.gstraccini.dev/'],
                 'no scheme'    => ['github-proxy.gstraccini.dev'],
                 '127 dns name' => ['http://127.github-proxy.example.test/'],
                ];

        return $urls;

    }//end insecureProvider()


    /**
     * The GitHub token travels in the Authorization header, so a remote URL
     * must be HTTPS.
     *
     * @param string $configured Configured value.
     *
     * @return void
     */
    #[DataProvider('insecureProvider')]
    public function testRejectsInsecureUrl(string $configured): void
    {
        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('GitHub API URL must use HTTPS');

        $resolver = new GitHubApiUrl();
        $resolver->resolve($configured);

    }//end testRejectsInsecureUrl()


}//end class
