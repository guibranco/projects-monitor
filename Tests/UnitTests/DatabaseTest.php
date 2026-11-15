<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Database;
use GuiBranco\ProjectsMonitor\Library\RequestException;

/**
 * Tests for Database::connect(): it waits and retries only after a failure.
 */
class DatabaseTest extends TestCase
{


    /**
     * A Database whose connection attempts return the given errors in turn
     * (null = connected) and whose pause() is counted instead of sleeping.
     *
     * @param array   $errors The result of each openConnection() call.
     * @param integer $pauses How many times pause() must be called.
     *
     * @return Database
     */
    private function createDatabase(array $errors, int $pauses): Database
    {
        $database = $this->getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->onlyMethods(["openConnection", "pause"])
            ->getMock();
        $database->expects($this->exactly(count($errors)))->method("openConnection")->willReturnOnConsecutiveCalls(...$errors);
        $database->expects($this->exactly($pauses))->method("pause")->with(5);

        $connection = $this->createMock(mysqli::class);
        $connection->method("set_charset")->willReturn(true);
        $connection->method("query")->willReturn(true);

        $property = new ReflectionProperty(Database::class, "connection");
        $property->setValue($database, $connection);

        return $database;

    }//end createDatabase()


    /**
     * A connection that opens at once is used without waiting.
     *
     * @return void
     */
    public function testConnectsWithoutWaiting(): void
    {
        $this->createDatabase([null], 0)->connect();

    }//end testConnectsWithoutWaiting()


    /**
     * A failed attempt waits 5 seconds and retries once.
     *
     * @return void
     */
    public function testRetriesOnceAfterAFailure(): void
    {
        $this->createDatabase(["refused", null], 1)->connect();

    }//end testRetriesOnceAfterAFailure()


    /**
     * A failed retry is reported.
     *
     * @return void
     */
    public function testThrowsWhenTheRetryFails(): void
    {
        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("Connection failed: still refused");
        $this->createDatabase(["refused", "still refused"], 1)->connect();

    }//end testThrowsWhenTheRetryFails()


    /**
     * A Database that will try a server nobody listens on, with a wrong password.
     *
     * @return Database
     */
    private function createUnreachableDatabase(): Database
    {
        $database = $this->getMockBuilder(Database::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        foreach (["host" => "127.0.0.1", "user" => "nobody", "password" => "wrong", "database" => "nothing"] as $name => $value) {
            $property = new ReflectionProperty(Database::class, $name);
            $property->setValue($database, $value);
        }

        return $database;

    }//end createUnreachableDatabase()


    /**
     * A failed connection reports why, whether mysqli throws (the PHP 8.1+
     * default) or only sets connect_error.
     *
     * @return void
     */
    public function testOpenConnectionReportsTheFailure(): void
    {
        $method = new ReflectionMethod(Database::class, "openConnection");

        $this->assertNotEmpty($method->invoke($this->createUnreachableDatabase()));

        $previous = mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $this->assertNotEmpty(@$method->invoke($this->createUnreachableDatabase()));
        } finally {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        }

        $this->assertTrue($previous);

    }//end testOpenConnectionReportsTheFailure()


    /**
     * The pause before the retry sleeps for the given time.
     *
     * @return void
     */
    public function testPause(): void
    {
        $method  = new ReflectionMethod(Database::class, "pause");
        $started = microtime(true);
        $method->invoke($this->createUnreachableDatabase(), 0);

        $this->assertLessThan(1, (microtime(true) - $started));

    }//end testPause()


}//end class
