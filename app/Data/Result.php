<?php

namespace App\Data;

final readonly class Result
{
    public function __construct(
        public bool $success,
        public ?string $error = null,
        public mixed $data = null,
    ) {}
}
