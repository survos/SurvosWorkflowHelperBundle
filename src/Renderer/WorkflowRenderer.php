<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Survos\WorkflowBundle\Renderer;

use Doctrine\Common\Inflector\Inflector;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;

class WorkflowRenderer
{
    private $generator;

    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
    }

    public function render(ClassNameDetails $formClassDetails, array $formFields,
                           ClassNameDetails $boundClassDetails = null,
                           array $constraintClasses = [], array $extraUseClasses = [])
    {
        $fieldTypeUseStatements = [];
        $fields = [];
        foreach ($formFields as $name => $fieldTypeOptions) {
            $fieldTypeOptions = $fieldTypeOptions ?? ['type' => null, 'options_code' => null];

            if (isset($fieldTypeOptions['type'])) {
                $fieldTypeUseStatements[] = $fieldTypeOptions['type'];
                $fieldTypeOptions['type'] = Str::getShortClassName($fieldTypeOptions['type']);
            }

            $fields[$name] = $fieldTypeOptions;
        }


        $mergedTypeUseStatements = array_merge($fieldTypeUseStatements, $extraUseClasses);
        sort($mergedTypeUseStatements);

        $entityVarSingular = lcfirst(Inflector::singularize($boundClassDetails->getShortName()));

        /*
        $entityTwigVarPlural = Str::asTwigVariable($entityVarPlural);
        $entityTwigVarSingular = Str::asTwigVariable($entityVarSingular);

        $routeName = Str::asRouteName($controllerClassDetails->getRelativeNameWithoutSuffix());
        $templatesPath = Str::asFilePath($controllerClassDetails->getRelativeNameWithoutSuffix());
         */

        $generatedFilename= $this->generator->generateClass(
            $formClassDetails->getFullName(),
            __DIR__ . '/../Resources/skeleton/Request/ParamConverter/ParamConverter.tpl.php',
            $v=[
                'entity_full_class_name' => $boundClassDetails ? $boundClassDetails->getFullName() : null,
                'entity_class_name' => $boundClassDetails ? $boundClassDetails->getShortName() : null,
                'form_fields' => $fields,
                'entity_var_name' => $entityVarSingular,
                'entity_unique_name' => $entityVarSingular . 'Id',
                'field_type_use_statements' => $mergedTypeUseStatements,
                'constraint_use_statements' => $constraintClasses,
                'shortClassName' => $formClassDetails->getShortName(),
            ]
        );
        dump($generatedFilename, $v, $formClassDetails);
        $contents = $this->generator->getFileContentsForPendingOperation($generatedFilename);
    }
}
