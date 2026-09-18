<?php
declare(strict_types=1);

namespace App\Domain\Enums;

enum Badge: string
{
    case Bestseller = 'Bestseller';
    case New = 'New';
    case JustIn = 'Just In';
    case Limited = 'Limited';
    case Signature = 'Signature';

    public function label(): string
    {
        return $this->value;
    }
}