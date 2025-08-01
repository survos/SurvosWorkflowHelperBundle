# Dynamic Routing Middleware Documentation

## Overview

This document provides details on implementing and using the `DynamicRoutingMiddleware` within the Survos WorkflowBundle for a single exchange message routing configuration in Symfony Messenger with RabbitMQ.

## Purpose

The middleware enables runtime transport selection using `TransportNamesStamp` while utilizing a single exchange pattern in RabbitMQ to optimize resources and simplify management.

## Key Features

- **Dynamic Transport Selection**: Override default message routing at runtime.
- **Single Exchange Configuration**: All queues use a single exchange for efficient resource usage.
- **Integration with Survos WorkflowBundle**: Works seamlessly within existing workflow transitions.

## Installation

### Requirement

Ensure `jwage/phpamqplib-messenger` is installed:

```bash
composer require jwage/phpamqplib-messenger
```

### Environment Setup

Set the DSN in your `.env` file:

```bash
MESSENGER_TRANSPORT_DSN=phpamqplib://guest:guest@localhost:5672/dummy
```

### Service Configuration

Register the middleware in your `services.yaml`:

```yaml
Survos\WorkflowBundle\Messenger\Middleware\DynamicRoutingMiddleware:
    tags:
        - { name: messenger.middleware, alias: dynamic_routing }

dynamic_routing: '@Survos\WorkflowBundle\Messenger\Middleware\DynamicRoutingMiddleware'
```

### Messenger Configuration

Configure your `messenger.yaml` to use a single exchange:

```yaml
framework:
    messenger:

        failure_transport: failed

        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    exchange:
                        name: dummy_main
                        type: direct
                        durable: true
                    queues:
                        async:
                            binding_keys: [async]
                retry_strategy:
                    max_retries: 3
                    multiplier: 2

            failed:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    exchange:
                        name: dummy_main
                        type: direct
                        durable: true
                    queues:
                        failed:
                            binding_keys: [failed]
                retry_strategy:
                    max_retries: 3
                    multiplier: 2

        default_bus: messenger.bus.default

        routing:
            'Survos\WorkflowBundle\Message\AsyncTransitionMessage': async
```

## Usage

### Default Messaging

Messages are automatically routed to the default transport (`async`) if no `TransportNamesStamp` is present.

```php
$messageBus->dispatch(new TransitionMessage(...)); // Default routing
```

### Dynamic Routing with `TransportNamesStamp`

Override the default transport by attaching a `TransportNamesStamp`.

```php
$stamps = [new TransportNamesStamp(['alternative_transport'])];
$messageBus->dispatch(new TransitionMessage(...), $stamps);
```

## Benefits

- **Resource Efficiency**: Single exchange reduces overhead on RabbitMQ.
- **Flexibility**: Easily route messages to different transports as needed.
- **Scalability**: Simplifies scaling across environments.

## Testing

Ensure transports are set up:

```bash
php bin/console messenger:setup-transports
```

Verify functionality with debug commands or consumer output:

```bash
php bin/console messenger:consume async --limit=10
```

## Best Practices

- Always use `phpamqplib://` protocol in DSN for full functionality.
- Monitor RabbitMQ queues and exchanges to ensure messages are routed correctly.
- Use `TransportNamesStamp` judiciously for explicit routing where needed.
