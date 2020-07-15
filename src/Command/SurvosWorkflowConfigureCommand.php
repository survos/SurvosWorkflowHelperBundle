<?php

namespace Survos\WorkflowBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use function Symfony\Component\String\u;

class SurvosWorkflowConfigureCommand extends Command
{
    protected static $defaultName = 'survos:workflow:configure';
    /**
     * @var string
     */
    private $projectDir;

    public function __construct(string $projectDir, string $name = null)
    {
        parent::__construct($name);
        $this->projectDir = $projectDir;
    }

    protected function configure()
    {
        $this
            ->setDescription('Create a workflow from an entity')
            ->addArgument('class', InputArgument::OPTIONAL, 'Class')
            ->addOption('property', null, InputOption::VALUE_OPTIONAL, 'Marking Store Property', 'status')
        ;
    }

    private function phpConstant($class, $s) {
        return sprintf('!php/const %s::%s', $class, $s);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $class = $input->getArgument('class');
        if (!class_exists($class)) {
            $class = "App\\Entity\\$class";
        }

        $workflowName = $class;

        // could look for entity properties to find ['status','marking','state']
        $property = $input->getOption('property');

        // $config = $this->getConfigurationArray($class);
        $config = [
            'type' => 'state_machine',
            'audit_trail' => ['enabled' => true],
            'marking_store' => ['property' => $property],
        ];

        $reflectionClass = new \ReflectionClass($class);
        $shortName = $reflectionClass->getShortName();
        $constants =  $reflectionClass->getConstants();

        $config['supports'] = $class;

        // map the place names to PHP constants
        $places =
            array_map(function ($s) use ($class) {
                // get the value and use for meta
                $value = constant($class . '::' . $s);
               //  return [$this->phpConstant($class, $s) => [
                return [$value => [
                    'metadata' => [
                        'label' => u($value)->ascii()->replace('_', ' ')->title(true)->toString(),
                        'description' => ''
                    ]
                ]
                ];
            }, array_filter(array_keys($constants), function ($s) { return preg_match('/^PLACE_/', $s); }) );

        $placeList = array_map(function ($var) { return array_key_first($var); }, array_values($places));

        if (!count($places)) {
            $io->error("Define PLACE_ constants in class $class");
            return 1;
        }

        $initialPlace = $placeList[0];
        $transitions = [];
        foreach (array_filter(array_keys($constants), function ($s) { return preg_match('/^TRANSITION_/', $s); }) as $transitionConstant) {
            static $i=0;
            $value = constant(sprintf('%s::%s', $class, $transitionConstant));
            $transitions[$value] = [
            // $transitions[$this->phpConstant($class, $transitionConstant)] = [
                'from' => $placeList[$i % count($placeList)],
                'to' => $placeList[++$i % count($placeList)], // and is_granted('PROJECT_ADMIN', subject)
                'guard' => "is_fully_authenticated()",
                'metadata' => [
                    'label' => u($value)->ascii()->replace('_', ' ')->title(true)->toString(),
                    'description' => ''
                ]
            ];
        }

        if (!count($transitions)) {
            $io->error("Define at least one TRANSITION_ constant in class $class");
            return 1;
        }

        $config['transitions'] = $transitions;

        // may need the key if places is expanded!
        $config['initial_marking'] = $initialPlace;

        // walk through the places and add an option for metadata
        $result = array_column($places, 'name', 'code');
        foreach ($places as $idx=>$record) {
            $result[key($record)] = $record[key($record)];
        }
        $config['places'] = $result; // array_map(function ($p) { return key($p) => $p]; }, $places);

        // dump($constants, $config);
        // $yaml =  Yaml::dump([$workflowName => $config], 5);
        $yaml =  Yaml::dump(
            ['framework' => ['workflows' => [$workflowName => $config]]], 7);

        // get rid of quotes around php constant
        $fn = $this->projectDir . sprintf('./config/packages/workflow_%s.yaml', $shortName);

        $yaml = preg_replace("|'(!php/const[^']+)\'|", '$1', $yaml);
        print $yaml;

        file_put_contents($fn, $yaml);

        $io->success("Workflow created for $class in $fn");

        return 0;
    }

    private function getConfigurationArray($workflowName)
    {
        return Yaml::parse(
            <<< EOL
$workflowName:
        type: 'state_machine'
        audit_trail:
            enabled: true
        marking_store:
            type: 'method'
            property: 'currentPlace'
        supports:
            - App\Entity\Class
EOL

        );
    }
}
