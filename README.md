# BPM Initiator

BPM Initiator on PHP. Using to start business process in Camunda BPM.

## Docker images
| Docker image | Version tag | Date of build |
| --- | --- | --- |
| docker.quancy.com.sg/bpm-initiator | latest | 2019-12-18 |

## Queues
- Incoming queue: `bpm_init`

## Requirements
- php7.2-cli
- php7.2-bcmath
- php-mbstring
- php-amqp
- composer
- supervisor

## Configuration constants
- CAMUNDA_API_LOGIN=`<secret>`
- CAMUNDA_API_PASS=`<secret>`
- CAMUNDA_API_URL=https://%s:%s@bpm.kubia.dev/engine-rest
- CAMUNDA_TICK_TIMEOUT=10000
- RMQ_HOST=10.8.0.58
- RMQ_PORT=5672
- RMQ_VHOST=quancy.com.sg
- RMQ_USER=`<secret>`
- RMQ_PASS=`<secret>`
- RMQ_QUEUE_IN=bpm_init
- RMQ_RECONNECT_TIMEOUT=10000
- RMQ_TICK_TIMEOUT=10000
- CAMUNDA_INITIATOR_PREFIX_KEY=key/

## Installation
```
git clone https://gitlab.com/quancy-core/bpm-initiator.git
```

## Build and run as docker container
```
docker-compose build
docker-compose up
```

## Build and run as docker container daemon
```
docker-compose build
docker-compose up -d
```

## Stop docker container daemon
```
docker-compose down
```

## Message format

```json
{
  "data": {
    "user": {
      "first_name": "John",
      "last_name": "Doe"
    },
    "account": {
      "number": "702-0124511"
    },
    "date_start": "2019-09-14",
    "date_end": "2019-10-15"
  },
  "headers": {
    "command": "createTransactionsReport",
    "camundaProcessKey": "process-connector"
  }
}
```