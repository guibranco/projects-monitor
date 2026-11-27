<?php

require_once __DIR__.'/ApplicationsManagerTestCase.php';

use GuiBranco\ProjectsMonitor\Library\ApplicationsManager;
use GuiBranco\ProjectsMonitor\Library\InvalidApplicationException;

/**
 * Tests creating and renaming applications.
 */
class ApplicationsManagerTest extends ApplicationsManagerTestCase
{


    /**
     * Creating inserts the trimmed name, a key following the pattern and a new
     * token, and returns the full token.
     *
     * @return void
     */
    public function testCreateInsertsKeyFollowingThePatternAndReturnsFullToken(): void
    {
        $manager = $this->getManager(["WHERE `key` = ?" => [[$this->getRow([])]]]);

        $application = $manager->create("  Projects   Monitor ");

        $insert = $this->getStatements("INSERT INTO applications")[0]["values"];
        $this->assertSame("Projects Monitor", $insert[0]);
        $this->assertSame("pmk-pm-key", $insert[1]);
        $this->assertMatchesRegularExpression("/^[0-9a-f]{32}$/", $insert[2]);
        $this->assertArrayHasKey("token", $application);
        $this->assertTrue($application["keyFollowsPattern"]);

    }//end testCreateInsertsKeyFollowingThePatternAndReturnsFullToken()


    /**
     * A name used by another application is rejected.
     *
     * @return void
     */
    public function testCreateRejectsADuplicateName(): void
    {
        $manager = $this->getManager(["WHERE `name` = ?" => [[["id" => 9]]]]);

        $this->expectException(InvalidApplicationException::class);
        $manager->create("Projects Monitor");

    }//end testCreateRejectsADuplicateName()


    /**
     * An empty name is rejected.
     *
     * @return void
     */
    public function testCreateRejectsAnEmptyName(): void
    {
        $manager = $this->getManager([]);

        $this->expectException(InvalidApplicationException::class);
        $manager->create("   ");

    }//end testCreateRejectsAnEmptyName()


    /**
     * Renaming sets updated_at and keeps the key.
     *
     * @return void
     */
    public function testRenameSetsUpdatedAtAndKeepsTheKey(): void
    {
        $renamed = $this->getRow(["name" => "LogStream", "updated_at" => "2026-11-27 00:10:00"]);
        $manager = $this->getManager(["WHERE id = ?" => [[$this->getRow([])], [$renamed]]]);

        $application = $manager->rename(5, "LogStream");

        $update = $this->getStatements("UPDATE applications SET `name`");
        $this->assertCount(1, $update);
        $this->assertStringContainsString("updated_at = CURRENT_TIMESTAMP", $update[0]["sql"]);
        $this->assertSame(["LogStream", 5], $update[0]["values"]);
        $this->assertSame("pmk-pm-key", $application["key"]);
        $this->assertArrayNotHasKey("token", $application);

    }//end testRenameSetsUpdatedAtAndKeepsTheKey()


    /**
     * Renaming an unknown application returns null.
     *
     * @return void
     */
    public function testRenameReturnsNullForAnUnknownApplication(): void
    {
        $manager = $this->getManager([]);

        $this->assertNull($manager->rename(404, "Anything"));

    }//end testRenameReturnsNullForAnUnknownApplication()


    /**
     * A name used by another application is rejected when renaming.
     *
     * @return void
     */
    public function testRenameRejectsANameUsedByAnotherApplication(): void
    {
        $manager = $this->getManager(["WHERE `name` = ?" => [[["id" => 9]]], "WHERE id = ?" => [[$this->getRow([])]]]);

        $this->expectException(InvalidApplicationException::class);
        $manager->rename(5, "LogStream");

    }//end testRenameRejectsANameUsedByAnotherApplication()


    /**
     * Spaces are collapsed and names longer than the column are rejected.
     *
     * @return void
     */
    public function testGetValidNameCollapsesSpacesAndRejectsLongNames(): void
    {
        $manager = $this->getManager([]);

        $this->assertSame("Projects Monitor", $manager->getValidName("  Projects \t Monitor  ", 0));

        $this->expectException(InvalidApplicationException::class);
        $manager->getValidName(str_repeat("a", (ApplicationsManager::NAME_MAX_LENGTH + 1)), 0);

    }//end testGetValidNameCollapsesSpacesAndRejectsLongNames()


    /**
     * The fields mask the token and flag keys off the pattern.
     *
     * @return void
     */
    public function testGetFieldsMasksTheTokenAndFlagsKeysOffThePattern(): void
    {
        $application = $this->getManager([])->getFields($this->getRow(["key" => "test_application"]), false);

        $this->assertSame("••••••••cdef", $application["tokenMasked"]);
        $this->assertFalse($application["keyFollowsPattern"]);
        $this->assertSame("2026-11-26 23:00:00", $application["lastSeenAt"]);
        $this->assertNull($application["updatedAt"]);
        $this->assertArrayNotHasKey("token", $application);

    }//end testGetFieldsMasksTheTokenAndFlagsKeysOffThePattern()


}//end class
