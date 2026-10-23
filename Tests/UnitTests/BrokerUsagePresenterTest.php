<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\BrokerUsagePresenter;

/**
 * Tests the Broker Usage table rendering from the WebhooksHandler's
 * GET /broker/usage/current body: one row per broker, CloudAMQP-counted
 * usage figures, totals and the highest per-broker percentage for the gauge.
 */
class BrokerUsagePresenterTest extends TestCase
{


    /**
     * Builds one usage response exactly as the WebhooksHandler returns it.
     *
     * @return array
     */
    protected function getHandlerResponse(): array
    {
        return [
            'period'      => '2026-10',
            'messageCost' => 3,
            'brokers'     => [
                [
                    'broker'            => 'BROKER_URL_1',
                    'period'            => '2026-10',
                    'messageCount'      => 559344,
                    'messageCost'       => 3,
                    'usage'             => 1678032,
                    'monthlyLimit'      => 2000000,
                    'remaining'         => 321968,
                    'remainingMessages' => 107322,
                    'usagePercent'      => 83.9,
                ],
                [
                    'broker'            => 'BROKER_URL_2',
                    'period'            => '2026-10',
                    'messageCount'      => 0,
                    'messageCost'       => 3,
                    'usage'             => 0,
                    'monthlyLimit'      => 1500000,
                    'remaining'         => 1500000,
                    'remainingMessages' => 500000,
                    'usagePercent'      => 0,
                ],
            ],
        ];

    }//end getHandlerResponse()


    /**
     * Header row first, then one row per broker, in handler order.
     *
     * @return void
     */
    public function testPresentRendersHeaderAndOneRowPerBroker(): void
    {
        $presenter = new BrokerUsagePresenter();

        $result = $presenter->present($this->getHandlerResponse());

        $this->assertSame(BrokerUsagePresenter::TABLE_HEADER, $result['brokers'][0]);
        $this->assertCount(3, $result['brokers']);
        $this->assertSame(2, $result['total']);
        $this->assertSame('BROKER_URL_1', $result['brokers'][1][0]);
        $this->assertSame('BROKER_URL_2', $result['brokers'][2][0]);

    }//end testPresentRendersHeaderAndOneRowPerBroker()


    /**
     * Period and message cost are passed through for the caption.
     *
     * @return void
     */
    public function testPresentCarriesPeriodAndMessageCost(): void
    {
        $presenter = new BrokerUsagePresenter();

        $result = $presenter->present($this->getHandlerResponse());

        $this->assertSame('2026-10', $result['period']);
        $this->assertSame(3, $result['messageCost']);

    }//end testPresentCarriesPeriodAndMessageCost()


    /**
     * Totals sum usage and limits across brokers.
     *
     * @return void
     */
    public function testPresentSumsUsageAndLimitAcrossBrokers(): void
    {
        $presenter = new BrokerUsagePresenter();

        $result = $presenter->present($this->getHandlerResponse());

        $this->assertSame(1678032, $result['usage']);
        $this->assertSame(3500000, $result['limit']);
        $this->assertSame(round((1678032 * 100 / 3500000), 2), $result['percent']);

    }//end testPresentSumsUsageAndLimitAcrossBrokers()


    /**
     * The gauge gets the broker nearest its cap, not the average.
     *
     * @return void
     */
    public function testPresentReportsHighestSingleBrokerPercentForTheGauge(): void
    {
        $presenter = new BrokerUsagePresenter();

        $result = $presenter->present($this->getHandlerResponse());

        $this->assertSame(83.9, $result['maxPercent']);

    }//end testPresentReportsHighestSingleBrokerPercentForTheGauge()


    /**
     * Badge alt texts carry the CloudAMQP-counted figures.
     *
     * @return void
     */
    public function testPresentRendersCloudAmqpCountedFiguresInBadges(): void
    {
        $presenter = new BrokerUsagePresenter();

        $row = $presenter->present($this->getHandlerResponse())['brokers'][1];

        $this->assertStringContainsString('559344 payloads published', $row[1]);
        $this->assertStringContainsString('1678032 of 2000000 broker-counted messages', $row[2]);
        $this->assertStringContainsString('321968 broker-counted messages left', $row[3]);
        $this->assertStringContainsString('83.9% of the monthly limit', $row[4]);

    }//end testPresentRendersCloudAmqpCountedFiguresInBadges()


    /**
     * An older handler without cost-adjusted fields degrades to cost 1.
     *
     * @return void
     */
    public function testPresentDerivesCostAdjustedFiguresWhenHandlerOmitsThem(): void
    {
        // An older handler only returns messageCount / monthlyLimit / remaining.
        $response = [
            'period'  => '2026-09',
            'brokers' => [
                [
                    'broker'       => 'BROKER_URL_1',
                    'messageCount' => 10,
                    'monthlyLimit' => 100,
                    'remaining'    => 90,
                ],
            ],
        ];

        $presenter = new BrokerUsagePresenter();

        $result = $presenter->present($response);

        $this->assertSame(1, $result['messageCost']);
        $this->assertSame(10, $result['usage']);
        $this->assertSame(100, $result['limit']);
        $this->assertSame(10.0, $result['maxPercent']);

    }//end testPresentDerivesCostAdjustedFiguresWhenHandlerOmitsThem()


    /**
     * No brokers yields a header-only table and zero totals.
     *
     * @return void
     */
    public function testPresentWithNoBrokersReturnsEmptyTotals(): void
    {
        $presenter = new BrokerUsagePresenter();

        $result = $presenter->present(['period' => '2026-10', 'messageCost' => 3, 'brokers' => []]);

        $this->assertCount(1, $result['brokers']);
        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['usage']);
        $this->assertSame(0.0, $result['percent']);
        $this->assertSame(0.0, $result['maxPercent']);

    }//end testPresentWithNoBrokersReturnsEmptyTotals()


    /**
     * Percentages with their expected badge colour.
     *
     * @return array<string, array{float, string}>
     */
    public static function percentColorProvider(): array
    {
        return [
            'well below warn' => [
                0.0,
                'green',
            ],
            'just below warn' => [
                69.99,
                'green',
            ],
            'at warn'         => [
                70.0,
                'yellow',
            ],
            'below critical'  => [
                89.99,
                'yellow',
            ],
            'at critical'     => [
                90.0,
                'red',
            ],
            'over the limit'  => [
                120.0,
                'red',
            ],
        ];

    }//end percentColorProvider()


    /**
     * Colour thresholds at 70% and 90%.
     *
     * @param float  $percent  Usage percentage.
     * @param string $expected Expected badge colour.
     *
     * @return void
     */
    #[DataProvider('percentColorProvider')]
    public function testPercentColorThresholds(float $percent, string $expected): void
    {
        $presenter = new BrokerUsagePresenter();

        $this->assertSame($expected, $presenter->percentColor($percent));

    }//end testPercentColorThresholds()


}//end class
