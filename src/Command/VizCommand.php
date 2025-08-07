<?php

namespace Survos\WorkflowBundle\Command;

use Roave\BetterReflection\BetterReflection;
use Survos\WorkflowBundle\Service\SurvosGraphVizDumper;
use Survos\WorkflowBundle\Service\SurvosGraphVizDumper3;
use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\Transition;
use Twig\Environment;
use Symfony\Component\EventDispatcher\Debug\WrappedListener;

#[AsCommand(name: 'survos:workflow:viz', description: 'Visualize a workflow')]
final class VizCommand extends Command
{
    private const DUMP_FORMAT_OPTIONS = ['puml', 'mermaid', 'dot'];

    private array $orderedEvents = [
        'guard',
        'leave',
        'transition',
        'enter',
        'entered',
        'completed',
        'announce',
    ];

    public function __construct(
        /** @var WorkflowInterface[] */
        private iterable               $workflows,
        #[Autowire('%kernel.project_dir%')]
        private string                $projectDir,
        private Environment           $twig,
        private WorkflowHelperService $workflowHelper,
        #[Autowire(service: 'event_dispatcher')]
        private readonly EventDispatcherInterface $dispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'A workflow name')
            ->addArgument('marking', InputArgument::IS_ARRAY, 'A marking (a list of places)')
            ->addOption('label', 'l', InputOption::VALUE_REQUIRED, 'Label a graph')
            ->addOption('with-metadata', null, InputOption::VALUE_NONE, 'Include metadata')
            ->addOption(
                'dump-format',
                null,
                InputOption::VALUE_REQUIRED,
                'The dump format [' . implode('|', self::DUMP_FORMAT_OPTIONS) . ']',
                'dot'
            )
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command dumps the graphical representation of a
workflow in different formats.

<info>DOT</info>:  %command.full_name% <workflow name> | F -Tpng > workflow.png
<info>PUML</info>: %command.full_name% <workflow name> --dump-format=puml | java -jar plantuml.jar -p > workflow.png
<info>MERMAID</info>: %command.full_name% <workflow name> --dump-format=mermaid | mmdc -o workflow.svg
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $allEvents = $this->getWorkflowListeners($output);
        if (!file_exists('doc/assets')) {
            mkdir('doc/assets', 0777, true);
        }

//        $eventFilename = 'doc/workflow-events.json';
//        if (!file_exists($eventFilename)) {
//            throw new \RuntimeException(
//                "$eventFilename not found; run:\n" .
//                "  bin/console debug:event --format=json workflow > $eventFilename"
//            );
//        }
//        $allEvents = json_decode(
//            file_get_contents($eventFilename),
//            false,
//            512,
//            JSON_THROW_ON_ERROR
//        );
//
        $collected = [];
        $seen      = [];

        foreach ($this->workflows as $workflow) {
            $fn = $this->dumpSvg($workflow);
            $output->writeln($fn);
            $wfName = $workflow->getName();

            if ($input->getArgument('name') && $input->getArgument('name') !== $wfName) {
                continue;
            }

            $definition = $workflow->getDefinition();
            $mdStore = $definition->getMetadataStore();

            foreach ($definition->getTransitions() as $transition) {
                $transMeta = $mdStore->getTransitionMetadata($transition);
                /** @var Transition $transition */
                $tn = $transition->getName();


                foreach ($this->orderedEvents as $action) {
                    $eventKey = sprintf('workflow.%s.%s.%s', $wfName, $action, $tn);
//                    $action=='transition' && dd($eventKey, array_keys($allEvents));
                    $event = $allEvents[$eventKey]??null;
                    if (empty($event)) {
                        continue;
                    }


                    foreach ($event as $e) {
                        $e = (object)$e;
                        if (!str_starts_with($e->class, 'App\\')) {
                            continue;
                        }

                        $handlerKey = sprintf(
                            '%s::%s::%s::%s::%s',
                            $wfName,
                            $tn,
                            $action,
                            $e->class,
                            $e->name
                        );
                        if (isset($seen[$handlerKey])) {
                            continue;
                        }
                        $seen[$handlerKey] = true;

                        $refMethod = new \ReflectionMethod($e->class, $e->name);
                        $br        = (new BetterReflection())->reflector()->reflectClass($e->class);
                        $method    = $br->getMethod($e->name);

                        $srcLines = explode(
                            "\n",
                            str_replace("\t", "    ", $br->getLocatedSource()->getSource())
                        );
                        $snippet   = array_slice(
                            $srcLines,
                            $method->getStartLine() - 1,
                            $method->getEndLine() - $method->getStartLine() + 1
                        );
                        $justified = $this->leftJustifyPhpCode($snippet);
                        $file      = $br->getFileName();
                        $lineLink  = sprintf(
                            '%s/blob/main/%s#L%d-L%d',
                            basename($this->projectDir),
                            substr($file, strlen($this->projectDir) + 1),
                            $refMethod->getStartLine(),
                            $refMethod->getEndLine()
                        );

                        $collected[$wfName][$tn][$action][] = [
                            'file'   => $file,
                            'link'   => $lineLink,
                            'source' => $justified,
                            'method' => $e->name,
                            'metadata' => $transMeta, // redundant
                        ];
                    }
                }
            }
        }

