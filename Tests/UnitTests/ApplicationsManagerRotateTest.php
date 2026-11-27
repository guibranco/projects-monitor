<?php

require_once __DIR__.'/ApplicationsManagerTestCase.php';

use GuiBranco\ProjectsMonitor\Library\ApplicationsException;
use GuiBranco\ProjectsMonitor\Library\ApplicationsManager;

/**
 * Tests rotating the key and token of applications, and finding a free key.
 */
class ApplicationsManagerRotateTest extends ApplicationsManagerTestCase
{


    /**
     * The key is rebuilt from the current name and updated_at is set.
     *
     * @return void
     */
    public function testRotateKeyUsesTheCurrentName(): void
    {
        $before  = $this->getRow(["name" => "LogStream", "key" => "old_key"]);
        $after   = $this->getRow(["name" => "LogStream", "key" => "pmk-ls-key"]);
        $manager = $this->getManager(["WHERE id = ?" => [[$before], [$after]]]);

        $manager->rotateKey(5);

        $update = $this->getStatements("UPDATE applications SET `key`")[0];
        $this->assertSame(["pmk-ls-key", 5], $update["values"]);
        $this->assertStringContainsString("updated_at = CURRENT_TIMESTAMP", $update["sql"]);

    }//end testRotateKeyUsesTheCurrentName()


    /**
     * A new token is stored, updated_at is set, and the full token is returned.
     *
     * @return void
     */
    public function testRotateTokenStoresANewTokenAndReturnsIt(): void
    {
        $after   = $this->getRow(["token" => "ffffffffffffffffffffffffffffffff"]);
        $manager = $this->getManager(["WHERE id = ?" => [[$this->getRow([])], [$after]]]);

        $application = $manager->rotateToken(5);

        $update = $this->getStatements("UPDATE applications SET token")[0];
        $this->assertMatchesRegularExpression("/^[0-9a-f]{32}$/", $update["values"][0]);
        $this->assertNotSame("0123456789abcdef0123456789abcdef", $update["values"][0]);
        $this->assertStringContainsString("updated_at = CURRENT_TIMESTAMP", $update["sql"]);
        $this->assertSame("ffffffffffffffffffffffffffffffff", $application["token"]);

    }//end testRotateTokenStoresANewTokenAndReturnsIt()


    /**
     * Rotating anything of an unknown application returns null.
     *
     * @return void
     */
    public function testRotateReturnsNullForAnUnknownApplication(): void
    {
        $manager = $this->getManager([]);

        $this->assertNull($manager->rotateKey(404));
        $this->assertNull($manager->rotateToken(404));

    }//end testRotateReturnsNullForAnUnknownApplication()


    /**
     * The keys of other applications are skipped.
     *
     * @return void
     */
    public function testNextFreeKeySkipsKeysOfOtherApplications(): void
    {
        $taken   = [
                    ["key" => "pmk-pm-key"],
                    ["key" => "pmk-pm2-key"],
                   ];
        $manager = $this->getManager(["SELECT `key`" => [$taken]]);

        $this->assertSame("pmk-pm3-key", $manager->nextFreeKey("Payment Manager", 0));
        $this->assertSame(["pmk-pm%", 0], $this->getStatements("SELECT `key`")[0]["values"]);

    }//end testNextFreeKeySkipsKeysOfOtherApplications()


    /**
     * When every key is taken, an exception is thrown.
     *
     * @return void
     */
    public function testNextFreeKeyThrowsWhenEveryKeyIsTaken(): void
    {
        $taken = [["key" => "pmk-pm-key"]];
        for ($sequence = 2; $sequence <= ApplicationsManager::MAX_SEQUENCE; $sequence++) {
            $taken[] = ["key" => sprintf("pmk-pm%d-key", $sequence)];
        }

        $manager = $this->getManager(["SELECT `key`" => [$taken]]);

        $this->expectException(ApplicationsException::class);
        $manager->nextFreeKey("Projects Monitor", 0);

    }//end testNextFreeKeyThrowsWhenEveryKeyIsTaken()


    /**
     * A key that already is the one built from the name is kept, and nothing is updated.
     *
     * @return void
     */
    public function testRotateKeyKeepsAKeyThatAlreadyFollowsTheName(): void
    {
        $manager = $this->getManager(["WHERE id = ?" => [[$this->getRow([])]]]);

        $application = $manager->rotateKey(5);

        $this->assertSame("pmk-pm-key", $application["key"]);
        $this->assertCount(0, $this->getStatements("UPDATE applications"));

    }//end testRotateKeyKeepsAKeyThatAlreadyFollowsTheName()


}//end class
