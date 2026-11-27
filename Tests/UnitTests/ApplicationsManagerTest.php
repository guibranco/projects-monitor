<?php

use GuiBranco\ProjectsMonitor\Library\ApplicationsManager;
use PHPUnit\Framework\TestCase;

class ApplicationsManagerTest extends TestCase
{
    /**
     * A mysqli mock answering each prepared statement from $resultsFor (SQL
     * fragment => result sets, first match wins; each statement matching the
     * fragment gets the next result set, the last one repeating) and recording
     * the SQL and bound values.
     *
     * @param array<string, array<int, array<int, array<string, mixed>>>> $resultsFor
     */
    private function connection(array $resultsFor, ArrayObject $log): mysqli
    {
        $calls = [];
        $connection = $this->createMock(mysqli::class);
        $connection->method('prepare')->willReturnCallback(
            function (string $sql) use ($resultsFor, $log, &$calls): mysqli_stmt {
                $rows = [];
                foreach ($resultsFor as $fragment => $sets) {
                    if (str_contains($sql, $fragment)) {
                        $index = $calls[$fragment] ?? 0;
                        $calls[$fragment] = $index + 1;
                        $rows = $sets[min($index, count($sets) - 1)] ?? [];
                        break;
                    }
                }

                $result = $this->createMock(mysqli_result::class);
                $result->method('fetch_assoc')->willReturnOnConsecutiveCalls(...array_merge($rows, [null]));

                $stmt = $this->createMock(mysqli_stmt::class);
                $stmt->method('bind_param')->willReturnCallback(
                    function (string $types, mixed ...$values) use ($sql, $log): bool {
                        $log[] = ["sql" => $sql, "values" => $values];
                        return true;
                    }
                );
                $stmt->method('execute')->willReturn(true);
                $stmt->method('get_result')->willReturn($result);

                return $stmt;
            }
        );

        return $connection;
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            "id" => 5,
            "name" => "Projects Monitor",
            "key" => "pmk-pm-key",
            "token" => "0123456789abcdef0123456789abcdef",
            "created_at" => "2026-11-01 10:00:00",
            "updated_at" => null,
            "last_seen_at" => "2026-11-26 23:00:00",
        ], $overrides);
    }

    private function statements(ArrayObject $log, string $fragment): array
    {
        return array_values(array_filter((array) $log, fn ($entry) => str_contains($entry["sql"], $fragment)));
    }

    public function testNextFreeKeySkipsKeysOfOtherApplications(): void
    {
        $log = new ArrayObject();
        $manager = new ApplicationsManager($this->connection([
            "SELECT `key`" => [[["key" => "pmk-pm-key"], ["key" => "pmk-pm2-key"]]],
        ], $log));

        $this->assertSame("pmk-pm3-key", $manager->nextFreeKey("Payment Manager", 0));
        $this->assertSame(["pmk-pm%", 0], $this->statements($log, "SELECT `key`")[0]["values"]);
    }

    public function testCreateInsertsKeyFollowingThePatternAndReturnsFullToken(): void
    {
        $log = new ArrayObject();
        $manager = new ApplicationsManager($this->connection([
            "SELECT `key`" => [[]],
            "WHERE `name` = ?" => [[]],
            "WHERE `key` = ?" => [[$this->row()]],
        ], $log));

        $application = $manager->create("  Projects   Monitor ");

        $insert = $this->statements($log, "INSERT INTO applications")[0]["values"];
        $this->assertSame("Projects Monitor", $insert[0]);
        $this->assertSame("pmk-pm-key", $insert[1]);
        $this->assertMatchesRegularExpression("/^[0-9a-f]{32}$/", $insert[2]);
        $this->assertArrayHasKey("token", $application);
        $this->assertTrue($application["keyFollowsPattern"]);
    }

    public function testCreateRejectsADuplicateName(): void
    {
        $log = new ArrayObject();
        $manager = new ApplicationsManager($this->connection([
            "WHERE `name` = ?" => [[["id" => 9]]],
        ], $log));

        $this->expectException(InvalidArgumentException::class);
        $manager->create("Projects Monitor");
    }

    public function testCreateRejectsAnEmptyName(): void
    {
        $manager = new ApplicationsManager($this->connection([], new ArrayObject()));

        $this->expectException(InvalidArgumentException::class);
        $manager->create("   ");
    }

    public function testRenameSetsUpdatedAtAndKeepsTheKey(): void
    {
        $log = new ArrayObject();
        $manager = new ApplicationsManager($this->connection([
            "WHERE `name` = ?" => [[]],
            "WHERE id = ?" => [[$this->row()], [$this->row(["name" => "LogStream", "updated_at" => "2026-11-27 00:10:00"])]],
        ], $log));

        $application = $manager->rename(5, "LogStream");

        $update = $this->statements($log, "UPDATE applications SET `name`");
        $this->assertCount(1, $update);
        $this->assertStringContainsString("updated_at = CURRENT_TIMESTAMP", $update[0]["sql"]);
        $this->assertSame(["LogStream", 5], $update[0]["values"]);
        $this->assertArrayNotHasKey("token", $application);
    }

    public function testRenameReturnsNullForAnUnknownApplication(): void
    {
        $manager = new ApplicationsManager($this->connection(["WHERE id = ?" => [[]]], new ArrayObject()));

        $this->assertNull($manager->rename(404, "Anything"));
    }

    public function testRotateKeyUsesTheCurrentName(): void
    {
        $log = new ArrayObject();
        $manager = new ApplicationsManager($this->connection([
            "SELECT `key`" => [[]],
            "WHERE id = ?" => [[$this->row(["name" => "LogStream", "key" => "old_key"])], [$this->row(["name" => "LogStream", "key" => "pmk-ls-key"])]],
        ], $log));

        $manager->rotateKey(5);

        $update = $this->statements($log, "UPDATE applications SET `key`")[0];
        $this->assertSame(["pmk-ls-key", 5], $update["values"]);
        $this->assertStringContainsString("updated_at = CURRENT_TIMESTAMP", $update["sql"]);
    }

    public function testRotateTokenStoresANewTokenAndReturnsIt(): void
    {
        $log = new ArrayObject();
        $manager = new ApplicationsManager($this->connection([
            "WHERE id = ?" => [[$this->row()], [$this->row(["token" => "ffffffffffffffffffffffffffffffff"])]],
        ], $log));

        $application = $manager->rotateToken(5);

        $update = $this->statements($log, "UPDATE applications SET token")[0];
        $this->assertMatchesRegularExpression("/^[0-9a-f]{32}$/", $update["values"][0]);
        $this->assertNotSame("0123456789abcdef0123456789abcdef", $update["values"][0]);
        $this->assertStringContainsString("updated_at = CURRENT_TIMESTAMP", $update["sql"]);
        $this->assertSame("ffffffffffffffffffffffffffffffff", $application["token"]);
    }

    public function testPresentMasksTheTokenAndFlagsKeysOffThePattern(): void
    {
        $manager = new ApplicationsManager($this->connection([], new ArrayObject()));

        $application = $manager->present($this->row(["key" => "test_application"]), false);

        $this->assertSame("••••••••cdef", $application["tokenMasked"]);
        $this->assertFalse($application["keyFollowsPattern"]);
        $this->assertSame("2026-11-26 23:00:00", $application["lastSeenAt"]);
        $this->assertNull($application["updatedAt"]);
        $this->assertArrayNotHasKey("token", $application);
    }
}
