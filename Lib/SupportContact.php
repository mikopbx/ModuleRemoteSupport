<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

final readonly class SupportContact
{
    public function __construct(
        public string $type,
        public string $label,
        public string $uri,
    ) {
    }

    /**
     * @return array{type: string, label: string, uri: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'uri' => $this->uri,
        ];
    }
}
