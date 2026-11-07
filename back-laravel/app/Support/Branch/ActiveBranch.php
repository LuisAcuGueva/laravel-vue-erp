<?php

declare(strict_types=1);

namespace App\Support\Branch;

use App\Modules\Maintenance\Models\Branch;
use LogicException;

class ActiveBranch
{
    private ?Branch $branch = null;

    public function set(Branch $branch): void
    {
        $this->branch = $branch;
    }

    public function get(): Branch
    {
        if ($this->branch === null) {
            throw new LogicException('ActiveBranch no ha sido resuelta para esta petición.');
        }

        return $this->branch;
    }

    public function id(): string
    {
        return $this->get()->id;
    }

    public function has(): bool
    {
        return $this->branch !== null;
    }
}
