<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Streaming;

interface ProvisionableStreamProviderInterface extends StreamProviderInterface
{
    /**
     * Creates the installation's own webhook scope (a library, a project) with an account key the installation never
     * stores, and answers the params it needs.
     *
     * @return array<string, string>
     */
    public function provision(string $accountKey, string $name, string $webhookUrl): array;
}
