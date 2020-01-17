# BPM Initiator
BPM Initiator on PHP. Using to start business process in Camunda BPM.

## Docker images
| Docker image | Version tag | Date of build |
| --- | --- | --- |
| docker.quancy.com.sg/bpm-initiator | latest | 2020-01-17 |
| docker.quancy.com.sg/bpm-initiator | 0.2 | 2020-01-17 |
| docker.quancy.com.sg/bpm-initiator | 0.1 | 2019-12-20 |

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

## Examples for test initialise process
For test initialize process in synchronous mode
```
php ./examples/test.php -- sync
```

For test initialize process in asynchronous mode
```
php ./examples/test.php -- async
```

## Message format
- see `./examples/messageSync.json` for synchronous mode
- see `./examples/messageAsync.json` for asynchronous mode

```json
{
  "data": {
    "parameters": {
      "type": "sms",
      "key": "79990001122",
      "object": "79990001122"
    },
    "user": {
      "id": 339,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john.doe@kubia.com",
      "email_validated": true,
      "phone": "9990001122",
      "code": "7",
      "address": "Address",
      "status": "ACTIVE",
      "active": true,
      "doc_types": [
        1,
        2,
        3,
        4
      ],
      "doc_required": true,
      "reg_state": 5,
      "send_to_crm": "Y",
      "avatar": {
        "32": "https://sandbox-storage.quancy.com.sg/avatars/23d/32-264ae03c05d1934bd3bbbc79480f1.jpg",
        "64": "https://sandbox-storage.quancy.com.sg/avatars/23d/64-264ae03c05d1934bd3bbbc79480f1.jpg",
        "128": "https://sandbox-storage.quancy.com.sg/avatars/23d/128-264ae03c05d1934bd3bbbc79480f1.jpg"
      },
      "qr": "...",
      "type": "Corporate",
      "phone_formatted": "+7 999 000-11-22",
      "demo": 1,
      "required_documents": null,
      "balance": {
        "amount": "365",
        "currency": "USD"
      }
    }
  },
  "headers": {
    "camundaProcessKey": "connector-ms-otp",
    "camundaBusinessKey": "user-corp-38249",
    "camundaProcessUnique": true
  }
}
```