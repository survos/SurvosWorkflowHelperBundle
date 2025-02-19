<?php

namespace Survos\WorkflowBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Survos\CoreBundle\Traits\QueryBuilderHelperInterface;
use Survos\WorkflowBundle\Event\RowEvent;
use Survos\WorkflowBundle\Message\AsyncTransitionMessage;
use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Yaml\Yaml;
use Zenstruck\Alias;
use Zenstruck\Console\Attribute\Argument;
use Zenstruck\Console\Attribute\Option;
use Zenstruck\Console\InvokableServiceCommand;
use Zenstruck\Console\IO;
use Zenstruck\Console\RunsCommands;
use Zenstruck\Console\RunsProcesses;
use Zenstruck\Metadata;
use Zenstruck\Metadata\Bridge\Doctrine\AliasManagerRegistry;

#[AsCommand('workflow:iterate', 'Iterative over an doctrine table, sending events"')]
final class IterateCommand extends InvokableServiceCommand
{
    use RunsCommands;
    use RunsProcesses;

    private bool $initialized = false; // so the event listener can be called from outside the command
    private ProgressBar $progressBar;

    public function __construct(
        private LoggerInterface           $logger,
        private ParameterBagInterface     $bag,
        private ?WorkflowHelperService    $workflowHelperService = null,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private ?MessageBusInterface      $bus = null,
        private ?EntityManagerInterface   $entityManager = null
    )
    {

        parent::__construct();
    }

