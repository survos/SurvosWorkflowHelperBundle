<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\Set\SensiolabsSetList;
use Rector\Nette\Set\NetteSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\CodeQuality\Rector\ClassMethod\ReturnTypeFromStrictScalarReturnExprRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictBoolReturnExprRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNativeFuncCallRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNewArrayRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src/Command'
    ]);

    $rectorConfig->rules([
        \Rector\Symfony\Rector\Class_\CommandPropertyToAttributeRector::class,
        ReturnTypeFromStrictBoolReturnExprRector::class,
        ReturnTypeFromStrictNativeFuncCallRector::class,
        ReturnTypeFromStrictNewArrayRector::class,
        ReturnTypeFromStrictScalarReturnExprRector::class,
    ]);

    // register a single rule
    $rectorConfig->ruleWithConfiguration(
        \Rector\Php80\Rector\Class_\AnnotationToAttributeRector::class,
        [
            new \Rector\Php80\ValueObject\AnnotationToAttribute('ApiPlatform\Core\Annotation\ApiFilter'),
            new \Rector\Php80\ValueObject\AnnotationToAttribute('ApiPlatform\Core\Annotation\ApiResource'),
            new \Rector\Php80\ValueObject\AnnotationToAttribute('ApiPlatform\Core\Annotation\ApiProperty'),
            new \Rector\Php80\ValueObject\AnnotationToAttribute('ApiPlatform\Core\Annotation\ApiSubresource'),
            new \Rector\Php80\ValueObject\AnnotationToAttribute('Gedmo\Mapping\Annotation\Tree'),
            new \Rector\Php80\ValueObject\AnnotationToAttribute('Gedmo\Mapping\Annotation\TreeRoot'),
            new \Rector\Php80\ValueObject\AnnotationToAttribute('Gedmo\Mapping\Annotation\TreeParent'),
            new \Rector\Php80\ValueObject\AnnotationToAttribute('Gedmo\Mapping\Annotation\TreeLeft'),
            new \Rector\Php80\ValueObject\AnnotationToAttribute('Gedmo\Mapping\Annotation\TreeLevel'),
            new \Rector\Php80\ValueObject\AnnotationToAttribute('Gedmo\Mapping\Annotation\TreeRight'),

            new \Rector\Php80\ValueObject\AnnotationToAttribute('Gedmo\Mapping\Annotation\Timestampable'),
            new \Rector\Php80\ValueObject\AnnotationToAttribute('Gedmo\Mapping\Annotation\Slug'),




        ]
    );


    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        SymfonySetList::SYMFONY_60,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        NetteSetList::ANNOTATIONS_TO_ATTRIBUTES,
        SensiolabsSetList::FRAMEWORK_EXTRA_61,
    ]);
};

