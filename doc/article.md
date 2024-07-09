# Article 

I love the Symfony Workflow bundle.  It's an elegant and powerful solution to managing state within an entity.

I recently had the need to handle the situation where a user uploads a photo, and a link is sent to admin to approve or reject the photo.  Only an admin could approve or reject the photo, but the user could also withdraw the photo for any reason.

The most common way to define a workflow in the workflow.yaml file in config/packages.  But that hard-codes strings, and I hate needing to remember a particular string.  

```yaml
workflow:
  submission:
    ...
```
You can use PHP constants in YAML files, shown in [link to article].  I used this method for a few years, but still found it hard to read and I liked auto-complete and the ability to reflect a single constant and make changes throughout the code.

So I switched the workflow definition to PHP, which finally gave me auto-complete and a single source of the names of the places and transitions.  

I've used this method for a few years, defining the workflow in the entity that uses to workflow, since most of the time the workflow only applies to one entity per project.

But I still found it hard to read and unnatural.

```php
// Submission.php
class Submission {
   const TRANSITION_APPROVE='approve';
}
```

```php
return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'workflows' => [
            'Submission' => [
                'supports' => Submission::class,
                'transitions' => [
                    Sheet::TRANSITION_APPROVE  => [
                        'from' => [Submission::PLACE_WAITING],
                        'to' => [Submission::PLACE_APPROVED],
                    ],
                'type' => 'state_machine',

```

Inspired by zenstruck/console-extra-bundle's approach to defining arguments and options, I created a bundle that would allow places and transitions to be defined via attributes above their constants.  This finally gave me a succinct and code-friendly way to define the workflow.

### Enough as is

If you're new to workflows and state machines, I would argue that this is enough.  Almost anytime you have a ->setStatus() method, you probably have conditions, and a few minutes of configurating gets rid of a bunch of code.

```php
if ($age > $oneMonth) {
    if ($entity->getStatus() in ['published', 'promoted'] ) {
        $entity->setStatus('archived');
    }
}

if ($age > $oneMonth) {
    if ($workflow->can($entity, 'archive')) {
        $workflow->apply($entity, 'archive')
    }
}
```

In short, if you're setting a status that can be part of a state machine, you should be using the workflow bundle.   Because in addition to getting a more robust way to set the current state, you get events.

## Events

Events happen at every stage of the workflow, see [symfony docs] and [article].

But the event subscribers felt somewhat awkward

```php
    public static function getSubscribedEvents(): array
    {
        $completePrefix = 'workflow.Submission.transition.';
        $guardPrefix = 'workflow.Submission.guard.';

        return [
            $guardPrefix . Submission::TRANSITION_APPROVE => 'guardConsolidatedMustExist',
            $completePrefix . Submission::TRANSITION_LOAD => 'onLoadCatalog',
            ...
```

Functional, but I hated needing a key that was constructed by concatenating what I wanted and a value that was the name of a method.  I even created a script that read the workflow and generated the keys and methods because it was so verbose and easy to confuse.

## Using Attributes 

Symfony recently introduced AsTransitionListener and AsGuardListener attributes, and now the workflow actions can be consolidated in a single class, or 2 classes if you want to separate the workflow definition from the actions.

In this case, I want to email the admins upon submission with a link to approve or reject the submission.  This is already set up as an async message, so I can deploy it with 

     $this->bus->dispatch(new SendSubmissionForApprovalMessage($submission->getId());

While it's easy enought to put this in the SubmissionController:new() method, right after the ->flush() call, suppose the submission comes in via email or Slack?  Instead, after the entity is created we want to apply TRANSITION_SUBMIT by listening for it.

We can also make sure that only admins and the owner of the submission are allowed to

```php
// SubmissionWorkflow.php
class SubmissionWorkflow implements SubmissionWorkflowInterface
{
    #[AsTransitionListener(self::WORKFLOW_NAME, self::TRANSITION_SUBMIT)]
    public function onSubmit(TransitionEvent $event): void
    {
        $submission = $event->getSubject();
        $this->bus->dispatch((new SendForApproval($submission->getId())));
    }
    
    #[AsGuardListener(self::WORKFLOW_NAME, transition: self::TRANSITION_WITHDRAW)]
    public function onGuard(GuardEvent $event): void
    {
        $submission = $event->getSubject();
        $user = $this->security->getUser();
        if (!$submission->getUser() !== $user) {
            $event->setBlocked(true, "Only the submission owner can withdraw");
        }
    }
}
```

As a bonus, the bundle provide an include file and trait that makes interactively applying transitions very easy.  While there are many ways to set it up, one that's easy and very few lines of code is to add a XX_transition route with the _show controller.  


