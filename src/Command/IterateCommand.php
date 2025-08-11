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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;
use Zenstruck\Alias;
use Zenstruck\Messenger\Monitor\Stamp\DescriptionStamp;
use Zenstruck\Messenger\Monitor\Stamp\TagStamp;

#[AsCommand('workflow:iterate', 'Iterate a Doctrine entity and dispatch workflow transitions.', aliases: ['iterate'])]
final class IterateCommand extends Command
{
    public function __construct(
        private LoggerInterface $logger,
        private WorkflowHelperService $workflowHelperService,
        private EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $bus,
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $doctrine,
        private PropertyAccessorInterface $propertyAccessor,
        #[Autowire('%env(DEFAULT_TRANSPORT)%')] private ?string $defaultTransport = null,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,

        // ARGUMENTS — description only (name inferred from parameter)
        #[Argument('FQCN or short name of the Doctrine entity')] ?string $className = null,

        // OPTIONS — description first; name inferred from parameter; explicit shortcut
        #[Option('Messenger transport name', shortcut: 'p')] ?string $transport = null,
        #[Option('Workflow transition name', shortcut: 't')] ?string $transition = null,
        #[Option('Comma-separated marking(s) to filter', shortcut: 'm')] ?string $marking = null,
        #[Option('Workflow name/code if multiple on class', shortcut: 'w')] ?string $workflowName = null,
        #[Option('Comma-separated tags for listeners', shortcut: 'g')] string $tags = '',
        #[Option('Comma-separated property paths to dump for each row', shortcut: 'd')] string $dump = '',
        #[Option('grid:index after flush?')] ?bool $indexAfterFlush = null,
        #[Option('Show counts per marking and exit', shortcut: 's')] ?bool $stats = null,
        #[Option('Process at most this many items', shortcut: 'x')] int $max = 0,
        #[Option('[deprecated] Use --max instead')] int $limit = 0,
        #[Option('Use this count for progress bar', shortcut: 'c')] int $count = 0,
    ): int {
        // --limit shim
        if ($limit) {
            $io->warning('--limit is deprecated; use --max.');
            $max = $limit;
        }

        // Resolve/select entity class
        $doctrineEntitiesFqcn = $this->getAllDoctrineEntitiesFqcn();
        if (!$doctrineEntitiesFqcn) {
            $io->error('No Doctrine entities found. Create some first, then run again.');
            return Command::FAILURE;
        }

        if (!$className) {
            $className = $io->choice('Which Doctrine entity are you going to iterate?', array_values($doctrineEntitiesFqcn));
        } else {
            if (isset($doctrineEntitiesFqcn[$className])) {
                $className = $doctrineEntitiesFqcn[$className];
            }
            if (!class_exists($className) && class_exists('App\\Entity\\' . $className)) {
                $className = 'App\\Entity\\' . $className;
            }
            if (!class_exists($className) && class_exists(Alias::class)) {
                $className = Alias::classFor($className);
            }
        }

        if (!class_exists($className)) {
            $io->error("Entity class not found: {$className}");
            return Command::FAILURE;
        }

        /** @var QueryBuilderHelperInterface $repo */
        $repo = $this->entityManager->getRepository($className);

        // Determine workflow (if any) for this class
        $workflow = null;
        $availableTransitions = [];

        $grouped = $this->workflowHelperService->getWorkflowsGroupedByClass();
        if (isset($grouped[$className][0])) {
            $workflowName ??= $grouped[$className][0];
            $workflow = $this->workflowHelperService->getWorkflowByCode($workflowName);

            // Build from->transitions map and list of places
            $places = array_values($workflow->getDefinition()->getPlaces());
            foreach ($workflow->getDefinition()->getTransitions() as $t) {
                foreach ($t->getFroms() as $from) {
                    $availableTransitions[$from][] = $t;
                }
            }

            if ($stats) {
                $this->showStats($io, $className, $availableTransitions, $workflow);
                return Command::SUCCESS;
            }

            // Pick marking(s)
            if ($marking) {
                $selected = array_values(array_filter(array_map('trim', explode(',', $marking))));
                foreach ($selected as $m) {
                    if (!in_array($m, $places, true)) {
                        $io->error("Invalid marking: {$m}\nValid markings are:\n - " . implode("\n - ", $places));
                        return Command::FAILURE;
                    }
                }
            } else {
                $question = new ChoiceQuestion('From which marking?', $places);
                $marking = $io->askQuestion($question);
            }

            // Pick transition (if not provided)
            $transitions = [];
            foreach ($workflow->getDefinition()->getTransitions() as $t) {
                if (in_array($marking, $t->getFroms(), true)) {
                    $help = $this->wfTransitionDescription($workflow, $t) ?? $t->getName();
                    if ($guard = $this->wfTransitionGuard($workflow, $t)) {
                        $help .= " (if: {$guard})";
                    }
                    $transitions[$t->getName()] = $help;
                }
            }

            if ($transition) {
                if (!array_key_exists($transition, $transitions)) {
                    $io->error("Invalid transition: {$transition}\nValid from '{$marking}':\n - " . implode("\n - ", array_keys($transitions)));
                    return Command::FAILURE;
                }
            } else {
                $question = new ChoiceQuestion('Transition?', array_keys($transitions));
                $transition = $io->askQuestion($question);
            }
        }

        $io->title($className);

        // Build where (supports IN() for multiple markings)
        $where = [];
        if ($marking) {
            $where['marking'] = array_values(array_filter(array_map('trim', explode(',', $marking))));
        }

        // Determine total count
        if (!$count) {
            $count = $repo->count($where);
            if (!$count) {
                $io->warning('No items found for filter: ' . json_encode($where));
                return Command::SUCCESS;
            }
        }

        $progressBar = new ProgressBar($io, $count);

        // Prepare stamps
        $stamps = [];
        $shortClass = (new \ReflectionClass($className))->getShortName();

        if ($workflow && $transition) {
            $wfMeta = $this->workflowHelperService->getTransitionMetadata($transition, $workflow);
            $transport ??= $wfMeta['transport'] ?? $this->defaultTransport;
        }
        if ($transport) {
            $stamps[] = new TransportNamesStamp([$transport]);
        }
        if (class_exists(TagStamp::class)) {
            $stamps[] = new TagStamp($transition ?? 'iterate');
        }

        // Optional dump table
        $table = null;
        $headers = [];
        if ($dump) {
            $headers = array_values(array_unique(array_filter(array_map('trim', explode(',', $dump)))));
            if (!in_array('key', $headers, true)) {
                $headers[] = 'key';
            }
            $table = new Table($io);
            $table->setHeaders($headers);
        }

        // Build query
        $qb = $this->entityManager->getRepository($className)->createQueryBuilder('t');
        foreach ($where as $key => $value) {
            if (is_array($value)) {
                $qb->andWhere("t.$key IN (:{$key})");
            } else {
                $qb->andWhere("t.$key = :{$key}");
            }
            $qb->setParameter($key, $value);
        }

        // Identifier handling (single id only)
        $classMeta = $this->entityManager->getClassMetadata($className);
        $idFields = $classMeta->getIdentifierFieldNames();
        if (count($idFields) !== 1) {
            $io->error('Composite identifiers are not supported by this command.');
            return Command::FAILURE;
        }
        $identifier = $idFields[0];

        // PRE event
        $this->eventDispatcher->dispatch(new RowEvent(
            $className,
            type: RowEvent::PRE_ITERATE,
            action: self::class,
        ));

        // Iterate
        $processed = 0;
        foreach ($qb->getQuery()->toIterable() as $item) {
            $key = $this->propertyAccessor->getValue($item, $identifier);

            if ($table) {
                $row = [];
                foreach ($headers as $h) {
                    $value = $h === 'key'
                        ? $key
                        : $this->propertyAccessor->getValue($item, $h);
                    $row[] = substr((string)($value ?? ''), 0, 120);
                }
                $table->addRow($row);
            }

            if ($workflow && $transition) {
                if (!$workflow->can($item, $transition)) {
                    foreach ($workflow->buildTransitionBlockerList($item, $transition) as $blocker) {
                        $io->warning($blocker->getMessage());
                    }
                    $progressBar->advance();
                    $processed++;
                    if ($max && $processed >= $max) {
                        break;
                    }
                    continue;
                }

                $messageStamps = $stamps;
                if (class_exists(DescriptionStamp::class)) {
                    $messageStamps[] = new DescriptionStamp("{$shortClass}:{$key} {$marking}->{$transition}");
                }

                $this->bus->dispatch(
                    new TransitionMessage($key, $className, $transition, $workflowName),
                    $messageStamps
                );
            } else {
                // No workflow: emit a row event and let listeners handle it
                $this->eventDispatcher->dispatch(new RowEvent(
                    $className,
                    $item,
                    $key,
                    $processed,
                    $count,
                    type: RowEvent::LOAD,
                    action: self::class,
                    context: [
                        'tags' => $tags ? explode(',', $tags) : [],
                        'transition' => $transition,
                        'transport' => $transport,
                    ]
                ));
            }

            $processed++;
            if ($max && $processed >= $max) {
                break;
            }

            // Optional: free memory on big runs (beware: detaches entities)
            if (($processed % 200) === 0) {
                $this->entityManager->clear();
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        if ($table) {
            $table->render();
        }

        // POST event
        $this->eventDispatcher->dispatch(new RowEvent(
            $className,
            type: RowEvent::POST_LOAD,
            action: self::class,
            context: [
                'tags' => $tags ? explode(',', $tags) : [],
                'transition' => $transition,
                'transport' => $transport,
            ]
        ));

        // Optional stats if a workflow was in play
        if ($workflow) {
            $this->showStats($io, $className, $availableTransitions, $workflow);
        }

        $io->success($this->getName() . ' success ' . $className);
        return Command::SUCCESS;
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
        return $entitiesFqcn;
    }

    private function wfTransitionDescription(WorkflowInterface $workflow, Transition $t): ?string
    {
        $store = $workflow->getMetadataStore();
        if (method_exists($store, 'getTransitionMetadata')) {
            $meta = $store->getTransitionMetadata($t);
            return $meta['description'] ?? null;
        }
        return $store->getMetadata('description', $t) ?? null;
    }

    private function wfTransitionGuard(WorkflowInterface $workflow, Transition $t): ?string
    {
        $store = $workflow->getMetadataStore();
        if (method_exists($store, 'getTransitionMetadata')) {
            $meta = $store->getTransitionMetadata($t);
            return $meta['guard'] ?? null;
        }
        return $store->getMetadata('guard', $t) ?? null;
    }

    public function showStats(
        SymfonyStyle $io,
        string $className,
        array $availableTransitions,
        WorkflowInterface $workflow
    ): void {
        $counts = $this->workflowHelperService->getCounts($className, 'marking');
        $table = new Table($io);
        $table->setHeaderTitle($className);
        $table->setHeaders(['marking', 'description', 'count', 'Available Transitions']);

        $store = $workflow->getMetadataStore();

        foreach ($counts as $name => $count) {
            // Place description
            $markingHelp = null;
            if (method_exists($store, 'getPlaceMetadata')) {
                $pm = $store->getPlaceMetadata($name);
                $markingHelp = $pm['description'] ?? null;
            } else {
                $markingHelp = $store->getMetadata('description', $name) ?? null;
            }

            $lines = [];
            foreach ($availableTransitions[$name] ?? [] as $t) {
                $desc = $this->wfTransitionDescription($workflow, $t);
                $lines[] = sprintf('(%s) %s', $t->getName(), $desc ?? '');
            }

            $table->addRow([$name, $markingHelp, $count, implode("\n", $lines)]);
        }

        $table->render();
    }
}
