<?php

namespace Articulate\Modules\EntityManager;

enum EntityState
{
    case NEW;      // Entity created but not yet persisted
    case MANAGED;  // Entity loaded from or persisted to database
    case REMOVED;  // Entity marked for deletion
}
