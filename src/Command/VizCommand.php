<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Survos\WorkflowBundle\Command;

use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\Printer;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionMethod;
use Survos\WorkflowBundle\Service\SurvosGraphVizDumper;
use Survos\WorkflowBundle\Service\SurvosStateMachineGraphVizDumper;
use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Process\Process;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Dumper\MermaidDumper;
use Symfony\Component\Workflow\Dumper\PlantUmlDumper;
use Symfony\Component\Workflow\Dumper\StateMachineGraphvizDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\WorkflowInterface;
use Twig\Environment;
use function Symfony\Component\String\s;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @final
 */
#[AsCommand(name: 'survos:workflow:viz', description: 'Vizualize a workflow')]
class VizCommand extends Command
{
    private const DUMP_FORMAT_OPTIONS = [
        'puml',
        'mermaid',
        'dot',
    ];

    public function __construct(
        /** @var WorkflowInterface[] */
        private iterable                                    $workflows,
        #[Autowire('%kernel.project_dir%')] private ?string $projectDir,
        private Environment                                 $twig,
        private WorkflowHelperService                       $workflowHelper,
//        private ServiceLocator $workflows,
//        #[AutowireIterator('%kernel.event_listener%')] private readonly iterable $messageHandlers

    )
    {
        parent::__construct();
    }


    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, 'A workflow name'),
                new InputArgument('marking', InputArgument::IS_ARRAY, 'A marking (a list of places)'),
                new InputOption('label', 'l', InputOption::VALUE_REQUIRED, 'Label a graph'),
                new InputOption('with-metadata', null, InputOption::VALUE_NONE, 'Include the workflow\'s metadata in the dumped graph', null),
                new InputOption('dump-format', null, InputOption::VALUE_REQUIRED, 'The dump format [' . implode('|', self::DUMP_FORMAT_OPTIONS) . ']', 'dot'),
            ])
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command dumps the graphical representation of a
workflow in different formats

<info>DOT</info>:  %command.full_name% <workflow name> | dot -Tpng > workflow.png
<info>PUML</info>: %command.full_name% <workflow name> --dump-format=puml | java -jar plantuml.jar -p > workflow.png
<info>MERMAID</info>: %command.full_name% <workflow name> --dump-format=mermaid | mmdc -o workflow.svg
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $orderedEvents = ['guard','leave','transition','enter','entered','completed','announce'];

        $eventFilename = 'doc/workflow-events.json';
        assert(file_exists($eventFilename), "$eventFilename does not exist, run bin/console debug:event --format=json workflow > " . $eventFilename);
        $allEvents = json_decode(file_get_contents($eventFilename), false, 512, JSON_THROW_ON_ERROR);
        $ee = [];
        foreach ($this->workflows as $workflow) {
            $this->dumpSvg($workflow);
//            $this->workflowHelper->workflowDiagram();
        }

        // order the events
        foreach ($orderedEvents as $eventName) {

            foreach ($allEvents as $code => $events) {
                if (!str_starts_with($code, 'workflow.')) {
                    continue;
                }

                $parts = explode('.', str_replace('workflow.', '', $code));
                $workflowName = array_shift($parts);
                $action = array_shift($parts);
                $transition = array_shift($parts);
                if ($action <> $eventName) {
                    continue;
                }
//                dd($code, $eventName, $action, $workflowName, $transition);
//            dd(wf: $workflowName, action: $action, transition: $transition);//, $parts, $code);

                foreach ($events as $e) {
                    $reflectionMethod = new \ReflectionMethod($e->class, $e->name);
                    // hack to only get App Events, not the Symfony Events (in vendor)
                    if (!str_starts_with($e->class, 'App')) {
                        continue;
                    }
//                assert(!is_string($e), $e);
                    $classInfo = (new BetterReflection())
                        ->reflector()
                        ->reflectClass($e->class);
                    $method = $classInfo->getMethod($e->name);
//                $source = $method->getLocatedSource()->getSource();
                    $rawSource = $classInfo->getLocatedSource()->getSource();
                    $rawSource = str_replace("\t", "    ", $rawSource);
                    $source = explode("\n", $rawSource);


                    $lines = array_slice($source, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);
                    $justifiedCode = $this->leftJustifyPhpCode($lines);

//                dd($e->class, $source, $classInfo, $method, $reflectionMethod);
//                $m = ReflectionMethod::createFromName(MediaWorkflow::class, $e->name);
//                $m = ReflectionMethod::createFromName($e->class, $e->name);
                    $reflectionClass = new \ReflectionClass($e->class);
                    $fn = str_replace($this->projectDir, 'blob/main', $reflectionClass->getFileName());
                    $ee[$workflowName][$action][$transition][] = [
                        'file' => $classInfo->getFileName(),
//                    'lines' => $e->extra['line'],
                        'link' => sprintf('%s#L%d-%d', $fn, $reflectionMethod->getStartLine(), $reflectionMethod->getEndLine()),
                        'source' => $justifiedCode
                    ];
                }
            }
        }

        foreach ($ee as $wf => $events) {
            $md = $this->twig->render('@SurvosWorkflow/md/workflows.html.twig', [
                'events' => $events,
                'workflowName' => $wf,
            ]);
            file_put_contents($fn = sprintf('doc/%s.md', $wf), $md);
            $output->writeln(sprintf('<info>%s</info>', $fn));
        }

        return self::SUCCESS;

        dd($events);

