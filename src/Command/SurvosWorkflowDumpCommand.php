<?php

namespace Survos\WorkflowBundle\Command;

use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'survos:workflow:dump')]
class SurvosWorkflowDumpCommand extends Command
{

    public function __construct(private WorkflowHelperService $helper, private TranslatorInterface $translator,
                            /** @var WorkflowInterface[] */
                                private iterable $workflows,
                                ?string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setDescription('create .json file for workflow metadata and translations')
            ->addArgument('flowCodes', InputArgument::OPTIONAL, 'Flow Codes', 'fetched,raw,site')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $data = [];

        $helper = $this->helper;
        $flowCodes = explode(',', $input->getArgument('flowCodes'));

        foreach ($helper->getWorkflowsByCode() as $flowCode => $workflowArray) {
            if (! in_array($flowCode, $flowCodes)) {
                continue;
            }

            $io->title("Creating $flowCode");

            // $workflow = $workflowArray['workflow'];

            $wrapper = $helper->getWorkflowsByCode($flowCode);

            /** @var Workflow $workflow */
            $workflow = $wrapper['workflow'];

            $entity = $wrapper['entity'];

            $marking = $workflow->getMarking($entity);
            $markingStore = $workflow->getMarkingStore();

            // unset the current state

            $metadataStore = $workflow->getMetadataStore();
            foreach ($workflow->getDefinition()->getPlaces() as $idx => $place) {
                //                dd(get_class($metadataStore));

                //                $data = $metadataStore->getPlaceMetadata($place)->get('icon');
                $metaData = $metadataStore->getPlaceMetadata($place);

                $style = $metaData['dump_style'] ?? null;
                $color = 'red';

                $icon = $metaData['icon'] ?? ($style['icon'] ?? '~');
                $color = $metaData['iconColor'] ?? ($style['iconColor'] ?? 'yellow');
                $class = $metaData['class'] ?? '~';

                // dump($place, $data);
                // $icon = 'fa-question-mark';

                // get the defaults from the workflow translation array

                $data[$flowCode]['places'][$place] =
                    [
                        'label' => $this->trans(sprintf('%s.places.%s.label', $flowCode, $place), ucfirst($place)),
                        'icon' => $icon, // $this->trans(sprintf('%s.places.%s.icon', $flowCode, $place), $icon),
                        'color' => $color, // $this->trans(sprintf('%s.places.%s.color', $flowCode, $place), $color),
                    ];
            }

            foreach ($workflow->getDefinition()->getTransitions() as $idx => $transition) {
                $transitionCode = $transition->getName();
                $metaData = $metadataStore->getTransitionMetadata($transition);
                $style = $metaData['dump_style'] ?? null;

                $icon = $metaData['icon'] ?? ($style['icon'] ?? '~');
                $color = $metaData['iconColor'] ?? ($style['iconColor'] ?? 'yellow');
                $class = $metaData['class'] ?? '~';

                $data[$flowCode]['transitions'][$transitionCode] =
                    [
                        'label' => $this->trans(sprintf('%s.transitions.%s.label', $flowCode, $transitionCode), $transitionCode),
                        'icon' => $icon,
                        'iconColor' => $color,
                    ];
            }
        }

        $path = __DIR__ . '/../../assets/js'; // how do you call getProjectRoot from a Command??

        $js = "let workflowData = " . json_encode($data);

        file_put_contents($output = $path . '/survos-workflow.json', json_encode($data, JSON_PRETTY_PRINT));

        $io->success("File $output written");
        return self::SUCCESS;
    }

    private function trans($string, $default)
    {
        $translator = $this->translator;
        $t = $translator->trans($string);
        if ($t === $string) {
            $t = $default;
        }
        return $t;
    }
}
