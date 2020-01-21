#!/usr/bin/php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class AMQPResponse {
    private $response;
    private $corr_id;
    private $callback_queue;
    private $channel;

    function __construct(&$callback_queue, &$channel) {
        $this->callback_queue = $callback_queue;
        $this->channel = $channel;
    }

    function send($data = [], $queue = RMQ_QUEUE_IN) {
        $this->corr_id = uniqid();
        $delivery_data = ['correlation_id' => $this->corr_id, 'reply_to' => $this->callback_queue];
        $data = array_merge($data, $delivery_data);
        $message = new AMQPMessage(json_encode($data), $delivery_data);

        $this->channel->basic_publish($message, '', $queue);

        while(!$this->response) {
            $this->channel->wait();
        }

        return $this->response;
    }

    function onResponse(AMQPMessage $rep) {
        if($rep->get('correlation_id') == $this->corr_id) {
            $this->response = $rep->body;
        }
    }
}

print "Usage --help: php ./examples/test.php --help" . PHP_EOL;
print PHP_EOL;

// for test; remove it
for($i=0; $i<1; $i++) {
    $connection = new AMQPStreamConnection(RMQ_HOST, RMQ_PORT, RMQ_USER, RMQ_PASS, RMQ_VHOST, false, 'AMQPLAIN', null, 'en_US', 3.0, 3.0, null, true, 60);
    $channel = $connection->channel();
    $channel->confirm_select(); // change channel mode to confirm mode

    list($callback_queue) = $channel->queue_declare('', false, true, false, !false);
    $AMQPResponse = new AMQPResponse( $callback_queue, $channel );
    $channel->basic_consume($callback_queue, '', false, false, false, false, [$AMQPResponse, 'onResponse']);

    // Usage
    $options = getopt('', ['2step', 'sync', 'help']);
    if(array_key_exists('help', $options)) {
        fwrite(STDOUT, "For synchronous request use: php ./examples/test.php --sync\n");
        fwrite(STDOUT, "For two step synchronous request use: php ./examples/test.php --2step\n");
        exit(1);
    }

    if(array_key_exists('sync', $options)) {
        $filename = 'messageSync.json';
        $mode = 'sync';
    } elseif(array_key_exists('2step', $options)) {
        $filename  ='messageSync.json';
        $mode = '2step';
    } else {
        $filename  ='messageAsync.json';
        $mode = 'async';
    }

    $dataInJson = file_get_contents(__DIR__ . '/' . $filename);
    $data = json_decode($dataInJson, true);
    $dataInJson = json_encode($data);

    // make double convert for trim whitespaces
    $data = json_decode($dataInJson, true);

    print " [x] Sent '" . $dataInJson . PHP_EOL;

    if($mode === 'sync') {
        $message = new AMQPMessage(json_encode($data));
        $channel->basic_publish($message, '', RMQ_QUEUE_IN);
    } elseif ($mode === '2step') {
        $responseJson = $AMQPResponse->send($data);
        $response = json_decode($responseJson, true);

        if($response['success'] === true) {
            print " [x] Response '$responseJson'" . PHP_EOL;

            // if process instance created
            if(isset($response['camundaProcessInstanceId']) && $response['camundaProcessInstanceId'] !== '') {
                $dataListener = [
                    "headers" => [
                        "camundaListenerMessageName" => "listener-otp-create",
                        "camundaProcessInstanceId"   => $response['camundaProcessInstanceId'],
                    ],
                    'time' => time(),
                ];

                // send second synchronous request
                $responseJsonListener = $AMQPResponse->send($dataListener, 'bpm_listener');
                print " [x] Second Response '$responseJsonListener'" . PHP_EOL;

            }
        } else {
            print " [x] Response '$responseJson'" . PHP_EOL;
        }
    } else {
        $response = $AMQPResponse->send($data);
        print " [x] Response '$response'" . PHP_EOL;
    }
    $channel->close();
    $connection->close();
}
