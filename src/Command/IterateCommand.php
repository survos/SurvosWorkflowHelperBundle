<?php

namespace Survos\WorkflowBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Survos\CoreBundle\Traits\QueryBuilderHelperInterface;
use Survos\WorkflowBundle\Event\RowEvent;
use Survos\WorkflowBundle\Message\TransitionMessage;
use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Yaml\Yaml;
use Zenstruck\Alias;
use Zenstruck\Messenger\Monitor\Stamp\DescriptionStamp;
use Zenstruck\Messenger\Monitor\Stamp\TagStamp;
use Zenstruck\Metadata;

use function Symfony\Component\String\u;

#[AsCommand('workflow:iterate', 'Iterative over an doctrine table, sending events, Symfony 7.3"', aliases: ['iterate'])]
final class IterateCommand extends Command // extends is for 7.2/7.3 compatibility
{
    private bool $initialized = false; // so the event listener can be called from outside the command
    private ProgressBar $progressBar;

    public function __construct(
        private LoggerInterface $logger,
        private ParameterBagInterface $bag,
        private ?WorkflowHelperService $workflowHelperService = null,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private ?MessageBusInterface $bus = null,
        private ?EntityManagerInterface $entityManager = null,
        private ?ManagerRegistry $doctrine=null,
        #[Autowire('%env(DEFAULT_TRANSPORT)%')] private ?string $defaultTransport = null,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle                                                                                $io,
        #[Argument('class name')] ?string                  $className = null,
        # to override the default

        // these used to be nullable!!
        #[Option('message transport', shortcut: 'tr')] ?string $transport = null,
        #[Option('workflow transition', shortcut: 't')] ?string $transition = null,
        #[Option('workflow marking', shortcut: 'm')] ?string $marking = null,
        #[Option(name: 'workflow', description: 'workflow (if multiple on class)')] ?string $workflowName = null,
        // marking CAN be null, which is why we should set it when inserting
        #[Option('tags (for listeners)')] string $tags = '',
        #[Option] string $dump = '',

        #[Option(name: 'index', description: 'grid:index after flush?')] ?bool $indexAfterFlush = null,
        #[Option('show stats only')] ?bool $stats = null,
        #[Option] int $limit = 0,
        #[Option("use this count for progressBar")] int $count = 0,
    ): int {

        // inject entities that implement marking interface

        $workflow = null;
        $doctrineEntitiesFqcn = $this->getAllDoctrineEntitiesFqcn();

        if (0 === \count($doctrineEntitiesFqcn)) {
            $io->error('This command iterates over an existing Doctrine entity, but no entities were found in your application. Create some Doctrine entities that implement MarkingInterface first and then run this command again.');

            return Command::FAILURE;
        }

        if (!$className) {
            if (!$className = $doctrineEntitiesFqcn[$className] ?? null) {
                $className = $io->choice(
                    'Which Doctrine entity are you going to iterate?',
                    $doctrineEntitiesFqcn
                );
                $className = $doctrineEntitiesFqcn[$className] ?? $className;
            }
        }

        $helper = $this->getHelper('question');
        if (!class_exists($className) && class_exists($entityClass = 'App\\Entity\\' . $className)) {
            $className = $entityClass;
        }
        if (!class_exists($className) && class_exists(Metadata::class)) {
            $metaData = Metadata::for($className); // ['track' => true, 'identifier' => 'getId'] (alternatively, fetch metadata by a class' alias)
            $className = Alias::classFor($className);
        }


        /** @var QueryBuilderHelperInterface $repo */
        $repo = $this->entityManager->getRepository($className);
//        dd($this->workflowHelperService->getWorkflowsGroupedByClass());

        if ($workflowName = $this->workflowHelperService->getWorkflowsGroupedByClass()[$className][0]) {
            $workflow = $this->workflowHelperService->getWorkflowByCode($workflowName);
            $places = $workflow->getDefinition()->getPlaces();

            $availableTransitions=[];
            foreach ($workflow->getDefinition()->getTransitions() as $t) {
                foreach ($t->getFroms() as $from) {
                    $availableTransitions[$from][] = $t;
                }
            }
//        $transitions = array_filter(array_unique(array_map(fn(Transition $transition) =>
//        in_array($marking, $transition->getFroms()) ? $transition->getName() : null,
//            )));
            if ($stats) {
                $this->showStats($repo, $io, $className, $availableTransitions, $workflow);
                return Command::SUCCESS;
            }



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
                    null
                );

                $marking = $io->askQuestion($question);
            }

