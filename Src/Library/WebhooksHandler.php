<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Client for the WebhooksHandler (guibranco/WebhooksHandler) bearer-token
 * protected /broker endpoints — the C# service that forwards GitHub webhook
 * payloads to the LavinMQ brokers. Distinct from Webhooks (the PHP consumer's
 * API) and RabbitMq (the brokers' own management API).
 */
class WebhooksHandler extends BearerTokenApiClient
{


    /**
     * Loads the handler URL and token from webhooksHandler.secrets.php.
     *
     * @throws SecretsFileNotFoundException When the secrets file is missing.
     */
    public function __construct()
    {
        $this->initialise('webhooksHandler.secrets.php', 'handlerApiUrl', 'handlerApiToken');

    }//end __construct()


    /**
     * Name used in error messages.
     *
     * @return string
     */
    protected function serviceName(): string
    {
        return 'WebhooksHandler API';

    }//end serviceName()


    /**
     * Current-month broker usage for every configured broker connection, as
     * reported by GET /broker/usage/current, rendered for the dashboard
     * (see BrokerUsagePresenter). A 200 whose body has no `brokers` list is
     * treated as a load failure, never as zero usage.
     *
     * @return array
     *
     * @throws RequestException When the handler answers without a brokers list.
     */
    public function getBrokerUsage(): array
    {
        $usage = $this->doGet('broker/usage/current');
        if (isset($usage['brokers']) === false || is_array($usage['brokers']) === false) {
            throw new RequestException(
                'WebhooksHandler usage response has no brokers list: GET broker/usage/current'
            );
        }

        $presenter = new BrokerUsagePresenter();

        return $presenter->present($usage);

    }//end getBrokerUsage()


    /**
     * Throttle settings and the repositories the per-repository throttle has
     * sent to the Throttled Queue, as reported by GET /broker/throttle,
     * rendered for the dashboard (see ThrottledRepositoriesPresenter). A 200
     * whose body has no `repositories` list is treated as a load failure,
     * never as "nothing throttled".
     *
     * @return array
     *
     * @throws RequestException When the handler answers without a repositories list.
     */
    public function getThrottledRepositories(): array
    {
        $status = $this->doGet('broker/throttle');
        if (isset($status['repositories']) === false || is_array($status['repositories']) === false) {
            throw new RequestException(
                'WebhooksHandler throttle response has no repositories list: GET broker/throttle'
            );
        }

        $presenter = new ThrottledRepositoriesPresenter();

        return $presenter->present($status);

    }//end getThrottledRepositories()


}//end class
