<?php
// phpcs:ignoreFile
/** @noinspection AutoloadingIssuesInspection */

declare(strict_types=1);

namespace Symfony\Component\HttpKernel\Event
{
    if (! class_exists(ControllerEvent::class) && class_exists(FilterControllerEvent::class)) {
        /** @internal This class extends a third party vendor, so we mark as internal to not expose upstream BC breaks */
        class ControllerEvent extends FilterControllerEvent {
        }
    }
}

namespace Scoutapm\ScoutApmBundle\Twig
{
    use Twig\Environment as Twig;

    if (class_exists(Twig::class)) {
        /**
         * @psalm-suppress RedundantCondition
         * @psalm-suppress TypeDoesNotContainType
         */
        if (Twig::MAJOR_VERSION === 2) {
            require_once __DIR__ . '/../../stub/TwigMethods-Twig2.php';
        } else {
            require_once __DIR__ . '/../../stub/TwigMethods-Twig3.php';
        }
    }
}
