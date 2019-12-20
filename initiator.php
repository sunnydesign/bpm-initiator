#!/usr/bin/php

<?php
/**
 * Camunda Process Initiator
 */

sleep(1); // timeout for start through supervisor

require_once __DIR__ . '/vendor/autoload.php';

// Libs
use Camunda\Entity\Request\ProcessDefinitionRequest;
use Camunda\Service\ProcessDefinitionService;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Quancy\Logger\Logger;

// Config
$config = __DIR__ . '/config.php';
$config_env = __DIR__ . '/config.env.php';
if (is_file($config)) {
    require_once $config;
} elseif (is_file($config_env)) {
    require_once $config_env;
}

/**
 * Close connection
 *
 * @param $connection
 */
function cleanup_connection($connection) {
    // Connection might already be closed.
    // Ignoring exceptions.
    try {
        if($connection !== null) {
            $connection->close();
        }
    } catch (\ErrorException $e) {
    }
}

/**
 * Shutdown
 *
 * @param $connection
 */
function shutdown($connection)
{
    //$channel->close();
    $connection->close();
}

/**
 * Validate message
 */
function validate_message($message) {
    // Headers
    if(!isset($message['headers'])) {
        $logMessage = '`headers` is not set in incoming message';
        Logger::log($logMessage, 'input', RMQ_QUEUE_IN,'bpm-initiator', 1);
        exit(1);
    }

    // Unsafe parameters in headers
    $unsafeHeadersParams = ['camundaProcessKey'];

    foreach ($unsafeHeadersParams as $paramName) {
        if(!isset($message['headers'][$paramName])) {
            $logMessage = '`' . $paramName . '` param is not set in incoming message';
            Logger::log($logMessage, 'input', RMQ_QUEUE_IN,'bpm-initiator', 1);
            exit(1);
        }
    }
}

/**
 * Callback
 *
 * @param $msg
 */
$callback = function($msg) {
    Logger::log(sprintf("Received %s", $msg->body), 'input', RMQ_QUEUE_IN,'bpm-initiator', 0 );

    // Set manual acknowledge for received message
    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']); // manual confirm delivery message

    // Send a message with the string "quit" to cancel the consumer.
    if ($msg->body === 'quit') {
        $msg->delivery_info['channel']->basic_cancel($msg->delivery_info['consumer_tag']);
    }

    // Update variables
    $message = json_decode($msg->body, true);

    // Validate message
    validate_message($message);

    // Update variables
    $updateVariables['message'] = [
        'value' => json_encode($message),
        'type' => 'Json'
    ];

    // REQUEST to API
    $camundaUrl = sprintf(CAMUNDA_API_URL, CAMUNDA_API_LOGIN, CAMUNDA_API_PASS); // camunda api with basic auth
    $processDefinitionRequest = (new ProcessDefinitionRequest())
        ->set('variables', $updateVariables);

    $processDefinitionService = new ProcessDefinitionService($camundaUrl);
    $key = CAMUNDA_INITIATOR_PREFIX_KEY . $message['headers']['camundaProcessKey'];

    // request
    $processDefinitionService->startInstanceByKey($key, '', $processDefinitionRequest);

    // success
    if($processDefinitionService->getResponseCode() == 200) {
        $logMessage = sprintf(
            "Process instance <%s> from process <%s> is launched",
            $processDefinitionService->getResponseContents()->id,
            $message['headers']['camundaProcessKey']
        );
        Logger::log($logMessage, 'input', RMQ_QUEUE_IN,'bpm-initiator', 0 );
    } else {
        $logMessage = sprintf(
            "Process instance from process <%s> is not launched, because `%s`",
            $message['headers']['camundaProcessKey'],
            $processDefinitionService->getResponseContents()->message ?? 'Request error'
        );
        Logger::log($logMessage, 'input', RMQ_QUEUE_IN,'bpm-initiator', 1 );
    }
};

/**
 * Loop
 */
$connection = null;
while(true) {
    try {
        $connection = new AMQPStreamConnection(RMQ_HOST, RMQ_PORT, RMQ_USER, RMQ_PASS, RMQ_VHOST, false, 'AMQPLAIN', null, 'en_US', 3.0, 3.0, null, true, 60);
        register_shutdown_function('shutdown', $connection);

        Logger::log('Waiting for messages. To exit press CTRL+C', '-', '-','bpm-connector-in', 0);

        // Your application code goes here.
        $channel = $connection->channel();
        $channel->confirm_select(); // change channel mode to confirm mode
        $channel->basic_qos(0, 1, false); // one message in one loop
        $channel->basic_consume(RMQ_QUEUE_IN, '', false, false, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait(null, true, 0);
            usleep(RMQ_TICK_TIMEOUT);
        }

    } catch(AMQPRuntimeException $e) {
        echo $e->getMessage() . PHP_EOL;
        cleanup_connection($connection);
        usleep(RMQ_RECONNECT_TIMEOUT);
    } catch(\RuntimeException $e) {
        echo "Runtime exception " . PHP_EOL;
        cleanup_connection($connection);
        usleep(RMQ_RECONNECT_TIMEOUT);
    } catch(\ErrorException $e) {
        echo "Error exception " . PHP_EOL;
        cleanup_connection($connection);
        usleep(RMQ_RECONNECT_TIMEOUT);
    }
}