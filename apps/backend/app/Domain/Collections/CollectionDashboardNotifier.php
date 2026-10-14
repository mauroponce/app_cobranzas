<?php

namespace App\Domain\Collections;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

final class CollectionDashboardNotifier
{
    private const TTL_SECONDS = 86400;

    public function __construct(private readonly Repository $cache) {}

    public function revision(string $tenantId): string
    {
        return (string) $this->cache->get($this->key($tenantId), '');
    }

    public function notify(string $tenantId): string
    {
        $revision = (string) Str::uuid();
        $this->cache->put($this->key($tenantId), $revision, self::TTL_SECONDS);

        return $revision;
    }

    private function key(string $tenantId): string
    {
        return 'collections:dashboard:'.$tenantId.':revision';
    }
}
