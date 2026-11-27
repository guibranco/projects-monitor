<?php

use GuiBranco\ProjectsMonitor\Library\ApplicationsApi;
use GuiBranco\ProjectsMonitor\Library\ApplicationsManager;
use GuiBranco\ProjectsMonitor\Library\InvalidApplicationException;
use PHPUnit\Framework\TestCase;

/**
 * Tests the HTTP status and payload of the applications endpoints.
 */
class ApplicationsApiTest extends TestCase
{

    public const APPLICATION = [
                                "id"   => 5,
                                "name" => "Projects Monitor",
                                "key"  => "pmk-pm-key",
                               ];


    /**
     * A mocked manager.
     *
     * @return ApplicationsManager
     */
    private function getManager(): ApplicationsManager
    {
        $methods = [
                    "list",
                    "create",
                    "rename",
                    "rotateKey",
                    "rotateToken",
                   ];

        return $this->getMockBuilder(ApplicationsManager::class)->disableOriginalConstructor()->onlyMethods($methods)->getMock();

    }//end getManager()


    /**
     * Listing answers 200 with the applications.
     *
     * @return void
     */
    public function testListReturnsTheApplications(): void
    {
        $manager = $this->getManager();
        $manager->method("list")->willReturn([self::APPLICATION]);

        $this->assertSame([200, ["applications" => [self::APPLICATION]]], (new ApplicationsApi($manager))->list());

    }//end testListReturnsTheApplications()


    /**
     * Creating answers 201 with the application.
     *
     * @return void
     */
    public function testCreateReturns201WithTheApplication(): void
    {
        $manager = $this->getManager();
        $manager->expects($this->once())->method("create")->with("Projects Monitor")->willReturn(self::APPLICATION);

        $response = (new ApplicationsApi($manager))->create('{"name":"Projects Monitor"}');

        $this->assertSame([201, ["application" => self::APPLICATION]], $response);

    }//end testCreateReturns201WithTheApplication()


    /**
     * Creating answers 400 for invalid JSON or an invalid name.
     *
     * @return void
     */
    public function testCreateAnswers400ForInvalidJsonOrName(): void
    {
        $manager = $this->getManager();
        $manager->method("create")->willThrowException(new InvalidApplicationException("The application's name is required"));
        $api = new ApplicationsApi($manager);

        $this->assertSame([400, ["error" => "Invalid JSON provided"]], $api->create("not json"));
        $this->assertSame([400, ["error" => "The application's name is required"]], $api->create("{}"));

    }//end testCreateAnswers400ForInvalidJsonOrName()


    /**
     * Updating renames the application.
     *
     * @return void
     */
    public function testUpdateRenames(): void
    {
        $manager = $this->getManager();
        $manager->expects($this->once())->method("rename")->with(5, "LogStream")->willReturn(self::APPLICATION);

        $response = (new ApplicationsApi($manager))->update('{"id":5,"name":"LogStream"}');

        $this->assertSame([200, ["application" => self::APPLICATION]], $response);

    }//end testUpdateRenames()


    /**
     * Updating answers 400 for an invalid ID and 404 for an unknown one.
     *
     * @return void
     */
    public function testUpdateAnswers400Or404(): void
    {
        $manager = $this->getManager();
        $manager->method("rename")->willReturn(null);
        $api = new ApplicationsApi($manager);

        $this->assertSame(400, $api->update('{"id":0,"name":"x"}')[0]);
        $this->assertSame(400, $api->update('{"id":"abc","name":"x"}')[0]);
        $this->assertSame([404, ["error" => "Application not found"]], $api->update('{"id":9,"name":"x"}'));

    }//end testUpdateAnswers400Or404()


    /**
     * Rotating calls the manager for the target.
     *
     * @return void
     */
    public function testRotateDispatchesOnTheTarget(): void
    {
        $withToken          = self::APPLICATION;
        $withToken["token"] = "t";

        $manager = $this->getManager();
        $manager->expects($this->once())->method("rotateKey")->with(5)->willReturn(self::APPLICATION);
        $manager->expects($this->once())->method("rotateToken")->with(5)->willReturn($withToken);
        $api = new ApplicationsApi($manager);

        $this->assertSame(200, $api->rotate('{"id":5,"target":"key"}')[0]);
        $this->assertSame("t", $api->rotate('{"id":5,"target":"token"}')[1]["application"]["token"]);

    }//end testRotateDispatchesOnTheTarget()


    /**
     * Rotating answers 400 for an unknown target and 404 for an unknown application.
     *
     * @return void
     */
    public function testRotateAnswers400Or404(): void
    {
        $manager = $this->getManager();
        $manager->method("rotateToken")->willReturn(null);
        $api = new ApplicationsApi($manager);

        $this->assertSame([400, ["error" => "The target must be \"key\" or \"token\""]], $api->rotate('{"id":5,"target":"both"}'));
        $this->assertSame(404, $api->rotate('{"id":5,"target":"token"}')[0]);

    }//end testRotateAnswers400Or404()


    /**
     * A name that is not a string answers 400 without calling the manager.
     *
     * @return void
     */
    public function testNonStringNameAnswers400(): void
    {
        $manager = $this->getManager();
        $manager->expects($this->never())->method("create");
        $manager->expects($this->never())->method("rename");
        $api = new ApplicationsApi($manager);

        $this->assertSame([400, ["error" => "The application's name must be a string"]], $api->create('{"name":[]}'));
        $this->assertSame(400, $api->update('{"id":5,"name":42}')[0]);

    }//end testNonStringNameAnswers400()


}//end class
