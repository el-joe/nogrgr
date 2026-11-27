<?php

use Illuminate\Queue\SerializesModels;
use Symfony\Component\Finder\Finder;

/*
| RQA-ADM-006: SerializesModels::__unserialize() restores properties from the scope of the
| queued class, so a readonly property declared in a parent class cannot be initialised
| ("Cannot initialize readonly property Parent::$x from scope Child") and the queued
| notification / job fails in the worker. Readonly promoted properties are only safe on
| the class that is actually queued.
*/

it('queues no class that inherits a readonly property from its parent', function (): void {
    $root = dirname(__DIR__, 3);
    $violations = [];

    foreach ((new Finder)->files()->in($root.'/app')->name('*.php')->notPath(['resources', 'Database', 'routes']) as $file) {
        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        if (! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || ! in_array(SerializesModels::class, class_uses_recursive($class), true)) {
            continue;
        }

        foreach ($reflection->getProperties() as $property) {
            if ($property->isReadOnly() && $property->getDeclaringClass()->getName() !== $class) {
                $violations[] = $class.'::$'.$property->getName().' (readonly in '.$property->getDeclaringClass()->getName().')';
            }
        }
    }

    expect($violations)->toBeEmpty();
});
