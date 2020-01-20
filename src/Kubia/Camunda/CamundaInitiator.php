<?php

namespace Kubia\Camunda;
use Camunda\Entity\Request\ProcessInstanceRequest;
use Camunda\Service\ProcessInstanceService;
use Camunda\Entity\Request\ProcessDefinitionRequest;
use Camunda\Service\ProcessDefinitionService;
use PhpAmqpLib\Message\AMQPMessage;
use Kubia\Logger\Logger;

/**
 * Class CamundaInitiator
 * @package Kubia\Camunda
 */
class CamundaInitiator extends CamundaBaseConnector
{
    /** @var string */
    public $logOwner = 'bpm-initiator';

    /** @var bool */
    public $startAllowed = true;

    /** @var bool */
    public $processStarted = false;

    /**
     * Check running process instances with current business key
     */
    public function isProcessInstanceAlreadyStarted(): void
    {
        $this->startAllowed = true;

        if(
            isset($this->headers['camundaProcessUnique']) &&
            filter_var($this->headers['camundaProcessUnique'], FILTER_VALIDATE_BOOLEAN)
        ) {
            // count running process instances
            $processInstanceRequest = (new ProcessInstanceRequest())
                ->set('businessKey', $this->headers['camundaBusinessKey']);

            $processInstanceService = (new ProcessInstanceService($this->camundaUrl))
                ->getListCount($processInstanceRequest);

            // if process already exist
            if((int)$processInstanceService->count > 0) {
                // disallow start
                $this->startAllowed = false;
            }
        }
    }

    /**
     * Start process instance
     */
    public function startProcessInstance(): void {
        // Update variables
        $this->updatedVariables['message'] = [
            'value' => json_encode($this->message),
            'type' => 'Json'
        ];

        $processDefinitionRequest = (new ProcessDefinitionRequest())
            ->set('variables', $this->updatedVariables)
            ->set('businessKey', $this->headers['camundaBusinessKey']);

        $processDefinitionService = new ProcessDefinitionService($this->camundaUrl);
        $key = $this->camundaConfig['prefix'] . $this->headers['camundaProcessKey'];

        // request
        $processDefinitionService->startInstanceByKey($key, '', $processDefinitionRequest);

        // success
        if($processDefinitionService->getResponseCode() == 200) {
            $this->processStarted = true;
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

    /**
     * Callback
     * @param AMQPMessage $msg
     */
    public function callback(AMQPMessage $msg): void
    {
        Logger::log(sprintf("Received %s", $msg->body), 'input', $this->rmqConfig['queue'], $this->logOwner, 0);

        // Set manual acknowledge for received message
        $this->channel->basic_ack($msg->delivery_info['delivery_tag']); // manual confirm delivery message

        $this->msg = $msg;

        // Update variables
        $this->message = json_decode($msg->body, true);
        $this->headers = $this->message['headers'] ?? null;

        // Validate message
        $this->validateMessage();

        // Check running process instances with current business key
        $this->isProcessInstanceAlreadyStarted();

        if($this->startAllowed)
            $this->startProcessInstance();

        // response if is synchronous mode
        if($this->msg->has('correlation_id') && $this->msg->has('reply_to'))
            $this->sendSynchronousResponse($this->msg, $this->processStarted);
    }
}