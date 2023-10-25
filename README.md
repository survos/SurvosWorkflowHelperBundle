# workflow-bundle
Add admin/developer interface to workflow component

V2.0 requires Symfony 6.1 (because of the new bundle configuration)

Demo available at (heroku URL), source for demo at 

#### If developing WorkflowBundle (WorkflowExtrasBundle?  WorkflowHelperBundle?)

    composer config repositories.survos_workflow_helper '{"type": "path", "url": "/home/tac/survos/bundles/WorkflowBundle/"}'
```bash
composer config repositories.survos_workflow_helper '{"type": "path", "url": "/home/tac/survos/bundles/WorkflowBundle"}'
composer req survos/workflow-helper-bundle:"*@dev"
```

## Of interest

https://github.com/codereviewvideos/symfony-workflow-example

```bash
symfony new workflow-demo  --webapp --version=next --php=8.2 && cd workflow-demo 
composer config extra.symfony.allow-contrib true
composer req symfony/asset-mapper:^6.4
bin/console importmap:require d3

composer config minimum-stability beta
composer config extra.symfony.allow-contrib true
composer req symfony/stimulus-bundle:2.x-dev
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

