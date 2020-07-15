<?php

namespace Survos\WorkflowBundle\Maker;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Survos\BaseBundle\Renderer\ParamConverterRenderer;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Renderer\FormTypeRenderer;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassDetails;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Validator\Validation;

/**
 * @author Tac Tacelosky <tacman@gmail.com>
 */
final class MakeWorkflow extends AbstractMaker
{
    private $entityHelper;
    /**
     * @var ParamConverterRenderer
     */
    private $paramConverterRenderer;

    public function __construct(DoctrineHelper $entityHelper, WorkflowRenderer $paramConverterRenderer)
    {
        $this->entityHelper = $entityHelper;
        $this->paramConverterRenderer = $paramConverterRenderer;
    }

    public static function getCommandName(): string
    {
        return 'survos:make:param-converter';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConf)
    {
        $command
            ->setDescription('Creates a new param converter class')
            ->addArgument('bound-class', InputArgument::REQUIRED, 'The name of Entity or fully qualified model class name that the new form will be bound to (empty for none)')
            //     ->addArgument('name', InputArgument::OPTIONAL, sprintf('The name of the ParamConverter class (e.g. <fg=yellow>%sType</>)', Str::asClassName(Str::getRandomTerm())))
            ->setHelp(file_get_contents(__DIR__.'/../Resources/help/MakeParamConverter.txt'))
        ;

        $inputConf->setArgumentAsNonInteractive('bound-class');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
    {
        if (null === $input->getArgument('bound-class')) {
            $argument = $command->getDefinition()->getArgument('bound-class');

            $entities = $this->entityHelper->getEntitiesForAutocomplete();

            $question = new Question($argument->getDescription());
            $question->setValidator(function ($answer) use ($entities) {return Validator::existsOrNull($answer, $entities); });
            $question->setAutocompleterValues($entities);
            $question->setMaxAttempts(3);

            $input->setArgument('bound-class', $io->askQuestion($question));
        }
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator)
    {

        $boundClass = $input->getArgument('bound-class');
        $boundClassDetails = null;

        if (null !== $boundClass) {
            $boundClassDetails = $generator->createClassNameDetails(
                $boundClass,
                'Entity\\'
            );

            $doctrineEntityDetails = $this->entityHelper->createDoctrineDetails($boundClassDetails->getFullName());

            if (null !== $doctrineEntityDetails) {
                $formFields = $doctrineEntityDetails->getFormFields();
            } else {
                $classDetails = new ClassDetails($boundClassDetails->getFullName());
                $formFields = $classDetails->getFormFields();
            }
        }

        $paramConverterClassNameDetails = $generator->createClassNameDetails(
            $boundClassDetails->getShortName(),
            'Request\\ParamConverter\\',
            'ParamConverter'
        );

        $templatesPath = Str::asFilePath($paramConverterClassNameDetails->getRelativeNameWithoutSuffix());

        $formFields = ['field_name' => null];


        $this->paramConverterRenderer->render(
            $paramConverterClassNameDetails,
            $formFields,
            $boundClassDetails
        );

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text([
            'Next: Add fields to your form and start using it.',
            'Find the documentation at <fg=yellow>https://symfony.com/doc/current/forms.html</>',
        ]);
    }

    public function configureDependencies(DependencyBuilder $dependencies)
    {
        $dependencies->addClassDependency(
            AbstractType::class,
            // technically only form is needed, but the user will *probably* also want validation
            'form'
        );

        $dependencies->addClassDependency(
            Validation::class,
            'validator',
            // add as an optional dependency: the user *probably* wants validation
            false
        );

        $dependencies->addClassDependency(
            DoctrineBundle::class,
            'orm',
            false
        );
    }
}
