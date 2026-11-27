<?php

require_once __DIR__.'/ApplicationsManagerTestCase.php';

use GuiBranco\ProjectsMonitor\Library\ApplicationKey;
use GuiBranco\ProjectsMonitor\Library\ApplicationsException;
use GuiBranco\ProjectsMonitor\Library\ApplicationsManager;

/**
 * Tests listing the applications and the database failures.
 */
class ApplicationsManagerQueryTest extends ApplicationsManagerTestCase
{


    /**
     * Every row is listed with the token masked.
     *
     * @return void
     */
    public function testListPresentsEveryRowWithTheTokenMasked(): void
    {
        $second = $this->getRow(["id" => 6, "name" => "LogStream", "key" => "pmk-ls-key"]);
        $result = $this->createMock(mysqli_result::class);
        $result->method('fetch_assoc')->willReturnOnConsecutiveCalls($this->getRow([]), $second, null);
        $connection = $this->createMock(mysqli::class);
        $connection->expects($this->once())->method('query')->with($this->stringContains("ORDER BY name"))->willReturn($result);

        $applications = (new ApplicationsManager($connection, new ApplicationKey()))->list();

        $this->assertCount(2, $applications);
        $this->assertSame("LogStream", $applications[1]["name"]);
        $this->assertTrue($applications[1]["keyFollowsPattern"]);
        $this->assertArrayNotHasKey("token", $applications[0]);

    }//end testListPresentsEveryRowWithTheTokenMasked()


    /**
     * A failed list query throws.
     *
     * @return void
     */
    public function testListThrowsWhenTheQueryFails(): void
    {
        $connection = $this->createMock(mysqli::class);
        $connection->method('query')->willReturn(false);

        $this->expectException(ApplicationsException::class);
        (new ApplicationsManager($connection, new ApplicationKey()))->list();

    }//end testListThrowsWhenTheQueryFails()


    /**
     * A statement that cannot be prepared throws.
     *
     * @return void
     */
    public function testPrepareFailureThrows(): void
    {
        $connection = $this->createMock(mysqli::class);
        $connection->method('prepare')->willReturn(false);

        $this->expectException(ApplicationsException::class);
        (new ApplicationsManager($connection, new ApplicationKey()))->rotateToken(5);

    }//end testPrepareFailureThrows()


    /**
     * A statement that fails to run throws.
     *
     * @return void
     */
    public function testExecuteFailureThrows(): void
    {
        $stmt = $this->createMock(mysqli_stmt::class);
        $stmt->method('bind_param')->willReturn(true);
        $stmt->method('execute')->willReturn(false);
        $connection = $this->createMock(mysqli::class);
        $connection->method('prepare')->willReturn($stmt);

        $this->expectException(ApplicationsException::class);
        $this->expectExceptionMessage("Failed to run");
        (new ApplicationsManager($connection, new ApplicationKey()))->rotateKey(5);

    }//end testExecuteFailureThrows()


    /**
     * Creating throws when the new row cannot be read back.
     *
     * @return void
     */
    public function testCreateThrowsWhenTheNewRowIsNotFound(): void
    {
        $manager = $this->getManager([]);

        $this->expectException(ApplicationsException::class);
        $manager->create("Projects Monitor");

    }//end testCreateThrowsWhenTheNewRowIsNotFound()


}//end class