        foreach ($collected as $wf => $transitions) {
            $md = $this->twig->render('@SurvosWorkflow/md/workflows.html.twig', [
                'workflowName'       => $wf,
                'eventsByTransition' => $transitions,
            ]);
            $outFile = sprintf('doc/%s.md', $wf);
            file_put_contents($outFile, $md);
            $output->writeln(sprintf('<info>Wrote</info> %s', $outFile));
        }

        return self::SUCCESS;
    }

    private function leftJustifyPhpCode(array $lines): string
    {
        $minIndent = null;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            preg_match('/^[ \t]*/', $line, $m);
            $len = strlen($m[0]);
            if ($minIndent === null || $len < $minIndent) {
                $minIndent = $len;
            }
        }

        return implode("\n", array_map(
            fn(string $line) => preg_replace('/^[ \t]{0,' . $minIndent . '}/', '', $line),
            $lines
        ));
    }


    private function dumpSvg(WorkflowInterface $workflow)
    {
        $dumper = new SurvosGraphVizDumper();
        $marking = new Marking();
        $type = $workflow instanceof StateMachine ? 'state_machine' : 'workflow';
        $definition = $workflow->getDefinition();

        $options = [
            'name' => $workflow->getName(),
            'with-metadata' => true, // $input->getOption('with-metadata'),
            'nofooter' => true,
            'label' => $workflow->getName()
        ];
        $dot = $dumper->dump($definition, $marking, $options);
        //

        file_put_contents($fn = sprintf('doc/assets/%s.dot', $workflow->getName()), $dot);
        try {
            $process = new Process(['dot', '-Tsvg']);
            $process->setInput($dot);
            $process->mustRun();

            $svg = $process->getOutput();
            file_put_contents($fn = sprintf('doc/assets/%s.svg', $workflow->getName()), $svg);
        } catch (\Exception $e) {
            dd($e->getMessage(), $dot);
        }
        return $fn;
//        dd($svg, $dot, $fn);

    }

    private function getWorkflowListeners(): array
    {
        $listeners = $this->dispatcher->getListeners();

        $workflowListeners = array_filter(
            $listeners,
            fn($key) => str_starts_with($key, 'workflow.'),
            ARRAY_FILTER_USE_KEY
        );

        $result = [];

        foreach ($workflowListeners as $eventName => $listenerList) {
            foreach ($listenerList as $listener) {
                if (is_array($listener)) {
                    [$objectOrClass, $method] = $listener;
                    $class = is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass;
                    $result[$eventName][] = [
                        'class' => $class,
                        'name' => $method
                    ];
                }
            }
        }

        return $result;
    }


    private function describeListener(callable $listener): string
    {
        if (is_array($listener)) {
            [$classOrObject, $method] = $listener;
            $className = is_object($classOrObject) ? get_class($classOrObject) : (string)$classOrObject;
            return sprintf('%s::%s', $className, $method);
        }

        if ($listener instanceof \Closure) {
            $ref = new \ReflectionFunction($listener);
            return sprintf('Closure at %s:%d', $ref->getFileName(), $ref->getStartLine());
        }

        if (is_object($listener)) {
            return get_class($listener);
        }

        return (string)$listener;
    }
}
