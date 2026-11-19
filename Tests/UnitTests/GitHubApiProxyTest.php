<?php

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\GitHubApiProxy;
use GuiBranco\ProjectsMonitor\Library\GitHubApiProxyRange;
use GuiBranco\ProjectsMonitor\Library\RequestException;
use PHPUnit\Framework\TestCase;

/**
 * Tests the github-api-proxy client against a mocked HTTP boundary: the
 * bearer-authenticated GETs to each stats endpoint, unwrapping of `data`,
 * and the rule that a failed or malformed answer is a load failure.
 */
class GitHubApiProxyTest extends TestCase
{

    private const API_URL = 'https://api.github-proxy.example.test/';


    /**
     * Builds a GitHubApiProxy whose constructor (secrets file, real Request)
     * is skipped, with the private state set through reflection.
     *
     * @param Request $request Mocked HTTP client.
     *
     * @return GitHubApiProxy
     */
    private function proxyWith(Request $request): GitHubApiProxy
    {
        $config = new Configuration();
        $config->init();

        $builder = $this->getMockBuilder(GitHubApiProxy::class);
        $builder->disableOriginalConstructor();
        $builder->onlyMethods([]);
        $proxy = $builder->getMock();

        $state = [
                  'apiUrl'  => self::API_URL,
                  'headers' => ['Authorization: Bearer test-token'],
                  'request' => $request,
                 ];
        foreach ($state as $property => $value) {
            $reflection = new ReflectionProperty(GitHubApiProxy::class, $property);
            $reflection->setValue($proxy, $value);
        }

        return $proxy;

    }//end proxyWith()


    /**
     * A stand-in Response (no HTTP) with the given status, body and message.
     *
     * @param integer $status  HTTP status, or -1 for a transport failure.
     * @param string  $body    Response body.
     * @param string  $message Transport error message.
     *
     * @return Response
     */
    private function responseWith(int $status, string $body='', string $message=''): Response
    {
        $builder = $this->getMockBuilder(Response::class);
        $builder->disableOriginalConstructor();
        $builder->onlyMethods(['getStatusCode', 'getBody', 'getMessage']);
        $response = $builder->getMock();
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn($body);
        $response->method('getMessage')->willReturn($message);

        return $response;

    }//end responseWith()


    /**
     * A Request mock answering each URL from a map; unknown URLs fail the test.
     *
     * @param array<string, Response> $responses Endpoint (relative) => response.
     *
     * @return Request
     */
    private function requestMap(array $responses): Request
    {
        $builder = $this->getMockBuilder(Request::class);
        $builder->disableOriginalConstructor();
        $builder->onlyMethods(['get']);
        $request = $builder->getMock();

        $request->method('get')->willReturnCallback(
            function (string $url, array $headers) use ($responses): Response {
                $this->assertSame(['Authorization: Bearer test-token'], $headers);
                $endpoint = substr($url, strlen(self::API_URL));
                $this->assertArrayHasKey($endpoint, $responses, 'Unexpected call: '.$endpoint);

                return $responses[$endpoint];
            }
        );

        return $request;

    }//end requestMap()


    /**
     * A 200 wrapping the value in the stats API's {since, data} envelope.
     *
     * @param array $value Data member.
     *
     * @return Response
     */
    private function dataResponse(array $value): Response
    {
        $body = [
                 'since' => '2026-11-17T17:30:00Z',
                 'data'  => $value,
                ];

        return $this->responseWith(200, json_encode($body));

    }//end dataResponse()


