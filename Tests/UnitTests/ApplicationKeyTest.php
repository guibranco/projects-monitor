<?php

use GuiBranco\ProjectsMonitor\Library\ApplicationKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the keys (pmk-{acronym}-key) and tokens of applications.
 */
class ApplicationKeyTest extends TestCase
{

    /**
     * Builds the keys and tokens.
     *
     * @var ApplicationKey
     */
    private ApplicationKey $applicationKey;


    /**
     * Creates the ApplicationKey under test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->applicationKey = new ApplicationKey();

    }//end setUp()


    /**
     * Names and their acronyms.
     *
     * @return array<string, array<int, string>>
     */
    public static function acronymProvider(): array
    {
        return [
                "two words"                => [
                                               "Projects Monitor",
                                               "pm",
                                              ],
                "camelCase"                => [
                                               "WebhooksHandler",
                                               "wh",
                                              ],
                "uppercase run then word"  => [
                                               "GStraccini-bot",
                                               "gsb",
                                              ],
                "uppercase words and dash" => [
                                               "API BR - Vagas Aggregator",
                                               "abva",
                                              ],
                "accents"                  => [
                                               "Integração Service",
                                               "is",
                                              ],
                "underscores and dots"     => [
                                               "my_app.worker",
                                               "maw",
                                              ],
                "digits"                   => [
                                               "Service 2 Go",
                                               "s2g",
                                              ],
                "single word"              => [
                                               "Grimoire",
                                               "g",
                                              ],
                "only symbols"             => [
                                               "--- !!!",
                                               "app",
                                              ],
                "empty"                    => [
                                               "",
                                               "app",
                                              ],
               ];

    }//end acronymProvider()


    /**
     * The acronym is the first letter of each word, lowercase.
     *
     * @param string $name     The application's name.
     * @param string $expected The acronym.
     *
     * @return void
     */
    #[DataProvider('acronymProvider')]
    public function testAcronym(string $name, string $expected): void
    {
        $this->assertSame($expected, $this->applicationKey->acronym($name));

    }//end testAcronym()


    /**
     * The key follows pmk-{acronym}-key, with the sequence after the acronym from 2 on.
     *
     * @return void
     */
    public function testBuildFollowsPattern(): void
    {
        $this->assertSame("pmk-pm-key", $this->applicationKey->build("Projects Monitor", 1));
        $this->assertSame("pmk-pm2-key", $this->applicationKey->build("Projects Monitor", 2));
        $this->assertSame("pmk-pm12-key", $this->applicationKey->build("Projects Monitor", 12));

    }//end testBuildFollowsPattern()


    /**
     * A key matches a name when it is one of the name's keys.
     *
     * @return void
     */
    public function testMatchesName(): void
    {
        $this->assertTrue($this->applicationKey->matchesName("pmk-pm-key", "Projects Monitor"));
        $this->assertTrue($this->applicationKey->matchesName("pmk-pm3-key", "Projects Monitor"));
        $this->assertFalse($this->applicationKey->matchesName("pmk-pm1-key", "Projects Monitor"));
        $this->assertFalse($this->applicationKey->matchesName("pmk-ls-key", "Projects Monitor"));
        $this->assertFalse($this->applicationKey->matchesName("test_application", "Test Application"));

    }//end testMatchesName()


    /**
     * A token is 32 random hexadecimal characters.
     *
     * @return void
     */
    public function testGenerateTokenIsHexAndRandom(): void
    {
        $first  = $this->applicationKey->generateToken();
        $second = $this->applicationKey->generateToken();

        $this->assertMatchesRegularExpression("/^[0-9a-f]{32}$/", $first);
        $this->assertNotSame($first, $second);

    }//end testGenerateTokenIsHexAndRandom()


    /**
     * The mask shows only the last 4 characters.
     *
     * @return void
     */
    public function testMaskShowsOnlyTheLastFourCharacters(): void
    {
        $this->assertSame("••••••••cdef", $this->applicationKey->mask("0123456789abcdef"));
        $this->assertSame("••••", $this->applicationKey->mask("abc"));

    }//end testMaskShowsOnlyTheLastFourCharacters()


    /**
     * A long name's acronym is bounded so that its keys fit VARCHAR(100).
     *
     * @return void
     */
    public function testAcronymIsBoundedSoTheKeyFitsTheColumn(): void
    {
        $name = str_repeat("a ", 100);

        $this->assertSame(ApplicationKey::MAX_ACRONYM_LENGTH, strlen($this->applicationKey->acronym($name)));
        $this->assertLessThanOrEqual(100, strlen($this->applicationKey->build($name, 1000)));
        $this->assertTrue($this->applicationKey->matchesName($this->applicationKey->build($name, 1), $name));

    }//end testAcronymIsBoundedSoTheKeyFitsTheColumn()


}//end class
