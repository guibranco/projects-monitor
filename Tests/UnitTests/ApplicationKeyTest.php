<?php

use GuiBranco\ProjectsMonitor\Library\ApplicationKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ApplicationKeyTest extends TestCase
{
    public static function acronymProvider(): array
    {
        return [
            "two words" => ["Projects Monitor", "pm"],
            "camelCase" => ["WebhooksHandler", "wh"],
            "uppercase run then word" => ["GStraccini-bot", "gsb"],
            "uppercase words and dash" => ["API BR - Vagas Aggregator", "abva"],
            "accents" => ["Integração Service", "is"],
            "underscores and dots" => ["my_app.worker", "maw"],
            "digits" => ["Service 2 Go", "s2g"],
            "single word" => ["Grimoire", "g"],
            "only symbols" => ["--- !!!", "app"],
            "empty" => ["", "app"],
        ];
    }

    #[DataProvider('acronymProvider')]
    public function testAcronym(string $name, string $expected): void
    {
        $this->assertSame($expected, ApplicationKey::acronym($name));
    }

    public function testBuildFollowsPattern(): void
    {
        $this->assertSame("pmk-pm-key", ApplicationKey::build("Projects Monitor"));
        $this->assertSame("pmk-pm-key", ApplicationKey::build("Projects Monitor", 1));
        $this->assertSame("pmk-pm2-key", ApplicationKey::build("Projects Monitor", 2));
        $this->assertSame("pmk-pm12-key", ApplicationKey::build("Projects Monitor", 12));
    }

    public function testMatchesName(): void
    {
        $this->assertTrue(ApplicationKey::matchesName("pmk-pm-key", "Projects Monitor"));
        $this->assertTrue(ApplicationKey::matchesName("pmk-pm3-key", "Projects Monitor"));
        $this->assertFalse(ApplicationKey::matchesName("pmk-pm1-key", "Projects Monitor"));
        $this->assertFalse(ApplicationKey::matchesName("pmk-ls-key", "Projects Monitor"));
        $this->assertFalse(ApplicationKey::matchesName("test_application", "Test Application"));
    }

    public function testGenerateTokenIs32HexCharactersAndRandom(): void
    {
        $first = ApplicationKey::generateToken();
        $second = ApplicationKey::generateToken();

        $this->assertMatchesRegularExpression("/^[0-9a-f]{32}$/", $first);
        $this->assertNotSame($first, $second);
    }

    public function testMaskShowsOnlyTheLastFourCharacters(): void
    {
        $this->assertSame("••••••••cdef", ApplicationKey::mask("0123456789abcdef"));
        $this->assertSame("••••", ApplicationKey::mask("abc"));
    }
}
