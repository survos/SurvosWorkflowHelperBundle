<?php


namespace Survos\WorkflowBundle\Event;

use Survos\WorkflowBundle\Command\IterateCommand;
use Symfony\Contracts\EventDispatcher\Event;

class RowEvent extends Event
{

    public const string PRE_ITERATE='pre_load';
    public const string LOAD='load';
    public const string POST_LOAD='post_load';
    public const string DISCARD='discard'; // do not import the row

    public function __construct(
        public string          $className,
        private ?object         $entity=null,
        public int|string|null $key=null, // the pk
        public ?int            $index=null, // numeric index so callback can stop after a limit or show progress
        public ?int            $total=null, // so we can act on the first or last row, add a progressBar, etc.
        public ?string         $type=self::LOAD, // defaults to regular row load
        public ?string         $action=null,
        public array           $context = []
    )
    {
        if ($type) {
            assert(in_array($type, [self::PRE_ITERATE, self::LOAD, self::POST_LOAD, self::DISCARD]));
        }
        if ($this->action) {
            assert(class_exists($this->action));
        }
    }

    public function getEntity(): ?object
    {
        return $this->entity;
    }

    public function isPreLoad(): bool
    {
        return $this->type === self::PRE_ITERATE;
    }
    public function isRowLoad(): bool
    {
        return $this->type === self::LOAD;
    }
    public function isPostLoad(): bool
    {
        return $this->type === self::POST_LOAD;
    }

    public function isIterate(): bool
    {
        return $this->action === IterateCommand::class;
    }
}