            $transitions = [];
            foreach ($workflow->getDefinition()->getTransitions() as $t) {
                if (in_array($marking, $t->getFroms())) {
                    $help = $workflow->getMetadataStore()->getMetadata('description', $t)??$t->getName();
                    if ($guardExpression = $workflow->getMetadataStore()->getMetadata('guard', $t)) {
                        $help .= " (if: " . $guardExpression . ")";
                    }
                    $transitions[$t->getName()] = $help;
                }
            }
//            dd($transitions);
//            $transitions = array_filter(array_unique(array_map(fn(Transition $transition) =>
//                in_array($marking, $transition->getFroms()) ? $transition->getName() : null,
//                $workflow->getDefinition()->getTransitions())));

            if ($transition) {
                assert(array_key_exists($transition, $transitions), "invalid transition:\n\n$transition: use\n\n" . join("\n", $transitions));
            } else {
                $question = new ChoiceQuestion(
                    'Transition?',
                    // choices can also be PHP objects that implement __toString() method
                    $transitions,
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
                $io->warning("No items found for " . json_encode($where));
                return self::SUCCESS;
            }
        }

        $progressBar = new ProgressBar($io, $count);
        $idx = 0;
        $meta = $this->workflowHelperService->getTransitionMetadata($transition, $workflow);
        $transport ??= $meta['transport']??$this->defaultTransport;
        if ($transport) {
            $stamps[] = new TransportNamesStamp($transport);
        }
        $shortClass = new \ReflectionClass($className)->getShortName();
        if (class_exists(TagStamp::class)) {
            $stamps[] = new TagStamp($transition);
        }
//        new TagStamp('tag-1')
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
            )
        );

        foreach ($iterator as $idx => $item) {
            // @todo: hande property hooks, etc.
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
                    foreach ($workflow->buildTransitionBlockerList($item, $transition) as $blocker) {
                        if ($io->isVerbose()) {
                            $io->warning($blocker->getMessage());
                            foreach ($blocker->getParameters() as $param) {
                                $io->warning($param);
                            }
                        }
                    }
                    continue;
                } else {
                    $messageStamps = $stamps;
                    if (class_exists(DescriptionStamp::class)) {
                        $messageStamps[] = new DescriptionStamp("$shortClass:$key $marking->$transition");
                    }
                    // if there's a workflow and a transition, dispatch a transition message, otherwise a simple row event
                    // description is message-specific
                    $envelope = $this->bus->dispatch($message = new TransitionMessage(
                        $key,
                        $className,
                        $transition,
                        $workflowName,
                    ), $messageStamps);
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
                        ]
                    )
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
                ]
            )
        );

        if ($indexAfterFlush) { // || $transport==='sync') { @todo: check for tags, e.g. create-owners
            $cli = "db:index $className  --reset"; // trans simply _gets_ existing translations
            $this->io()->warning('bin/console ' . $cli);
            $this->runCommand($cli);
        }

        $this->showStats($repo, $io, $className, $availableTransitions, $workflow);

        $io->success($this->getName() . ' success ' . $className);
        return self::SUCCESS;
    }

    private function getAllDoctrineEntitiesFqcn(): array
    {
        $entitiesFqcn = [];
        foreach ($this->doctrine->getManagers() as $entityManager) {
            $classesMetadata = $entityManager->getMetadataFactory()->getAllMetadata();
            foreach ($classesMetadata as $classMetadata) {
                $entitiesFqcn[lcfirst($classMetadata->getReflectionClass()->getShortName())] = $classMetadata->getName();
            }
        }

//        sort($entitiesFqcn);

        return $entitiesFqcn;
    }

    /**
     * @param QueryBuilderHelperInterface $repo
     * @param SymfonyStyle $io
     * @param mixed $className
     * @param mixed $count
     * @param array $availableTransitions
     * @return int
     */
    public function showStats(QueryBuilderHelperInterface $repo,
                              SymfonyStyle $io, mixed $className,
                              array $availableTransitions,
    WorkflowInterface $workflow
    ): void
    {

        $counts = $repo->getCounts('marking');
        $table = new Table($io);
        $table->setHeaderTitle($className);
        $table->setHeaders(['marking', 'description', 'count', 'Available Transitions']);
        $meta = $workflow->getMetadataStore();
        foreach ($counts as $name => $count) {
            $markingHelp = $meta->getMetadata('description', $name)??null;
            $x = [];
            foreach ($availableTransitions[$name]??[] as $t) {
                $description = $meta->getMetadata('description', $t);
                $x[] = sprintf("(%s) %s", $t->getName(), $description);
            }
            $table->addRow([$name, $markingHelp, $count, join("\n", $x ?? [])]);
        }
        $table->render();
    }
}