    /**
     * All endpoints the dashboard reads, answering 200.
     *
     * @return array<string, Response>
     */
    private function okResponses(): array
    {
        $summary = [
                    'requests'      => 3,
                    'client_errors' => 1,
                   ];
        $caller  = [
                    'caller'   => 'guibranco',
                    'requests' => 3,
                   ];
        $health  = [
                    'status'  => 'ok',
                    'version' => '0.2.0',
                   ];
        $route   = [
                    'method'   => 'GET',
                    'route'    => '/repos/{owner}/{repo}',
                    'requests' => 3,
                   ];
        $request = [
                    'caller'   => 'guibranco',
                    'method'   => 'GET',
                    'route'    => '/repos/{owner}/{repo}',
                    'status'   => 200,
                    'requests' => 3,
                   ];

        return [
                'api/v1/summary?period=24h'              => $this->dataResponse($summary),
                'api/v1/timeline?period=24h&bucket=hour' => $this->dataResponse([]),
                'api/v1/callers?period=24h&limit=50'     => $this->dataResponse([$caller]),
                'api/v1/rate-limits'                     => $this->dataResponse([]),
                'api/v1/routes?period=24h&limit=50'      => $this->dataResponse([$route]),
                'api/v1/route-usage?period=24h&limit=50' => $this->dataResponse([$request]),
                'health'                                 => $this->responseWith(200, json_encode($health)),
               ];

    }//end okResponses()


    /**
     * The dashboard is built from all stats endpoints and the health check.
     *
     * @return void
     */
    public function testGetDashboardCallsEveryEndpoint(): void
    {
        $proxy  = $this->proxyWith($this->requestMap($this->okResponses()));
        $result = $proxy->getDashboard();

        $this->assertSame(3, $result['summary']['requests']);
        $this->assertSame(1, $result['summary']['errors']);
        $this->assertSame('0.2.0', $result['health']['version']);
        $this->assertCount(2, $result['callers']);
        $this->assertCount(25, $result['timeline']);
        $this->assertCount(2, $result['routes']);
        $this->assertCount(2, $result['requests']);

    }//end testGetDashboardCallsEveryEndpoint()


    /**
     * The chosen range sets the period and timeline bucket of every stats call.
     *
     * @return void
     */
    public function testGetDashboardUsesTheRange(): void
    {
        $responses = [];
        foreach ($this->okResponses() as $endpoint => $response) {
            $endpoint = str_replace('period=24h&bucket=hour', 'period=30d&bucket=day', $endpoint);
            $responses[str_replace('period=24h', 'period=30d', $endpoint)] = $response;
        }

        $proxy  = $this->proxyWith($this->requestMap($responses));
        $result = $proxy->getDashboard(new GitHubApiProxyRange('30d'));

        $this->assertSame('30d', $result['range']['key']);
        $this->assertCount(31, $result['timeline']);

    }//end testGetDashboardUsesTheRange()


    /**
     * A non-200 answer (e.g. the allow-list's 403) is a load failure.
     *
     * @return void
     */
    public function testNon200IsALoadFailure(): void
    {
        $responses = $this->okResponses();
        $responses['api/v1/summary?period=24h'] = $this->responseWith(403, '{"message":"not allowed"}');
        $proxy = $this->proxyWith($this->requestMap($responses));

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('(403)');
        $proxy->getDashboard();

    }//end testNon200IsALoadFailure()


    /**
     * A transport failure reports the transport message.
     *
     * @return void
     */
    public function testTransportFailureReportsTheMessage(): void
    {
        $responses = $this->okResponses();
        $responses['api/v1/summary?period=24h'] = $this->responseWith(-1, '', 'Could not resolve host');
        $proxy = $this->proxyWith($this->requestMap($responses));

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Could not resolve host');
        $proxy->getDashboard();

    }//end testTransportFailureReportsTheMessage()


    /**
     * A 200 without a data member is a load failure, never empty stats.
     *
     * @return void
     */
    public function testMissingDataIsALoadFailure(): void
    {
        $responses = $this->okResponses();
        $responses['api/v1/callers?period=24h&limit=50'] = $this->responseWith(200, '{"since":"x"}');
        $proxy = $this->proxyWith($this->requestMap($responses));

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('no data member');
        $proxy->getDashboard();

    }//end testMissingDataIsALoadFailure()


}//end class
