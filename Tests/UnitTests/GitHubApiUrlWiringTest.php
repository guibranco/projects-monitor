<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\GitHub;
use GuiBranco\ProjectsMonitor\Library\GitHubActionsUsage;

/**
 * Tests that the GitHub API clients take their base URL from $gitHubApiUrl
 * in gitHub.secrets.php, so their calls go through github-api-proxy.
 */
class GitHubApiUrlWiringTest extends TestCase
{

    /**
     * Secrets file the clients read.
     */
    private const SECRETS_FILE = __DIR__.'/../../Src/secrets/gitHub.secrets.php';

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
            $this->markTestSkipped('A real gitHub.secrets.php is present; not overwriting it.');
        }

        if (is_dir(dirname(self::SECRETS_FILE)) === false) {
            mkdir(dirname(self::SECRETS_FILE), 0700, true);
        }

        $content  = "<?php\n";
        $content .= "\$gitHubToken  = 'test-token';\n";
        $content .= "\$gitHubApiUrl = ' https://github-proxy.example.test ';\n";
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
     * Reads a private property.
     *
     * @param object $object   Instance.
     * @param string $property Property name.
     *
     * @return mixed
     */
    private function property(object $object, string $property): mixed
    {
        $reflection = new ReflectionProperty($object, $property);

        return $reflection->getValue($object);

    }//end property()


    /**
     * Both clients send their calls to the configured proxy URL. GitHub is
     * built first because it loads the secrets file with require_once.
     *
     * @return void
     */
    public function testClientsUseConfiguredApiUrl(): void
    {
        $gitHub = new GitHub();
        $usage  = new GitHubActionsUsage();

        $this->assertSame('https://github-proxy.example.test/', $this->property($gitHub, 'apiUrl'));
        $this->assertSame('https://github-proxy.example.test/', $this->property($usage, 'apiUrl'));

    }//end testClientsUseConfiguredApiUrl()


}//end class
