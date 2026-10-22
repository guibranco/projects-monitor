<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\SwaggerPagePresenter;

/**
 * Tests what the public Swagger UI page shows to anonymous, logged-in,
 * expired and localhost visitors, and the version.txt fallback.
 */
class SwaggerPagePresenterTest extends TestCase
{

    private const NOW = 1_800_000_000;

    private const HOST = 'guilherme.straccini.com';

    private const NO_VERSION_FILE = '/nonexistent/version.txt';

    /**
     * The version.txt stand-ins created by a test, removed on tearDown.
     *
     * @var array
     */
    private array $versionFiles = [];


    /**
     * Removes the version.txt stand-ins created by the test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->versionFiles as $versionFile) {
            if (is_file($versionFile) === true) {
                unlink($versionFile);
            }
        }

        $this->versionFiles = [];

    }//end tearDown()


    /**
     * Writes a version.txt stand-in and returns its path.
     *
     * @param string $content The file content.
     *
     * @return string
     */
    protected function createVersionFile(string $content): string
    {
        $versionFile = tempnam(sys_get_temp_dir(), 'pm-version-');

        file_put_contents($versionFile, $content);
        $this->versionFiles[] = $versionFile;

        return $versionFile;

    }//end createVersionFile()


    /**
     * A session as login.php leaves it, last active $idleSeconds ago.
     *
     * @param integer $idleSeconds Seconds since the last activity.
     *
     * @return array
     */
    protected function getLoggedInSession(int $idleSeconds): array
    {
        $session = [];

        $session['user_id']       = 7;
        $session['username']      = 'guibranco';
        $session['email']         = 'guibranco@example.com';
        $session['last_activity'] = (self::NOW - $idleSeconds);

        return $session;

    }//end getLoggedInSession()


    /**
     * Builds the presenter for a request on the production host, without a
     * version.txt.
     *
     * @param array $session The PHP session data, updated in place.
     *
     * @return SwaggerPagePresenter
     */
    protected function present(array &$session): SwaggerPagePresenter
    {
        return SwaggerPagePresenter::fromRequest($session, self::HOST, self::NOW, self::NO_VERSION_FILE);

    }//end present()


    /**
     * An anonymous visitor gets the read-only reference: login link, no
     * username, the notice and "Try it out" switched off.
     *
     * @return void
     */
    public function testAnonymousVisitorIsReadOnly(): void
    {
        $session   = [];
        $presenter = $this->present($session);

        $this->assertFalse($presenter->canTryItOut());
        $this->assertSame('', $presenter->getUsername());
        $this->assertSame('Read-only', $presenter->getSessionStatus());
        $this->assertStringContainsString('href="../../login.php"', $presenter->getHeaderLink());
        $this->assertStringContainsString('Log in', $presenter->getHeaderLink());
        $this->assertStringContainsString('class="api-notice"', $presenter->getNotice());
        $this->assertStringContainsString('href="../../login.php"', $presenter->getNotice());
        $this->assertSame('false', $presenter->getTryItOutFlag());
        $this->assertSame([], $session);

    }//end testAnonymousVisitorIsReadOnly()


    /**
     * A logged-in visitor keeps "Try it out", sees their name and the
     * dashboard link, gets no notice, and the session's last activity is
     * refreshed as the dashboard pages do.
     *
     * @return void
     */
    public function testLoggedInVisitorCanTryItOut(): void
    {
        $session   = $this->getLoggedInSession(600);
        $presenter = $this->present($session);

        $this->assertTrue($presenter->canTryItOut());
        $this->assertSame('guibranco', $presenter->getUsername());
        $this->assertSame('Signed in as <strong>guibranco</strong>', $presenter->getSessionStatus());
        $this->assertStringContainsString('href="../../index.php"', $presenter->getHeaderLink());
        $this->assertStringContainsString('Dashboard', $presenter->getHeaderLink());
        $this->assertSame('', $presenter->getNotice());
        $this->assertSame('true', $presenter->getTryItOutFlag());
        $this->assertSame(self::NOW, $session['last_activity']);

    }//end testLoggedInVisitorCanTryItOut()


    /**
     * The username is HTML-escaped in the status.
     *
     * @return void
     */
    public function testUsernameIsEscaped(): void
    {
        $session = $this->getLoggedInSession(0);

        $session['username'] = '<script>alert(1)</script>';

        $presenter = $this->present($session);

        $this->assertStringNotContainsString('<script>', $presenter->getSessionStatus());
        $this->assertStringContainsString('&lt;script&gt;', $presenter->getSessionStatus());

    }//end testUsernameIsEscaped()


