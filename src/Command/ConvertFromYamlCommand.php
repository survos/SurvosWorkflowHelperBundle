<?php

namespace Survos\WorkflowBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Yaml\Yaml;
use function Symfony\Component\String\u;

#[AsCommand(name: 'survos:workflow:convert')]
class ConvertFromYamlCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDescription('Add PLACE_ and TRANSITION_ constants to classes and create php config')
            ->addArgument('yamlFilename', InputArgument::REQUIRED, 'yaml filename or URL');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // https://tomasvotruba.com/blog/2020/07/27/how-to-switch-from-yaml-xml-configs-to-php-today-with-migrify/

        $io = new SymfonyStyle($input, $output);
        $fileOrUrl = $input->getArgument('yamlFilename');
        $yarm = file_get_contents($fileOrUrl);
        foreach (Yaml::parse($yarm)['framework']['workflows'] as $flowCode => $definition) {
            $constants = [];

            $supports = $definition['supports'];
            if (! is_array($supports)) {
                $supports = [$supports];
            }
            foreach ($supports as $class) {
                if (class_exists($class) && ($class <> 'stdClass')) {
                    foreach ($definition['places'] as $placeCode => $place) {
                        $constants[$class]['PLACE_'][] = is_string($place) ? $place : $placeCode;
                    }
                    foreach ($definition['transitions'] as $transitionCode => $transition) {
                        $constants[$class]['TRANSITION_'][] = $transitionCode;
                    }
                }
            }

            $php = '';
            $slugger = new AsciiSlugger();
            foreach ($constants as $class => $constantsByType) {
                foreach ($constantsByType as $type => $newConstants) {
                    foreach ($newConstants as $newConstant) {
                        $newConstant = $slugger->slug($newConstant, '_');
                        $php .= sprintf("const %s%s='%s';\n", $type, strtoupper($newConstant), $newConstant);
                    }
                }
                $io->write("Add the following to " . $class . "\n\n");
                $io->write($php);
                //            print($php);
            }
        }

        return self::SUCCESS;
    }
}
