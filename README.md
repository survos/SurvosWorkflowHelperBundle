# workflow-helper-bundle

Configure a workflow using PHP attributes.  Use just one class to configure and act on the workflow events.  (Or create an interface with the configuration for easy separation).

auto-registration!

@todo: https://joppe.dev/2024/10/11/dynamic-workflows-with-symfony-workflow-component/

for easyadmin integration, also see https://github.com/WandiParis/EasyAdminPlusBundle


```php
<?php
// SubmissionWorkflowInterface.php

namespace App\Workflow;

use Survos\WorkflowBundle\Attribute\Place;
use Survos\WorkflowBundle\Attribute\Transition;

interface SubmissionWorkflowInterface
{
    const WORKFLOW_NAME='SubmissionWorkflow';

    #[Place(initial: true, metadata: ['description' => "starting place after submission"])]
    const PLACE_NEW='new';
    #[Place(metadata: ['description' => "waiting for admin approval"])]
    const PLACE_WAITING='waiting';
    const PLACE_APPROVED='approved';
    const PLACE_REJECTED='rejected';
    const PLACE_WITHDRAWN='withdrawn';

    #[Transition(from:[self::PLACE_NEW], to: self::PLACE_WAITING)]
    const TRANSITION_SUBMIT='submit';
    #[Transition(from:[self::PLACE_NEW], to: self::PLACE_APPROVED, guard: "is_granted('ROLE_ADMIN')")]
    const TRANSITION_APPROVE='approve';
    #[Transition(from:[self::PLACE_NEW], to: self::PLACE_REJECTED, guard: "is_granted('ROLE_ADMIN')")]
    const TRANSITION_REJECT='reject';

    #[Transition(from:[self::PLACE_NEW, self::PLACE_APPROVED], to: self::PLACE_WITHDRAWN, guard: "is_granted('ROLE_USER')")]
    const TRANSITION_WITHDRAW='withdrawn';

    #[Transition(from:[self::PLACE_REJECTED, self::PLACE_APPROVED], to: self::PLACE_NEW)]
    const TRANSITION_RESET='reset';

}
```

Now create a class that implements the interface (to get the constants) and acts on the events.



```bash
symfony new workflow-demo  --webapp --php=8.4 && cd workflow-demo 
composer config extra.symfony.allow-contrib true
bin/console importmap:require d3

composer config minimum-stability beta
bin/console make:controller d3 -i
symfony server:start -d
symfony open:local --path=/d3



../survos/bin/lb.sh workflow-helper
# composer req survos/workflow-helper-bundle
bin/console make:controller d3 -i
cat > templates/d3  .html.twig <<END
{% extends 'base.html.twig' %}

{% block body %}
workflow here.

{% endblock %}
END
symfony server:start -d
symfony open:local --path=/d3

```

## Notes

Since the workflow may use a message bus, a reminder on how to configure that with the Symfony CLI: https://symfony.com/doc/current/setup/symfony_server.html#symfony-server_configuring-workers

https://github.com/survos/SurvosWorkflowHelperBundle/network/dependents
https://github.com/codereviewvideos/symfony-workflow-example