    public function __invoke(
        IO                                                                                 $io,
        #[Argument(name: 'className', description: 'class name')] string                   $className,
        #[Autowire('%env(DEFAULT_TRANSPORT)%')] ?string                                    $defaultTransport = null,
        # to override the default
        #[Option(description: 'message transport')] ?string                                $transport = null,
        #[Option(description: 'workflow transition')] ?string                              $transition = null,
        #[Option(name: 'worflow', description: 'workflow (if multiple on class)')] ?string $workflowName = null,
        // marking CAN be null, which is why we should set it when inserting
        #[Option(description: 'workflow marking')] ?string                                 $marking = null,
        #[Option(description: 'tags (for listeners)')] ?string                             $tags = null,
        #[Option(name: 'index', description: 'grid:index after flush?')] ?bool             $indexAfterFlush = false,
        #[Option(description: 'show stats only')] ?bool             $stats = false,
        #[Option] int                                                                      $limit = 0,
        #[Option(description: "use this count for progressBar")] int                       $count = 0,
        #[Option] string                                                                   $dump = '',

    ): int
    {
        $transport ??= $defaultTransport;

        // inject entities that implement marking interface


        // do we need the Config?  Or is it all in the StorageBox
        $workflow = null;
        $helper = $this->getHelper('question');
        if (!class_exists($className)) {
            $metaData = Metadata::for($className); // ['track' => true, 'identifier' => 'getId'] (alternatively, fetch metadata by a class' alias)
            $className = Alias::classFor($className);
        }
        if (!$marking) {

        }

        /** @var QueryBuilderHelperInterface $repo */
        $repo = $this->entityManager->getRepository($className);
        if ($stats) {
            $counts = $repo->getCounts('marking');
            $table = new Table($io->output());
            $table->setHeaderTitle($className);
            $table->setHeaders(['marking','count','Available Transitions']);
            foreach ($counts as $name => $count) {
                $table->addRow([$name, $count, '@todo']);
            }
            $table->render();
            return self::SUCCESS;
        }

        if ($workflowName = $this->workflowHelperService->getWorkflowsGroupedByClass()[$className][0]) {
            $workflow = $this->workflowHelperService->getWorkflowByCode($workflowName);
            $places = $workflow->getDefinition()->getPlaces();
            if ($marking) {
                $places = array_values($workflow->getDefinition()->getPlaces());
                foreach (explode(',', $marking) as $m) {
                    // could also check if there's a transition that it's a valid "from"
                    assert(in_array($m, $places), "invalid marking:\n\n$m: valid markings are\n\n" . join("\n", $places));
                }
            } else {
                $question = new ChoiceQuestion(
                    'From which marking?',
                    // choices can also be PHP objects that implement __toString() method
                    $places,
                    0
                );

                $marking = $io->askQuestion($question);

            }
            $transitions = array_unique(array_map(fn(Transition $transition) => $transition->getName(), $workflow->getDefinition()->getTransitions()));
            if ($transition) {
                assert(in_array($transition, $transitions), "invalid transition:\n\n$transition: use\n\n" . join("\n", $transitions));
            } else {
                $question = new ChoiceQuestion(
                    'Transition?',
                    // choices can also be PHP objects that implement __toString() method
                    $transitions,
                    0
                );

                $transition = $io->askQuestion($question);

            }
        }

        $io->title($className);
        $where = [];
        if ($marking) {
            $where = ['marking' => explode(',', $marking)];
        }
        if (!$count) {
            $count = $repo->count(criteria: $where);
            if (!$count) {
                $this->io()->warning("No items found for " . json_encode($where));
                return self::SUCCESS;
            }
        }

        $progressBar = new ProgressBar($io, $count);
        $idx = 0;
        $stamps = [];
        if ($transport) {
            $stamps[] = new TransportNamesStamp($transport);
        }
        if ($dump) {
            $headers = explode(',', $dump);
            if (!in_array('key', $headers)) {
                $headers[] = 'key';
            }
            $table = new Table($io);
            $table->setHeaders($headers);
            $table->render();
        }

        $qb = $this->entityManager->getRepository($className)->createQueryBuilder('t');
        foreach ($where as $key => $value) {
            $qb->andWhere("t.$key = :{$key}")
                ->setParameter($key, $value);
        }
//        $iterator = $repo->findBy($where);
//        $qb->andWhere($where);

        $meta = $this->entityManager->getClassMetadata($className);
        $identifier = $meta->getSingleIdentifierFieldName();
        $iterator = $qb->getQuery()->toIterable();

        $this->eventDispatcher->dispatch(
            $rowEvent = new RowEvent(
                $className,
                type: RowEvent::PRE_ITERATE,
                action: self::class,
            ));

        foreach ($iterator as $idx => $item) {
            $method = 'get' . ucfirst($identifier);
            $key = $item->{$method}();
            if ($dump) {
                $values = array_map(fn($key) => substr($item->{$key}(), 0, 40), $headers);
                $table->addRow($values);
                $table->render();
            }

            // since we have the workflow and transition, we can do a "can" here.
            if ($workflow && $transition) {
                if (!$workflow->can($item, $transition)) {
                    $io->warning("$item cannot transition from {$item->getMarking()} to $transition");
                    continue;
                } else {
                    // if there's a workflow and a transition, dispatch a transition message, otherwise a simple row event
                    $envelope = $this->bus->dispatch($message = new AsyncTransitionMessage(
                        $item->{'get' . $identifier}(),
                        $className,
                        $transition,
                        $workflowName,
                    ), $stamps);
                }
            } else {
                // no workflow, so dispatch the row event and let the listeners handle it.
                $this->eventDispatcher->dispatch(
                    $rowEvent = new RowEvent(
                        $className,
                        $item,
                        $item->getId(),
                        $idx,
                        $count,
                        type: RowEvent::LOAD,
                        action: self::class,
                        context: [
//                                'storageBox' => $kv,
                            'tags' => $tags ? explode(",", $tags) : [],
                            'transition' => $transition,
                            'transport' => $transport
                        ])
                );
            }


            // if it's an event that changes the values, like a cleanup, we need to update the row.
            // if it's just dispatching an event, then we don't.
            // @todo: update
            if ($limit && $idx >= ($limit - 1)) {
                break;
            }
            $progressBar->advance();
        }
        $progressBar->finish();

        // final dispatch, to process
        $this->eventDispatcher->dispatch(
            $rowEvent = new RowEvent(
                $className,
                type: RowEvent::POST_LOAD,
                action: self::class,
                context: [
                    'tags' => $tags ? explode(",", $tags) : [],
                    'transition' => $transition,
                    'transport' => $transport
                ])
        );

        if ($indexAfterFlush) { // || $transport==='sync') { @todo: check for tags, e.g. create-owners
            $cli = "db:index $className  --reset"; // trans simply _gets_ existing translations
            $this->io()->warning('bin/console ' . $cli);
            $this->runCommand($cli);
        }

        $io->success($this->getName() . ' success ' . $className);
        return self::SUCCESS;
    }

}
