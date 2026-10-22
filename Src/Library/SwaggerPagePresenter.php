<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Resolves what the public Swagger UI page (api/v1/swagger.php) shows to the
 * current visitor. The API reference is readable by anyone; "Try it out" is
 * only offered to an active dashboard session, or on localhost:8000 where
 * session_validator.php auto-authenticates API calls, because the protected
 * endpoints answer 401 without the PHPSESSID cookie. The browser sends that
 * cookie on its own to the same origin, so nothing has to be entered in the
 * Swagger UI Authorize dialog (browsers do not let scripts set the Cookie
 * header anyway). Pure logic, no HTTP, so it is unit-testable.
 */
class SwaggerPagePresenter
{

    /**
     * Idle seconds after which a dashboard session no longer counts as
     * active; the same rule index.php and session_validator.php apply.
     */
    public const SESSION_TIMEOUT = 1800;

    public const LOCAL_DEVELOPMENT_HOST = 'localhost:8000';

    public const DEFAULT_VERSION = '0.0.0-dev';

    private const LOGIN_LINK = '<a href="../../login.php">&larr; Log in</a>';

    private const DASHBOARD_LINK = '<a href="../../index.php">&larr; Dashboard</a>';

    private const NOTICE = '<div class="api-notice">
        You are browsing the public API reference. <a href="../../login.php">Log in</a> to the dashboard
        to enable <em>Try it out</em> &mdash; requests are sent with your dashboard session cookie, so no
        token needs to be entered here.
    </div>';

    /**
     * Whether the visitor may run requests from the Swagger UI.
     *
     * @var boolean
     */
    private bool $canTryItOut;

    /**
     * The logged-in user's name, empty when anonymous.
     *
     * @var string
     */
    private string $username;

    /**
     * The application version shown in the page title.
     *
     * @var string
     */
    private string $version;


    /**
     * Constructor.
     *
     * @param boolean $hasActiveSession   Whether the visitor has an active dashboard session.
     * @param boolean $isLocalDevelopment Whether the page is served on localhost:8000.
     * @param string  $username           The logged-in user's name, empty when anonymous.
     * @param string  $version            The application version shown in the page title.
     */
    public function __construct(bool $hasActiveSession, bool $isLocalDevelopment, string $username, string $version)
    {
        $this->canTryItOut = $isLocalDevelopment;
        if ($hasActiveSession === true) {
            $this->canTryItOut = true;
        }

        $this->username = $username;
        $this->version  = $version;

    }//end __construct()


    /**
     * Builds the presenter from the request, refreshing the session's last
     * activity while it is still active, as the dashboard pages do.
     *
     * @param array   $session     The PHP session data, updated in place.
     * @param string  $host        The request host (HTTP_HOST).
     * @param integer $now         The current Unix timestamp.
     * @param string  $versionFile Path to the version.txt file.
     *
     * @return SwaggerPagePresenter
     */
    public static function fromRequest(array &$session, string $host, int $now, string $versionFile): self
    {
        $hasActiveSession = self::hasActiveSession($session, $now);
        $username         = '';
        if ($hasActiveSession === true) {
            $session['last_activity'] = $now;
            $username                 = (string) ($session['username'] ?? '');
        }

        $version = self::readVersion($versionFile);

        return new self($hasActiveSession, ($host === self::LOCAL_DEVELOPMENT_HOST), $username, $version);

    }//end fromRequest()


    /**
     * Whether the session belongs to a logged-in user whose last activity is
     * within SESSION_TIMEOUT.
     *
     * @param array   $session The PHP session data.
     * @param integer $now     The current Unix timestamp.
     *
     * @return boolean
     */
    public static function hasActiveSession(array $session, int $now): bool
    {
        if (empty($session['user_id']) === true) {
            return false;
        }

        if (isset($session['last_activity']) === false) {
            return false;
        }

        return (($now - (int) $session['last_activity']) <= self::SESSION_TIMEOUT);

    }//end hasActiveSession()


    /**
     * Reads the application version from version.txt, falling back to
     * DEFAULT_VERSION when the file is missing or empty.
     *
     * @param string $versionFile Path to the version.txt file.
     *
     * @return string
     */
    public static function readVersion(string $versionFile): string
    {
        if (is_file($versionFile) === false) {
            return self::DEFAULT_VERSION;
        }

        $version = trim((string) file_get_contents($versionFile));
        if ($version === '') {
            return self::DEFAULT_VERSION;
        }

        return $version;

    }//end readVersion()


    /**
     * Whether the visitor may run requests from the Swagger UI.
     *
     * @return boolean
     */
    public function canTryItOut(): bool
    {
        return $this->canTryItOut;

    }//end canTryItOut()


    /**
     * The logged-in user's name, empty when anonymous.
     *
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;

    }//end getUsername()


    /**
     * The application version shown in the page title.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;

    }//end getVersion()


    /**
     * The HTML page title.
     *
     * @return string
     */
    public function getPageTitle(): string
    {
        return sprintf('Projects Monitor API v%s - Swagger UI', htmlspecialchars($this->version, ENT_QUOTES, 'UTF-8'));

    }//end getPageTitle()


    /**
     * The navigation link in the page header: back to the dashboard for a
     * visitor who can run requests, to the login page otherwise.
     *
     * @return string
     */
    public function getHeaderLink(): string
    {
        if ($this->canTryItOut === true) {
            return self::DASHBOARD_LINK;
        }

        return self::LOGIN_LINK;

    }//end getHeaderLink()


    /**
     * The session status shown in the page header.
     *
     * @return string
     */
    public function getSessionStatus(): string
    {
        if ($this->username !== '') {
            return sprintf('Signed in as <strong>%s</strong>', htmlspecialchars($this->username, ENT_QUOTES, 'UTF-8'));
        }

        if ($this->canTryItOut === true) {
            return 'Local development &mdash; Try it out enabled';
        }

        return 'Read-only';

    }//end getSessionStatus()


    /**
     * The notice telling an anonymous visitor how to enable "Try it out";
     * empty for a visitor who already can.
     *
     * @return string
     */
    public function getNotice(): string
    {
        if ($this->canTryItOut === true) {
            return '';
        }

        return self::NOTICE;

    }//end getNotice()


    /**
     * The JavaScript boolean literal telling Swagger UI whether to offer
     * "Try it out".
     *
     * @return string
     */
    public function getTryItOutFlag(): string
    {
        if ($this->canTryItOut === true) {
            return 'true';
        }

        return 'false';

    }//end getTryItOutFlag()


}//end class
