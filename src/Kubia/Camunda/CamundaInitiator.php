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

    /**
     * Check running process instances with current business key
     * @return bool
     */
    public function isProcessInstanceAlreadyStarted(): bool
    {
        $isAlreadyStarted = false;

        if(
            isset($this->headers['camundaProcessUnique']) &&
            filter_var($this->headers['camundaProcessUnique'], FILTER_VALIDATE_BOOLEAN)
        ) {
            // count running process instances
            $processInstanceRequest = (new ProcessInstanceRequest())
                ->set('businessKey', $this->headers['camundaBusinessKey']);

            // request
            $processInstanceService = (new ProcessInstanceService($this->camundaUrl))
                ->getListCount($processInstanceRequest);

            // if process already exist
            if((int)$processInstanceService->count > 0) {
                // disallow start
                $this->requestErrorMessage = 'Process already exists';
                $isAlreadyStarted = true;
            }
        }

        return $isAlreadyStarted;
    }

    /**
     * Start process instance
     * @return string
     */
    public function startProcessInstance(): string
    {
        // Update variables
        $this->updatedVariables['message'] = [
            'value' => json_encode($this->message),
            'type' => 'Json'
        ];

        // preparing
        $processDefinitionRequest = (new ProcessDefinitionRequest())
            ->set('variables', $this->updatedVariables)
            ->set('businessKey', $this->headers['camundaBusinessKey']);

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
            Logger::stdout($logMessage, 'input', $this->rmqConfig['queue'], $this->logOwner, 0 );
            if(isset($this->rmqConfig['queueLog'])) {
                Logger::elastic('bpm',
                    'started',
                    '',
                    $this->data,
                    $this->headers,
                    [],
                    $this->channel,
                    $this->rmqConfig['queueLog']
                );
            }

            return $processDefinitionService->getResponseContents()->id;
        } else {
            $logMessage = sprintf(
                "Process instance from process <%s> is not launched, because `%s`",
                $this->headers['camundaProcessKey'],
                $processDefinitionService->getResponseContents()->message ?? $this->requestErrorMessage
            );
            $this->logError($logMessage);

            return null;
        }
    }

    /**
     * Logging if system error
     * @param string $message
     */
    public function logError(string $message): void
    {
        Logger::stdout($message, 'input', $this->rmqConfig['queue'], $this->logOwner, 1 );

        if(isset($this->rmqConfig['queueLog'])) {
            Logger::elastic('bpm',
                'error',
                '',
                $this->data,
                $this->headers,
                ['type' => 'system', 'message' => $message],
                $this->channel,
                $this->rmqConfig['queueLog']
            );
        }
    }

    /**
     * Callback
     * @param AMQPMessage $msg
     */
    public function callback(AMQPMessage $msg): void
    {
        Logger::stdout(sprintf("Received %s", $msg->body), 'input', $this->rmqConfig['queue'], $this->logOwner, 0);

        $this->requestErrorMessage = 'Request error';

        // Set manual acknowledge for received message
        $this->channel->basic_ack($msg->delivery_info['delivery_tag']); // manual confirm delivery message

        $this->msg = $msg;

        // Update variables
        $this->message = json_decode($msg->body, true);
        $this->headers = $this->message['headers'] ?? null;

        // Validate message
        $this->validateMessage();

        // Check running process instances with current business key
        $isAlreadyStarted = $this->isProcessInstanceAlreadyStarted();

        // add correlation_id and reply_to to process variables if is synchronous request
        $this->mixRabbitCorrelationInfo();

        $processInstanceId = !$isAlreadyStarted ? $this->startProcessInstance() : null;

        $processStarted = (bool)$processInstanceId;

        // response if is synchronous mode
        if (!$processStarted && $this->msg->has('correlation_id') && $this->msg->has('reply_to'))
            $this->sendSynchronousResponse($this->msg, false);
    }
}