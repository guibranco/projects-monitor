<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Logger;

class LoggerTest extends TestCase
{
    private Logger $logger;
    private mysqli $connMock;

    protected function setUp(): void
    {
        $this->connMock = $this->createMock(mysqli::class);

        $this->logger = (new ReflectionClass(Logger::class))->newInstanceWithoutConstructor();

        $prop = (new ReflectionClass(Logger::class))->getProperty('connection');
        $prop->setAccessible(true);
        $prop->setValue($this->logger, $this->connMock);
    }

    // -------------------------------------------------------------------------
    // convertUrlsToLinks
    // -------------------------------------------------------------------------

    public function testConvertUrlsToLinksWrapsHttpUrl(): void
    {
        $result = $this->logger->convertUrlsToLinks('Visit https://example.com for info');

        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('target="_blank"', $result);
        $this->assertStringContainsString('rel="noopener noreferrer"', $result);
    }

    public function testConvertUrlsToLinksWrapsMultipleUrls(): void
    {
        $result = $this->logger->convertUrlsToLinks('https://a.com and https://b.com');

        $this->assertStringContainsString('href="https://a.com"', $result);
        $this->assertStringContainsString('href="https://b.com"', $result);
    }

    public function testConvertUrlsToLinksLeavesPlainTextUnchanged(): void
    {
        $result = $this->logger->convertUrlsToLinks('No URLs here');

        $this->assertSame('No URLs here', $result);
    }

    // -------------------------------------------------------------------------
    // convertUserAgentToLink
    // -------------------------------------------------------------------------

    public function testConvertUserAgentToLinkWithUrlPattern(): void
    {
        $result = $this->logger->convertUserAgentToLink('MyBot (+https://example.com)');

        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('MyBot', $result);
        $this->assertStringContainsString('target="_blank"', $result);
    }

    public function testConvertUserAgentToLinkWithoutUrlReturnsEscapedText(): void
    {
        $result = $this->logger->convertUserAgentToLink('Plain User Agent');

        $this->assertSame('Plain User Agent', $result);
    }

