<?php

declare(strict_types=1);

namespace Survos\WorkflowBundle\Messenger\Middleware;

use Survos\WorkflowBundle\Message\TransitionMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Jwage\PhpAmqpLibMessengerBundle\Transport\AmqpStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Dynamic routing middleware for single exchange message routing.
 * 
 * Converts TransportNamesStamp to AmqpStamp routing keys for TransitionMessage.
 * This enables runtime transport selection while using a single RabbitMQ exchange.
 * 
 * @author Survos Team
 */
final class DynamicRoutingMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        
        // Only process TransitionMessage instances
        if (!$message instanceof TransitionMessage) {
            return $stack->next()->handle($envelope, $stack);
        }

        // Check for TransportNamesStamp to override default routing
        $transportStamp = $envelope->last(TransportNamesStamp::class);
        
        if ($transportStamp !== null && !empty($transportStamp->getTransportNames())) {
            $intendedTransport = $transportStamp->getTransportNames()[0];
            
            // Skip if AmqpStamp already exists to avoid conflicts
            if ($envelope->last(AmqpStamp::class) === null) {
                // Convert transport name to AMQP routing key
                $envelope = $envelope->with(new AmqpStamp($intendedTransport));
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}

