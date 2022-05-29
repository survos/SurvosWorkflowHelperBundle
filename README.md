# workflow-bundle
Add admin/developer interface to workflow component

V2.0 requires Symfony 6.1 (because of the new bundle configuration)

Demo available at (heroku URL), source for demo at 

#### If developing WorkflowBundle (WorkflowExtrasBundle?  WorkflowHelperBundle?)

    composer config repositories.survosworkflow '{"type": "path", "url": "../Survos/WorkflowBundle"}'
    composer req survos/workflow-extension-bundle:"*@dev"