    /**
     * A session idle for longer than the timeout is treated as anonymous and
     * is left untouched (session_validator.php expires it on the next API
     * call).
     *
     * @return void
     */
    public function testExpiredSessionIsReadOnly(): void
    {
        $session   = $this->getLoggedInSession((SwaggerPagePresenter::SESSION_TIMEOUT + 1));
        $expected  = $session;
        $presenter = $this->present($session);

        $this->assertFalse($presenter->canTryItOut());
        $this->assertSame('', $presenter->getUsername());
        $this->assertSame('Read-only', $presenter->getSessionStatus());
        $this->assertSame('false', $presenter->getTryItOutFlag());
        $this->assertSame($expected, $session);

    }//end testExpiredSessionIsReadOnly()


    /**
     * The activity rule is inclusive: exactly SESSION_TIMEOUT idle seconds
     * is still active, one more is not, and a session without a user or
     * without a last activity never is.
     *
     * @return void
     */
    public function testHasActiveSessionRule(): void
    {
        $timeout = SwaggerPagePresenter::SESSION_TIMEOUT;
        $noUser  = $this->getLoggedInSession(0);
        $noTime  = $this->getLoggedInSession(0);

        $noUser['user_id'] = 0;
        unset($noTime['last_activity']);

        $this->assertTrue(SwaggerPagePresenter::hasActiveSession($this->getLoggedInSession(0), self::NOW));
        $this->assertTrue(SwaggerPagePresenter::hasActiveSession($this->getLoggedInSession($timeout), self::NOW));
        $expired = $this->getLoggedInSession(($timeout + 1));

        $this->assertFalse(SwaggerPagePresenter::hasActiveSession($expired, self::NOW));
        $this->assertFalse(SwaggerPagePresenter::hasActiveSession([], self::NOW));
        $this->assertFalse(SwaggerPagePresenter::hasActiveSession($noUser, self::NOW));
        $this->assertFalse(SwaggerPagePresenter::hasActiveSession($noTime, self::NOW));

    }//end testHasActiveSessionRule()


    /**
     * On localhost:8000 "Try it out" is offered without a session, because
     * session_validator.php auto-authenticates API calls there.
     *
     * @return void
     */
    public function testLocalDevelopmentCanTryItOut(): void
    {
        $session   = [];
        $localhost = SwaggerPagePresenter::LOCAL_DEVELOPMENT_HOST;
        $presenter = SwaggerPagePresenter::fromRequest($session, $localhost, self::NOW, self::NO_VERSION_FILE);

        $this->assertTrue($presenter->canTryItOut());
        $this->assertSame('', $presenter->getUsername());
        $this->assertSame('Local development &mdash; Try it out enabled', $presenter->getSessionStatus());
        $this->assertStringContainsString('href="../../index.php"', $presenter->getHeaderLink());
        $this->assertSame('', $presenter->getNotice());
        $this->assertSame('true', $presenter->getTryItOutFlag());

    }//end testLocalDevelopmentCanTryItOut()


    /**
     * A logged-in visitor on localhost still sees their own name.
     *
     * @return void
     */
    public function testLoggedInOnLocalhostShowsUsername(): void
    {
        $session   = $this->getLoggedInSession(0);
        $localhost = SwaggerPagePresenter::LOCAL_DEVELOPMENT_HOST;
        $presenter = SwaggerPagePresenter::fromRequest($session, $localhost, self::NOW, self::NO_VERSION_FILE);

        $this->assertTrue($presenter->canTryItOut());
        $this->assertSame('Signed in as <strong>guibranco</strong>', $presenter->getSessionStatus());

    }//end testLoggedInOnLocalhostShowsUsername()


    /**
     * The version comes from version.txt (trimmed), with the development
     * fallback when the file is missing or empty, and ends up in the title.
     *
     * @return void
     */
    public function testVersionAndPageTitle(): void
    {
        $fallback = SwaggerPagePresenter::DEFAULT_VERSION;

        $this->assertSame('2.3.4', SwaggerPagePresenter::readVersion($this->createVersionFile("2.3.4\n")));
        $this->assertSame($fallback, SwaggerPagePresenter::readVersion($this->createVersionFile("  \n")));
        $this->assertSame($fallback, SwaggerPagePresenter::readVersion(self::NO_VERSION_FILE));

        $session     = [];
        $versionFile = $this->createVersionFile('2.3.4');
        $presenter   = SwaggerPagePresenter::fromRequest($session, self::HOST, self::NOW, $versionFile);

        $this->assertSame('2.3.4', $presenter->getVersion());
        $this->assertSame('Projects Monitor API v2.3.4 - Swagger UI', $presenter->getPageTitle());

        $presenter = new SwaggerPagePresenter(false, false, '', '1.0.0 <beta>');

        $this->assertSame('Projects Monitor API v1.0.0 &lt;beta&gt; - Swagger UI', $presenter->getPageTitle());

    }//end testVersionAndPageTitle()


}//end class
