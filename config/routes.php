<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Survos\WorkflowBundle\Controller\WorkflowController;

return function (RoutingConfigurator $routes) {
    $routes->add('survos_workflows', '/workflows')
        ->controller(WorkflowController::class, 'workflows');

    $routes->add('survos_workflow', '/workflow/{flowCode')
        ->controller(WorkflowController::class, 'workflow');
};
