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
        if ($this->app->runningInConsole()) {
            // Publish config file
            $this->publishes([
                __DIR__.'/../config/slack_error_notifier.php' => config_path('slack_error_notifier.php'),
            ], 'slack-error-notifier-config');
        }

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
			$logger = $logWriter->getLogger();
			
			// Add slack handler to the monologger
			$slackHandler = new SlackWebhookHandler($webhookUrl, null, null, true, null, false, true, $this->getlogLevel($logger));
			$slackHandler->pushProcessor(new MemoryUsageProcessor);
			$slackHandler->pushProcessor(new RequestDataProcessor);
			$slackHandler->pushProcessor(new WebProcessor);
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
        $logLevel = config('slack_error_notifier.log_level') ?: config('app.log_level', 'error');

        $logLevel = strtoupper($logLevel);

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
