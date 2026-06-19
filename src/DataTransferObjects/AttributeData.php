<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\DataTransferObjects;

use Illuminate\Support\Collection;

final readonly class AttributeData
{
    /**
     * @param  Collection<string, mixed>|null  $meta
     */
    public function __construct(
        public string $name,
        public mixed $value = null,
        public ?Collection $meta = null,
    ) {}

    /**
     * @param  Collection<string, mixed>|null  $meta
     */
    public static function make(string $name, mixed $value = null, ?Collection $meta = null): self
    {
        return new self($name, $value, $meta);
    }

    /**
     * Convert a legacy name => value map into a list of attribute DTOs.
     *
     * @param  array<string, mixed>  $items
     * @return list<self>
     */
    public static function collection(array $items): array
    {
        $data = [];

        foreach ($items as $name => $value) {
            $data[] = new self((string) $name, $value);
        }

        return $data;
    }
}
