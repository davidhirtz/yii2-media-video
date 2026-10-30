<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Streaming;

use DateTimeImmutable;

interface ListableStreamProviderInterface extends StreamProviderInterface
{
    /**
     * Every video of this installation's scope, keyed by id, with its creation time where the provider has one.
     *
     * @return iterable<string, DateTimeImmutable|null>
     */
    public function getVideos(): iterable;
}
