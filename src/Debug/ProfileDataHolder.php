<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Debug;

final class ProfileDataHolder
{
    /** @var ProfileData[] */
    private array $data = [];

    public function addData(ProfileData $data): void
    {
        $this->data[] = $data;
    }

    /** @return ProfileData[] */
    public function getData(): array
    {
        return $this->data;
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
