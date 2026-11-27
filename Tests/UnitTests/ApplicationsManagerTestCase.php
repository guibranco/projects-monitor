<?php

use GuiBranco\ProjectsMonitor\Library\ApplicationKey;
use GuiBranco\ProjectsMonitor\Library\ApplicationsManager;
use PHPUnit\Framework\TestCase;

/**
 * Helpers of the ApplicationsManager tests: a mocked connection that answers
 * each statement and records what was bound.
 */
abstract class ApplicationsManagerTestCase extends TestCase
{

    /**
     * The SQL and values of every statement bound.
     *
     * @var ArrayObject
     */
    protected ArrayObject $statements;


    /**
     * Starts each test with no statement recorded.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->statements = new ArrayObject();

    }//end setUp()


    /**
     * A manager on a mocked connection: each statement whose SQL contains a
     * fragment of $resultsFor gets the fragment's next result set (the last one
     * repeating), the others get no rows.
     *
     * @param array<string, array<int, array<int, array<string, mixed>>>> $resultsFor The result sets by SQL fragment.
     *
     * @return ApplicationsManager
     */
    protected function getManager(array $resultsFor): ApplicationsManager
    {
        $calls      = new ArrayObject();
        $statements = $this->statements;
        $connection = $this->createMock(mysqli::class);
        $connection->method('prepare')->willReturnCallback(
            function (string $sql) use ($resultsFor, $calls, $statements): mysqli_stmt {
                return $this->getStatement($sql, $this->getRows($sql, $resultsFor, $calls), $statements);
            }
        );

        return new ApplicationsManager($connection, new ApplicationKey());

    }//end getManager()


    /**
     * The rows the statement gets.
     *
     * @param string                                                      $sql        The statement's SQL.
     * @param array<string, array<int, array<int, array<string, mixed>>>> $resultsFor The result sets by SQL fragment.
     * @param ArrayObject                                                 $calls      How many statements each fragment answered.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getRows(string $sql, array $resultsFor, ArrayObject $calls): array
    {
        foreach ($resultsFor as $fragment => $sets) {
            if (str_contains($sql, $fragment) === true) {
                $index            = ($calls[$fragment] ?? 0);
                $calls[$fragment] = ($index + 1);
                return $sets[min($index, (count($sets) - 1))];
            }
        }

        return [];

    }//end getRows()


    /**
     * A statement returning the rows and recording its SQL and bound values.
     *
     * @param string                           $sql        The statement's SQL.
     * @param array<int, array<string, mixed>> $rows       The rows of its result.
     * @param ArrayObject                      $statements Where to record it.
     *
     * @return mysqli_stmt
     */
    protected function getStatement(string $sql, array $rows, ArrayObject $statements): mysqli_stmt
    {
        $rows[] = null;
        $result = $this->createMock(mysqli_result::class);
        $result->method('fetch_assoc')->willReturnOnConsecutiveCalls(...$rows);

        $stmt = $this->createMock(mysqli_stmt::class);
        $stmt->method('bind_param')->willReturnCallback(
            function (mixed ...$arguments) use ($sql, $statements): bool {
                $statements[] = [
                                 "sql"    => $sql,
                                 "values" => array_slice($arguments, 1),
                                ];
                return true;
            }
        );
        $stmt->method('execute')->willReturn(true);
        $stmt->method('get_result')->willReturn($result);

        return $stmt;

    }//end getStatement()


    /**
     * A row of the applications table.
     *
     * @param array<string, mixed> $overrides The columns to change.
     *
     * @return array<string, mixed>
     */
    protected function getRow(array $overrides): array
    {
        $row = [
                "id"           => 5,
                "name"         => "Projects Monitor",
                "key"          => "pmk-pm-key",
                "token"        => "0123456789abcdef0123456789abcdef",
                "created_at"   => "2026-11-01 10:00:00",
                "updated_at"   => null,
                "last_seen_at" => "2026-11-26 23:00:00",
               ];

        return array_merge($row, $overrides);

    }//end getRow()


    /**
     * The statements recorded whose SQL contains the fragment.
     *
     * @param string $fragment Part of the SQL.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getStatements(string $fragment): array
    {
        $matching = [];
        foreach ($this->statements as $statement) {
            if (str_contains($statement["sql"], $fragment) === true) {
                $matching[] = $statement;
            }
        }

        return $matching;

    }//end getStatements()


}//end class
