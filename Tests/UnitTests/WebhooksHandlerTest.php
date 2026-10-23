<?php

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use GuiBranco\ProjectsMonitor\Library\BrokerUsagePresenter;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\RequestException;
use GuiBranco\ProjectsMonitor\Library\SecretsFileNotFoundException;
use GuiBranco\ProjectsMonitor\Library\WebhooksHandler;
use PHPUnit\Framework\TestCase;

/**
 * Tests the WebhooksHandler client against a mocked HTTP boundary: the
 * bearer-authenticated GET, JSON decoding, and the rule that a response
 * without a brokers list is a load failure rather than zero usage.
 */
class WebhooksHandlerTest extends TestCase
{

    private const API_URL = 'https://handler.example.test/';


    /**
     * Builds a WebhooksHandler whose constructor (secrets file, real
     * Request) is skipped, with the private state set through reflection so
     * getBrokerUsage() and doGet() run for real against the given Request.
     *
     * @param Request $request Mocked HTTP client.
     *
     * @return WebhooksHandler
     */
    private function handlerWith(Request $request): WebhooksHandler
    {
        $config = new Configuration();
        $config->init();

        $builder = $this->getMockBuilder(WebhooksHandler::class);
        $builder->disableOriginalConstructor();
        $builder->onlyMethods([]);
        $handler = $builder->getMock();

        $this->setPrivateProperty($handler, 'apiUrl', self::API_URL);
        $this->setPrivateProperty($handler, 'headers', ['Authorization: Bearer test-token']);
        $this->setPrivateProperty($handler, 'request', $request);

        return $handler;

    }//end handlerWith()


    /**
     * Sets a private property of the class under test (test-only reflection).
     *
     * @param object $object   Instance to modify.
     * @param string $property Property name.
     * @param mixed  $value    Value to set.
     *
     * @return void
     */
    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty(WebhooksHandler::class, $property);
        $reflection->setValue($object, $value);

    }//end setPrivateProperty()


    /**
     * A mocked Request whose get() returns the given response once.
     *
     * @param Response $response What the handler answers.
     *
     * @return Request
     */
    private function requestReturning(Response $response): Request
    {
        $builder = $this->getMockBuilder(Request::class);
        $builder->disableOriginalConstructor();
        $builder->onlyMethods(['get']);
        $request = $builder->getMock();

        $expectation = $request->expects($this->once());
        $expectation->method('get');
        $expectation->with(self::API_URL.'broker/usage/current', ['Authorization: Bearer test-token']);
        $expectation->willReturn($response);

        return $request;

    }//end requestReturning()


    /**
     * A 200 with a brokers list is rendered through the presenter.
     *
     * @return void
     */
    public function testGetBrokerUsageRendersTheHandlerResponse(): void
    {
        $broker  = [
                    'broker'       => 'BROKER_URL_1',
                    'messageCount' => 10,
                    'messageCost'  => 3,
                    'usage'        => 30,
                    'monthlyLimit' => 300,
                    'remaining'    => 270,
                    'usagePercent' => 10.0,
                   ];
        $payload = [
                    'period'      => '2026-10',
                    'messageCost' => 3,
                    'brokers'     => [$broker],
                   ];
        $body    = json_encode($payload);
        $handler = $this->handlerWith($this->requestReturning(Response::success($body, self::API_URL, [])));

        $result = $handler->getBrokerUsage();

        $this->assertSame('2026-10', $result['period']);
        $this->assertSame(3, $result['messageCost']);
        $this->assertSame(BrokerUsagePresenter::TABLE_HEADER, $result['brokers'][0]);
        $this->assertSame(1, $result['total']);
        $this->assertSame(30, $result['usage']);
        $this->assertSame(300, $result['limit']);
        $this->assertSame(10.0, $result['maxPercent']);

    }//end testGetBrokerUsageRendersTheHandlerResponse()


    /**
     * A non-200 answer is a RequestException carrying the status and body.
     *
     * @return void
     */
    public function testGetBrokerUsageThrowsOnNon200(): void
    {
        $response = Response::success('nope', self::API_URL, [], 401);
        $handler  = $this->handlerWith($this->requestReturning($response));

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('GET broker/usage/current (401): nope');

        $handler->getBrokerUsage();

    }//end testGetBrokerUsageThrowsOnNon200()


    /**
     * A transport failure (status -1) reports the transport message.
     *
     * @return void
     */
    public function testGetBrokerUsageThrowsOnTransportError(): void
    {
        $response = Response::error('connection refused', self::API_URL, -1);
        $handler  = $this->handlerWith($this->requestReturning($response));

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('(-1): connection refused');

        $handler->getBrokerUsage();

    }//end testGetBrokerUsageThrowsOnTransportError()


    /**
     * A 200 with a non-JSON body is a load failure, not zero usage.
     *
     * @return void
     */
    public function testGetBrokerUsageThrowsOnNonJsonBody(): void
    {
        $response = Response::success('<html>', self::API_URL, []);
        $handler  = $this->handlerWith($this->requestReturning($response));

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('non-JSON body');

        $handler->getBrokerUsage();

    }//end testGetBrokerUsageThrowsOnNonJsonBody()


    /**
     * A 200 whose JSON has no brokers list is a load failure, not zero usage.
     *
     * @return void
     */
    public function testGetBrokerUsageThrowsWhenBrokersListIsMissing(): void
    {
        $response = Response::success('{"period":"2026-10"}', self::API_URL, []);
        $handler  = $this->handlerWith($this->requestReturning($response));

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('no brokers list');

        $handler->getBrokerUsage();

    }//end testGetBrokerUsageThrowsWhenBrokersListIsMissing()


    /**
     * Without the secrets file the real constructor fails closed, like every
     * other integration.
     *
     * @return void
     */
    public function testConstructorFailsClosedWithoutSecretsFile(): void
    {
        if (file_exists(__DIR__.'/../../Src/secrets/webhooksHandler.secrets.php') === true) {
            $this->markTestSkipped('webhooksHandler.secrets.php exists in this checkout.');
        }

        $this->expectException(SecretsFileNotFoundException::class);

        $handler = new WebhooksHandler();
        $this->assertNull($handler);

    }//end testConstructorFailsClosedWithoutSecretsFile()


}//end class
