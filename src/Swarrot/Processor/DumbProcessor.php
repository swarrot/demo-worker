<?php

namespace Swarrot\Processor;

use Swarrot\Broker\Message;
use Psr\Log\LoggerInterface;

class DumbProcessor implements ProcessorInterface
{
    protected $success;
    protected $logger;

    public function __construct($success = true, LoggerInterface $logger)
    {
        $this->success = $success;
        $this->logger  = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function process(Message $message, array $options)
    {
        if ($this->success) {
            $this->logger->info('Successfully process message #' . $message->getId());

            return;
        }

        throw new \Exception('Failed to process message #' . $message->getId());
    }
}
