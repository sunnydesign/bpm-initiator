<?php

namespace Kubia\Camunda;

use Camunda\Entity\Request\ProcessDefinitionRequest;
use Camunda\Service\ProcessDefinitionService;
use PhpAmqpLib\Message\AMQPMessage;
use Quancy\Logger\Logger;

/**
 * Class CamundaInitiator
 * @package Kubia\Camunda
 */
class CamundaInitiator extends CamundaBaseConnector
{
    /** @var string */
    public $logOwner = 'bpm-initiator';

    /**
     * Callback
     * @param AMQPMessage $msg
     */
    public function callback(AMQPMessage $msg): void
    {
        Logger::log(sprintf("Received %s", $msg->body), 'input', $this->rmqConfig['queue'], $this->logOwner, 0 );

        // Set manual acknowledge for received message
        $this->channel->basic_ack($msg->delivery_info['delivery_tag']); // manual confirm delivery message

        // Update variables
        $this->message = json_decode($msg->body, true);
        $this->headers = $this->message['headers'] ?? null;

        // Validate message
        $this->validateMessage();

        // Update variables
        $this->updatedVariables['message'] = [
            'value' => json_encode($this->message),
            'type' => 'Json'
        ];

        $processDefinitionRequest = (new ProcessDefinitionRequest())
            ->set('variables', $this->updatedVariables);

        $processDefinitionService = new ProcessDefinitionService($this->camundaUrl);
        $key = $this->camundaConfig['prefix'] . $this->headers['camundaProcessKey'];

        // request
        $processDefinitionService->startInstanceByKey($key, '', $processDefinitionRequest);

        // success
        if($processDefinitionService->getResponseCode() == 200) {
            $logMessage = sprintf(
                "Process instance <%s> from process <%s> is launched",
                $processDefinitionService->getResponseContents()->id,
                $this->headers['camundaProcessKey']
            );
            Logger::log($logMessage, 'input', $this->rmqConfig['queue'], $this->logOwner, 0 );
        } else {
            $logMessage = sprintf(
                "Process instance from process <%s> is not launched, because `%s`",
                $this->headers['camundaProcessKey'],
                $processDefinitionService->getResponseContents()->message ?? $this->requestErrorMessage
            );
            Logger::log($logMessage, 'input', $this->rmqConfig['queue'], $this->logOwner, 1 );
        }
    }
}