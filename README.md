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
