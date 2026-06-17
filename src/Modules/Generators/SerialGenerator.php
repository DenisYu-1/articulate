<?php

namespace Articulate\Modules\Generators;

/**
 * Serial generator for PostgreSQL SERIAL/SEQUENCE primary keys.
 */
class SerialGenerator extends AbstractGenerator {
    public function __construct()
    {
        parent::__construct('serial');
    }
}
