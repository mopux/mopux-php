<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

/**
 * Marks a field as "not sent", as opposed to null, which the API uses to clear
 * a value (description, dates and hours).
 *
 *     new TaskUpdate(description: null);            // clears the description
 *     new TaskUpdate(title: 'New title');           // description untouched
 */
enum Missing
{
    case Value;
}
