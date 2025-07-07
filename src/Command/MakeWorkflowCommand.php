<?php

namespace Survos\WorkflowBundle\Command;

use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Type;
use ReflectionClass;
use Survos\WorkflowBundle\Attribute\Place;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;
use Twig\Environment;
use Zenstruck\Console\Attribute\Argument;
use Zenstruck\Console\Attribute\Option;
use Zenstruck\Console\InvokableServiceCommand;
use Zenstruck\Console\IO;
use Zenstruck\Console\RunsCommands;
use Zenstruck\Console\RunsProcesses;
use Survos\WorkflowBundle\Attribute\Transition;
use Survos\WorkflowBundle\Attribute\Workflow;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use function Symfony\Component\String\u;

#[AsCommand('survos:workflow:generate', 'IS THIS RIGHT?  Generate src/Workflow/(class)Workflow and Interface using nette/php-generator')]
#[WhenNot('prod')]
final class MakeWorkflowCommand extends Command
{

    public function __construct(
        #[Autowire('%kernel.project_dir%/src/Workflow')] private string $dir,
        private Environment                                             $twig
    )
    {
        parent::__construct();
    }


    public function __invoke(
        SymfonyStyle                                                                                             $io,
        #[Argument(name: 'class-name', description: 'entity class name')] string                       $entityClassName,
        #[Argument(name: 'place-names', description: 'place names, e.g. new,loaded,processed')] string $placeNames = 'new,loaded',
        #[Argument(name: 'transition-names', description: 'transitions, e.g. load,process')] string    $transitionNames = 'load',
        #[Option(description: 'namespace')] string                                                     $ns = "App\\Workflow"
    )
    {
        // @todo: check that class must implement MarkingInterface
        try {
            $shortName = (new ReflectionClass($entityClassName))->getShortName();
        } catch (\ReflectionException $e) {
            $io->error($entityClassName . "\n" . $e->getMessage());
            return self::FAILURE;
        }

        if (!class_exists(PhpNamespace::class)) {
            $io->error("Missing dependency:\n\ncomposer req nette/php-generator");
            return self::FAILURE;
        }


        $namespace = new PhpNamespace($ns);
        $workflowDir = $this->dir;
        if (!file_exists($workflowDir)) {
            mkdir($workflowDir, 0777, true);
        }
        $workflowClass = $shortName . "Workflow";
        foreach ([Place::class, Transition::class] as $use) {
            $namespace->addUse($use);
        }

        $interfaceClass = 'I' . $shortName . "Workflow";
        $class = $namespace->addInterface($interfaceClass);
        $namespace->add($class);
        $class->addConstant('WORKFLOW_NAME', $workflowClass);
        $placeConstants = [];
        foreach ($places = explode(',', $placeNames) as $idx => $place) {
            $pUp = strtoupper($place);
            $constant = $class->addConstant('PLACE_' . $pUp, $place);
            $constant->addAttribute(Place::class, $idx == 0 ? ['initial' => true] : []);
//            $placeConstants[] = 'self::' . $constant->getName();
            $placeConstants[] = new Literal('self::' . $constant->getName());
        }
        foreach ($transitions = explode(',', $transitionNames) as $idx => $t) {
            $from = [$placeConstants[$idx]];
            $to = $placeConstants[$idx + 1] ?? $placeConstants[0];
            $constant = $class->addConstant('TRANSITION_' . strtoupper($t), $t);
            $constant->addAttribute(Transition::class, ['from' => $from, 'to' => $to]);
            $transitionConstants[] = 'self::' . $constant->getName();
        }
        // hack, see https://github.com/nette/php-generator/issues/173
        $this->writeFile($namespace, $interfaceClass);

        $fullInterfaceClass = $ns . "\\" . $interfaceClass;
        if (!interface_exists($fullInterfaceClass)) {
            $io->error("reload so that  " . $fullInterfaceClass . " can be used. :-(");
            return self::SUCCESS;
        }

        // now the workflow events
        $namespace = new PhpNamespace($ns);
        foreach (
            [
                $fullInterfaceClass,
                $entityClassName,
//            $ns . "\\" . $interfaceClass, //  because they're in the same namespace, this isn't required
                Workflow::class,
                AsGuardListener::class,
                AsTransitionListener::class,
                GuardEvent::class,
                TransitionEvent::class,
            ] as $use
        ) {
            $namespace->addUse($use);
        }
//        dd($namespace->getUses(), $fullInterfaceClass);

        // This name is used for injecting the workflow into a service
// #[Target($class_name::WORKFLOW_NAME)] private WorkflowInterface $workflow
        /*<!--        const WORKFLOW_NAME = '--><?php //= $class_name ?><!--';-->*/


// create new classes in the namespace
        $class = $namespace->addClass($workflowClass);
        $class->addImplement($fullInterfaceClass);
        $class->addAttribute(Workflow::class, [
            'supports' => [new Literal($shortName . '::class')],
            'name' => new Literal('self::WORKFLOW_NAME')]);
        $class->addConstant('WORKFLOW_NAME', $workflowClass);

        $method = $class->addMethod('__construct');

        // catches everything
        $method = $class->addMethod('get' . $shortName)
            ->setReturnType($entityClassName);
        $parameter = $method
            ->addParameter('event');
        $parameter
            ->setType(Type::union(TransitionEvent::class, GuardEvent::class));

        $method->setBody(sprintf(<<<'PHP'
		/** @var %s */ return $event->getSubject();
PHP, $shortName));

        // catches everything
        $method = $class->addMethod('onGuard')
            ->setReturnType('void')
            ->addAttribute(AsGuardListener::class, [new Literal('self::WORKFLOW_NAME')]);
        $method
            ->addParameter('event')
            ->setType(GuardEvent::class);
        // this would be an appropriate spot for twig
        $body = $this->twig->render('@SurvosWorkflow/_guard_switch.php.twig', $params = [
            'shortName' => $shortName,
            'varName' => lcfirst($shortName),
            'entityClassName' => $entityClassName,
            'transitions' => $transitionConstants,
        ]);

        $method->setBody($body);

        foreach ($transitionConstants as $transitionConstant) {

//            dd($transitionConstants);
            // now the transitions
            $name = 'on' . u($transitionConstant)->after('TRANSITION_')->lower()->camel()->title()->toString();
            $method = $class->addMethod($name)
                ->setReturnType('void')
                ->addAttribute(AsTransitionListener::class, [
                    new Literal('self::WORKFLOW_NAME'),
                    new Literal($transitionConstant)
                ]);
            $method
                ->addParameter('event')
                ->setType(TransitionEvent::class);
            $body = $this->twig->render('@SurvosWorkflow/_transition_body.php.twig', $params);
            $method->setBody($body);
//            dd((string)$method);
        }

        $this->writeFile($namespace, $workflowClass);


        return self::SUCCESS;
    }

    private function writeFile(PhpNamespace $namespace, string $className)
    {
        $fn = $this->dir . "/$className.php";

        $code = "<?php\n\n" . $namespace;
//        $code = preg_replace('/\'(self.*?)\'/', "$1", $code);

        file_put_contents($fn, $code);
    }
}
