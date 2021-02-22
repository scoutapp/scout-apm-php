<?php
/** @noinspection AutoloadingIssuesInspection */

declare(strict_types=1);

namespace Symfony\Component\HttpKernel\Event
{
    if (! class_exists(ControllerEvent::class) && class_exists(FilterControllerEvent::class)) {
        class ControllerEvent extends FilterControllerEvent {
        }
    }
}