    public function testConvertUserAgentToLinkEscapesSpecialChars(): void
    {
        $result = $this->logger->convertUserAgentToLink('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    // -------------------------------------------------------------------------
    // getMessagesByGroupSampleId
    // -------------------------------------------------------------------------

    public function testGetMessagesByGroupSampleIdReturnsMappedRows(): void
    {
        $row = [
            'id'             => 42,
            'name'           => 'TestApp',
            'class'          => 'TestClass',
            'function'       => 'testMethod',
            'file'           => '/app/src/Foo.php',
            'line'           => 99,
            'object'         => 'none',
            'type'           => '->',
            'args'           => '[]',
            'message'        => 'Something went wrong',
            'details'        => 'none',
            'correlation_id' => 'abc-123',
            'user_agent'     => 'TestAgent/1.0',
            'created_at'     => '2024-01-15 10:00:00',
        ];

        $resultMock = $this->createMock(mysqli_result::class);
        $resultMock->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls($row, null);

        $stmtMock = $this->createMock(mysqli_stmt::class);
        $stmtMock->method('get_result')->willReturn($resultMock);

        $this->connMock->method('prepare')->willReturn($stmtMock);

        $result = $this->logger->getMessagesByGroupSampleId(42);

        $this->assertCount(1, $result);
        $this->assertSame(42, $result[0]['id']);
        $this->assertSame('TestApp', $result[0]['name']);
        $this->assertSame('TestClass', $result[0]['class']);
        $this->assertSame('Something went wrong', $result[0]['message']);
        $this->assertSame('abc-123', $result[0]['correlation_id']);
    }

    public function testGetMessagesByGroupSampleIdReturnsEmptyArrayWhenNoRows(): void
    {
        $resultMock = $this->createMock(mysqli_result::class);
        $resultMock->method('fetch_assoc')->willReturn(null);

        $stmtMock = $this->createMock(mysqli_stmt::class);
        $stmtMock->method('get_result')->willReturn($resultMock);

        $this->connMock->method('prepare')->willReturn($stmtMock);

        $result = $this->logger->getMessagesByGroupSampleId(1);

        $this->assertSame([], $result);
    }

    public function testGetMessagesByGroupSampleIdReturnsAllRowsFromResult(): void
    {
        $makeRow = static fn(int $id) => [
            'id'             => $id,
            'name'           => 'App',
            'class'          => 'Cls',
            'function'       => 'fn',
            'file'           => 'f.php',
            'line'           => 1,
            'object'         => '',
            'type'           => '',
            'args'           => '',
            'message'        => 'err',
            'details'        => '',
            'correlation_id' => '',
            'user_agent'     => 'UA',
            'created_at'     => '2024-01-01 00:00:00',
        ];

        $resultMock = $this->createMock(mysqli_result::class);
        $resultMock->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls($makeRow(1), $makeRow(2), $makeRow(3), null);

        $stmtMock = $this->createMock(mysqli_stmt::class);
        $stmtMock->method('get_result')->willReturn($resultMock);

        $this->connMock->method('prepare')->willReturn($stmtMock);

        $result = $this->logger->getMessagesByGroupSampleId(1);

        $this->assertCount(3, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame(2, $result[1]['id']);
        $this->assertSame(3, $result[2]['id']);
    }

    // -------------------------------------------------------------------------
    // deleteMessageById
    // -------------------------------------------------------------------------

    public function testDeleteMessageByIdReturnsTrueOnSuccess(): void
    {
        $stmtMock = $this->createMock(mysqli_stmt::class);
        $stmtMock->method('execute')->willReturn(true);

        $this->connMock->method('begin_transaction')->willReturn(true);
        $this->connMock->method('prepare')->willReturn($stmtMock);
        $this->connMock->method('commit')->willReturn(true);

        $result = $this->logger->deleteMessageById(1);

        $this->assertTrue($result);
    }

    public function testDeleteMessageByIdReturnsFalseAndRollsBackWhenExecuteFails(): void
    {
        $stmtMock = $this->createMock(mysqli_stmt::class);
        $stmtMock->method('execute')->willReturn(false);

        $this->connMock->method('begin_transaction')->willReturn(true);
        $this->connMock->method('prepare')->willReturn($stmtMock);
        $this->connMock->expects($this->once())->method('rollback')->willReturn(true);
        $this->connMock->expects($this->never())->method('commit');

        $result = $this->logger->deleteMessageById(99);

        $this->assertFalse($result);
    }

    public function testDeleteMessageByIdRethrowsExceptionAfterRollback(): void
    {
        $this->connMock->method('begin_transaction')->willReturn(true);
        $this->connMock->method('prepare')
            ->willThrowException(new \Exception('DB connection lost'));
        $this->connMock->expects($this->once())->method('rollback')->willReturn(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('DB connection lost');

        $this->logger->deleteMessageById(1);
    }

    // -------------------------------------------------------------------------
    // deleteMessagesByGroupSampleId
    // -------------------------------------------------------------------------

    public function testDeleteMessagesByGroupSampleIdReturnsTrueOnSuccess(): void
    {
        $stmtMock = $this->createMock(mysqli_stmt::class);
        $stmtMock->method('execute')->willReturn(true);

        $this->connMock->method('begin_transaction')->willReturn(true);
        $this->connMock->method('prepare')->willReturn($stmtMock);
        $this->connMock->method('commit')->willReturn(true);

        $result = $this->logger->deleteMessagesByGroupSampleId(42);

        $this->assertTrue($result);
    }

    public function testDeleteMessagesByGroupSampleIdReturnsFalseAndRollsBackWhenExecuteFails(): void
    {
        $stmtMock = $this->createMock(mysqli_stmt::class);
        $stmtMock->method('execute')->willReturn(false);

        $this->connMock->method('begin_transaction')->willReturn(true);
        $this->connMock->method('prepare')->willReturn($stmtMock);
        $this->connMock->expects($this->once())->method('rollback')->willReturn(true);
        $this->connMock->expects($this->never())->method('commit');

        $result = $this->logger->deleteMessagesByGroupSampleId(99);

        $this->assertFalse($result);
    }

    public function testDeleteMessagesByGroupSampleIdRethrowsExceptionAfterRollback(): void
    {
        $this->connMock->method('begin_transaction')->willReturn(true);
        $this->connMock->method('prepare')
            ->willThrowException(new \Exception('Deadlock detected'));
        $this->connMock->expects($this->once())->method('rollback')->willReturn(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Deadlock detected');

        $this->logger->deleteMessagesByGroupSampleId(1);
    }
}
