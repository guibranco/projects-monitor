<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Application;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\Database;

class ApplicationTest extends TestCase
{
    private $application;

    protected function setUp(): void
    {
        // onlyMethods([]): mock nothing — getApplicationId() must run for real so
        // testGetApplicationId can verify it reads the injected $application property.
        // Without disableOriginalConstructor(), a bare getMock() mocks every public
        // method by default, which is not what any test here wants.
        $this->application = $this->getMockBuilder(Application::class)
                                  ->disableOriginalConstructor()
                                  ->onlyMethods([])
                                  ->getMock();
    }

    public function testGetApplicationId()
    {
        // Reflect the real Application class, not the mock subclass — a mock's own
        // ReflectionClass can't see private properties declared only on its parent.
        $property = (new ReflectionClass(Application::class))->getProperty('application');
        $property->setAccessible(true);
        $property->setValue($this->application, ['id' => 123]);

        $this->assertEquals(123, $this->application->getApplicationId());
    }

    public function testValidateSuccess()
    {
        $configMock = $this->createMock(Configuration::class);
        $configMock->method('getRequestHeaders')->willReturn([
            'X-API-KEY' => 'test_key',
            'X-API-TOKEN' => 'test_token'
        ]);

        $databaseMock = $this->createMock(Database::class);
        $databaseMock->method('getConnection')->willReturn($this->getMockedConnection(true));

        // disableOriginalConstructor(): Application::__construct() unconditionally
        // opens a live MySQL connection via `new Database()` — running it here would
        // require a real, reachable DB no matter what's injected afterward below.
        // onlyMethods([]): mock nothing — a bare getMock() would otherwise mock every
        // public method by default, but this test needs the real validate()/authorize().
        $application = $this->getMockBuilder(Application::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        // Reflect the real Application class, not the mock subclass — a mock's own
        // ReflectionClass can't see private properties declared only on its parent.
        $reflection = new ReflectionClass(Application::class);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $configProperty->setValue($application, $configMock);

        $databaseProperty = $reflection->getProperty('database');
        $databaseProperty->setAccessible(true);
        $databaseProperty->setValue($application, $databaseMock);

        $this->assertTrue($application->validate());
    }

    public function testValidateFailure()
    {
        $configMock = $this->createMock(Configuration::class);
        $configMock->method('getRequestHeaders')->willReturn([
            'X-API-KEY' => 'test_key',
            'X-API-TOKEN' => 'test_token'
        ]);

        $databaseMock = $this->createMock(Database::class);
        $databaseMock->method('getConnection')->willReturn($this->getMockedConnection(false));

        // disableOriginalConstructor(): Application::__construct() unconditionally
        // opens a live MySQL connection via `new Database()` — running it here would
        // require a real, reachable DB no matter what's injected afterward below.
        // onlyMethods([]): mock nothing — a bare getMock() would otherwise mock every
        // public method by default, but this test needs the real validate()/authorize().
        $application = $this->getMockBuilder(Application::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        // Reflect the real Application class, not the mock subclass — a mock's own
        // ReflectionClass can't see private properties declared only on its parent.
        $reflection = new ReflectionClass(Application::class);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $configProperty->setValue($application, $configMock);

        $databaseProperty = $reflection->getProperty('database');
        $databaseProperty->setAccessible(true);
        $databaseProperty->setValue($application, $databaseMock);

        $this->assertFalse($application->validate());
    }

    public function testAuthorizeFailure()
    {
        $configMock = $this->createMock(Configuration::class);
        $configMock->method('getRequestHeaders')->willReturn([]);

        // disableOriginalConstructor(): Application::__construct() unconditionally
        // opens a live MySQL connection via `new Database()` — running it here would
        // require a real, reachable DB no matter what's injected afterward below.
        // onlyMethods([]): mock nothing — a bare getMock() would otherwise mock every
        // public method by default, but this test needs the real validate()/authorize().
        $application = $this->getMockBuilder(Application::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        // Reflect the real Application class, not the mock subclass — a mock's own
        // ReflectionClass can't see private properties declared only on its parent.
        $reflection = new ReflectionClass(Application::class);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $configProperty->setValue($application, $configMock);

        $this->assertFalse($application->authorize());
    }

    private function getMockedConnection($valid)
    {
        $stmtMock = $this->createMock(mysqli_stmt::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('get_result')->willReturn($this->getMockedResult($valid));

        $connMock = $this->createMock(mysqli::class);
        $connMock->method('prepare')->willReturn($stmtMock);

        return $connMock;
    }

    private function getMockedResult($valid)
    {
        $resultMock = $this->createMock(mysqli_result::class);
        $resultMock->method('fetch_array')->willReturn($valid ? ['id' => 123] : null);

        return $resultMock;
    }
}
