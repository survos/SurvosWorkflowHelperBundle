# workflow-helper-bundle

Configure a workflow using PHP attributes.  Use just one class to configure and act on the workflow events.  (Or create an interface with the configuration for easy separation).

```php
// S
```

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

## Notes

Since the workflow may use a message bus, a reminder on how to configure that with the Symfony CLI: https://symfony.com/doc/current/setup/symfony_server.html#symfony-server_configuring-workers

https://github.com/survos/SurvosWorkflowHelperBundle/network/dependents
https://github.com/codereviewvideos/symfony-workflow-example

