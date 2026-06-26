<?php

namespace Articulate\Modules\Generators;

/**
 * Auto-increment generator for integer primary keys.
 */
class AutoIncrementGenerator extends AbstractGenerator {
    public function __construct()
    {
        parent::__construct('auto_increment');
    }
}