//        foreach ($this->messageHandlers as $messageHandler) {
//            dd($messageHandler);
//        }

        $workflowName = $input->getArgument('name');
        foreach ($this->workflows as $workflow) {
            if ($workflowName && ($w->getName() !== $workflowName)) {
                continue;
            }
            $type = $workflow instanceof StateMachine ? 'state_machine' : 'workflow';
            $definition = $workflow->getDefinition();

            switch ($input->getOption('dump-format')) {
                case 'puml':
                    $transitionType = 'workflow' === $type ? PlantUmlDumper::WORKFLOW_TRANSITION : PlantUmlDumper::STATEMACHINE_TRANSITION;
                    $dumper = new PlantUmlDumper($transitionType);
                    break;

                case 'mermaid':
                    $transitionType = 'workflow' === $type ? MermaidDumper::TRANSITION_TYPE_WORKFLOW : MermaidDumper::TRANSITION_TYPE_STATEMACHINE;
                    $dumper = new MermaidDumper($transitionType);
                    break;

                case 'dot':
                default:
                    $dumper = new SurvosGraphVizDumper();
            }

            $marking = new Marking();

            foreach ($input->getArgument('marking') as $place) {
                $marking->mark($place);
            }

            $options = [
                'name' => $workflowName,
                'with-metadata' => $input->getOption('with-metadata'),
                'nofooter' => true,
                'label' => $input->getOption('label'),
            ];
            $output->writeln($dumper->dump($definition, $marking, $options));

            // now run dot and create the svg
        }

        return 0;
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

        file_put_contents($fn = sprintf('doc/%s.dot', $workflow->getName()), $dot);
        try {
            $process = new Process(['dot', '-Tsvg']);
            $process->setInput($dot);
            $process->mustRun();

            $svg = $process->getOutput();
            file_put_contents($fn = sprintf('doc/%s.svg', $workflow->getName()), $svg);
        } catch (\Exception $e) {
            dd($e->getMessage(), $dot);
        }
//        dd($svg, $dot, $fn);

    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            $suggestions->suggestValues(array_keys($this->workflows->getProvidedServices()));
        }

        if ($input->mustSuggestOptionValuesFor('dump-format')) {
            $suggestions->suggestValues(self::DUMP_FORMAT_OPTIONS);
        }
    }


    function leftJustifyPhpCode(array $lines): string
    {
        $minIndent = null;

        // Step 1: Find minimum indentation (spaces or tabs) among non-empty lines
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            if (preg_match('/^[ \t]*/', $line, $matches)) {
                $indentLength = strlen($matches[0]);
                if ($minIndent === null || $indentLength < $minIndent) {
                    $minIndent = $indentLength;
                }
            }
        }

        // Step 2: Remove the minimum common indentation from all lines
        $justified = array_map(function ($line) use ($minIndent) {
            return preg_replace('/^[ \t]{0,' . $minIndent . '}/', '', $line);
        }, $lines);

        return implode("\n", $justified);
    }

}
