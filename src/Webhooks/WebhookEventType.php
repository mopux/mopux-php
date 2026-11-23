<?php

declare(strict_types=1);

namespace Mopux\Webhooks;

enum WebhookEventType: string
{
    case TaskCreated = 'task.created';
    case TaskCompleted = 'task.completed';
    case TaskStatusChanged = 'task.status_changed';
    case TaskAssigned = 'task.assigned';
    case TaskUpdated = 'task.updated';
    case TaskMoved = 'task.moved';
    case TaskDeleted = 'task.deleted';
    case Ping = 'ping';
}
