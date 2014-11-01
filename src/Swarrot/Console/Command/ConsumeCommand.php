<?php

namespace Swarrot\Console\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Swarrot\Consumer;
use Symfony\Component\Console\Command\Command;
use Swarrot\Broker\MessageProviderInterface;
use Swarrot\Processor\DumbProcessor;
use Swarrot\Broker\MessageProvider\PeclPackageMessageProvider;
use Swarrot\Processor\Stack;
use Psr\Log\LoggerInterface;

class ConsumeCommand extends Command
{
    protected $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;

        parent::__construct();
    }

    public function configure()
    {
        $this
            ->setName('consume')
            ->setDescription('Consume a queue.')
            ->addArgument('queue', InputArgument::REQUIRED, 'The queue to consume')
            ->addArgument('vhost', InputArgument::OPTIONAL, 'In which vhost is the queue?', '/')
            ->addOption('fail', '', InputOption::VALUE_NONE, 'If activated, an exception will be thrown in the processor')
            ->addOption('max_messages', 'm', InputOption::VALUE_REQUIRED, 'Max messages to process.', 100)
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // We create a connection to an AMQP broker and retrieve the queue "mail"
        $connection = new \AMQPConnection([
            'vhost' => $input->getArgument('vhost')
        ]);
        $connection->connect();
        $channel = new \AMQPChannel($connection);
        $queue = new \AMQPQueue($channel);
        $queue->setName($input->getArgument('queue'));

        $messageProvider = new PeclPackageMessageProvider($queue);

        // We create a basic processor which use \SwiftMailer to send mails
        $processor = new DumbProcessor(
            !$input->getOption('fail'),
            $this->logger
        );
        $stack = (new Stack\Builder())
            ->push('Swarrot\Processor\SignalHandler\SignalHandlerProcessor', $this->logger)
            ->push('Swarrot\Processor\MaxMessages\MaxMessagesProcessor', $this->logger)
            ->push('Swarrot\Processor\ExceptionCatcher\ExceptionCatcherProcessor', $this->logger)
            ->push('Swarrot\Processor\MaxExecutionTime\MaxExecutionTimeProcessor', $this->logger)
            ->push('Swarrot\Processor\Ack\AckProcessor', $messageProvider, $this->logger)
            ->push('Swarrot\Processor\InstantRetry\InstantRetryProcessor', $this->logger)
        ;

        // We can now create a Consumer with a message Provider and a Processor
        $consumer = new Consumer(
            $messageProvider,
            $stack->resolve($processor),
            null,
            $this->logger
        );

        return $consumer->consume([
            'max_messages' => (int) $input->getOption('max_messages')
        ]);
    }
}
