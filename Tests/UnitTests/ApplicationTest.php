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
        $this->application = $this->getMockBuilder(Application::class)
                                  ->setMethods(['getApplicationId', 'validate', 'authorize'])
                                  ->getMock();
    }

    public function testGetApplicationId()
    {
        $reflection = new ReflectionClass($this->application);
        $property = $reflection->getProperty('application');
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

        $application = new Application();
        $reflection = new ReflectionClass($application);
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

        $application = new Application();
        $reflection = new ReflectionClass($application);
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

        $application = new Application();
        $reflection = new ReflectionClass($application);
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
