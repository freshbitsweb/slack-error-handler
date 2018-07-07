<?php

namespace Freshbitsweb\SlackErrorNotifier;

use Illuminate\Support\ServiceProvider;
use Monolog\ErrorHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\WebProcessor;

class SlackErrorNotifierServiceProvider extends ServiceProvider
{
    /**
    * Publishes configuration file and registers error handler for Slack notification
    *
    * @return void
    */
    public function boot()
    {
        // Publish config file
        $this->publishes([
            __DIR__.'/../config/slack_error_notifier.php' => config_path('slack_error_notifier.php'),
        ], 'slack-error-notifier-config');

        $this->pushSlackHandlerToLogger();
    }

    /**
    * Service container bindings
    *
    * @return void
    */
    public function register()
    {
        // Users can specify only the options they actually want to override
        $this->mergeConfigFrom(
            __DIR__.'/../config/slack_error_notifier.php',
            'slack_error_notifier'
        );
    }

    /**
     * Pushes Slack webhook handler to current logger
     *
     * @return void
     */
    protected function pushSlackHandlerToLogger()
    {
        // Only if webhook URL is available
        if ($webhookUrl = config('slack_error_notifier.webhook_url')) {
            $logWriter = $this->app->make(LoggerInterface::class);
            preg_match_all('|5\.([5,6])\.([0-9]+)|', app()->version(), $r);
            if (trim(current($r[1])) == 5) {
                $logger = $logWriter->getMonolog();
            }else if (trim(current($r[1])) == 6) {
                $logger = $logWriter->getLogger();
            }

            // Add slack handler to the monologger
            $slackHandler = new SlackWebhookHandler($webhookUrl, null, null, true, null, false, true, $this->getlogLevel($logger));
            $slackHandler = $this->pushProcessors($slackHandler);
            $logger->pushHandler($slackHandler);
        }
    }

    /**
     * Fetches and returns the log level for the new handler
     *
     * @param \Monolog\Logger Logger object
     * @return int
     */
    protected function getLogLevel($logger)
    {
        $logLevel = strtoupper(env('APP_LOG_LEVEL', 'debug'));

        return constant(get_class($logger) . '::' . $logLevel);
    }

    /**
     * Pushes number of processors to the handler to add extra parameters to the log message
     *
     * @param \Monolog\Handler\SlackWebhookHandler
     * @return \Monolog\Handler\SlackWebhookHandler
     */
    protected function pushProcessors($handler)
    {
        if (config('slack_error_notifier.add_memory_usage')) {
            $handler->pushProcessor(new MemoryUsageProcessor);
        }

        $handler->pushProcessor(new RequestDataProcessor);
        $handler->pushProcessor(new WebProcessor);

        return $handler;
    }
}
