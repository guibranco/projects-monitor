<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\LogParser;

final class LogParserTest extends TestCase
{
    private LogParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LogParser();
    }

    public function testParseSingleLineError(): void
    {
        $log = '[17-May-2025 18:00:00 Europe/Dublin] PHP Fatal error:  Something bad happened in /var/www/html/file.php on line 42';

        $results = $this->parser->parse($log);

        $this->assertCount(1, $results);

        $entry = $results[0];
        $this->assertSame('17-May-2025 18:00:00 Europe/Dublin', $entry['date']);
        $this->assertStringContainsString('Something bad happened', $entry['multilineError']);
        $this->assertSame('/var/www/html/file.php', $entry['file']);
        $this->assertSame('42', $entry['line']);
        $this->assertArrayNotHasKey('stackTraceDetails', $entry);
    }

    public function testParseMultilineErrorWithJson(): void
    {
        $log = <<<LOG
[17-May-2025 18:36:50 Europe/Dublin] PHP Fatal error:  Uncaught GuiBranco\\ProjectsMonitor\\Library\\RequestException: Code: 400 - Error: {
  "message":"Bad request",
  "request_id":"7ba6f70dc77ee61b74d622325b7d7c54"
} in /home/zerocool/public_html/projects-monitor/Library/Postman.php:46
LOG;

        $results = $this->parser->parse($log);

        $this->assertCount(1, $results);

        $entry = $results[0];
        $this->assertSame('17-May-2025 18:36:50 Europe/Dublin', $entry['date']);
        $this->assertStringContainsString('Uncaught GuiBranco\\ProjectsMonitor\\Library\\RequestException', $entry['multilineError']);
        $this->assertStringContainsString('"message":"Bad request"', $entry['multilineError']);
        $this->assertSame('/home/zerocool/public_html/projects-monitor/Library/Postman.php', $entry['file']);
        $this->assertSame('46', $entry['line']);
        $this->assertArrayNotHasKey('stackTraceDetails', $entry);
    }

    public function testParseErrorWithStackTrace(): void
    {
        $log = <<<LOG
[17-May-2025 18:36:50 Europe/Dublin] PHP Fatal error:  Uncaught GuiBranco\\ProjectsMonitor\\Library\\RequestException: Code: 400 - Error: {
  "message":"Bad request",
  "request_id":"7ba6f70dc77ee61b74d622325b7d7c54"
} in /home/zerocool/public_html/projects-monitor/Library/Postman.php:46
Stack trace:
#0 /home/zerocool/public_html/projects-monitor/Library/Postman.php(81): GuiBranco\\ProjectsMonitor\\Library\\Postman->doRequest()
#1 /home/zerocool/public_html/projects-monitor/api/v1/postman.php(10): GuiBranco\\ProjectsMonitor\\Library\\Postman->getUsage()
#2 {main}
  thrown in /home/zerocool/public_html/projects-monitor/Library/Postman.php on line 46
LOG;

        $results = $this->parser->parse($log);

        $this->assertCount(1, $results);

        $entry = $results[0];
        $this->assertSame('17-May-2025 18:36:50 Europe/Dublin', $entry['date']);
        $this->assertStringContainsString('Uncaught GuiBranco\\ProjectsMonitor\\Library\\RequestException', $entry['multilineError']);
        $this->assertSame('/home/zerocool/public_html/projects-monitor/Library/Postman.php', $entry['file']);
        $this->assertSame('46', $entry['line']);

        $this->assertArrayHasKey('stackTraceDetails', $entry);
        $this->assertStringContainsString('#0 /home/zerocool/public_html/projects-monitor/Library/Postman.php(81):', $entry['stackTraceDetails']);
        $this->assertStringContainsString('#1 /home/zerocool/public_html/projects-monitor/api/v1/postman.php(10):', $entry['stackTraceDetails']);
    }

    public function testParseEmptyStringThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Log content cannot be empty.');

        $this->parser->parse('');
    }

    public function testParseNoMatchesReturnsEmptyArray(): void
    {
        $log = "This is a random string\nwith no valid log entries";

        $results = $this->parser->parse($log);

        $this->assertIsArray($results);
        $this->assertCount(0, $results);
    }

    public function testParseSingleLineErrorWithoutFile(): void
    {
        $log = '[17-May-2025 18:00:00 Europe/Dublin] PHP Fatal error: Something bad happened without file info';

        $results = $this->parser->parse($log);

        $this->assertCount(1, $results);

        $entry = $results[0];
        $this->assertSame('17-May-2025 18:00:00 Europe/Dublin', $entry['date']);
        $this->assertStringContainsString('Something bad happened without file info', $entry['multilineError']);
        $this->assertSame('NO FILE', $entry['file']);
        $this->assertSame('-', $entry['line']);
        $this->assertArrayNotHasKey('stackTraceDetails', $entry);
    }

    public function testParseMultilineErrorWithoutFile(): void
    {
        $log = <<<LOG
[17-May-2025 18:36:50 Europe/Dublin] PHP Fatal error: Uncaught Exception: Multi-line error message
with additional details
and more information
LOG;

        $results = $this->parser->parse($log);

        $this->assertCount(1, $results);

        $entry = $results[0];
        $this->assertSame('17-May-2025 18:36:50 Europe/Dublin', $entry['date']);
        $this->assertStringContainsString('Uncaught Exception: Multi-line error message', $entry['multilineError']);
        $this->assertStringContainsString('with additional details', $entry['multilineError']);
        $this->assertStringContainsString('and more information', $entry['multilineError']);
        $this->assertSame('NO FILE', $entry['file']);
        $this->assertSame('-', $entry['line']);
        $this->assertArrayNotHasKey('stackTraceDetails', $entry);
    }

    public function testParseMixedEntriesWithAndWithoutFile(): void
    {
        $log = <<<LOG
[17-May-2025 18:00:00 Europe/Dublin] PHP Fatal error: Error without file info
[17-May-2025 18:01:00 Europe/Dublin] PHP Fatal error: Error with file info in /var/www/html/file.php on line 42
}
