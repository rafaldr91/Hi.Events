<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\GdprExportTokenDomainObject;
use HiEvents\Models\GdprExportToken;
use HiEvents\Repository\Interfaces\GdprExportTokenRepositoryInterface;

/**
 * @extends BaseRepository<GdprExportTokenDomainObject>
 */
class GdprExportTokenRepository extends BaseRepository implements GdprExportTokenRepositoryInterface
{
    protected function getModel(): string
    {
        return GdprExportToken::class;
    }

    public function getDomainObject(): string
    {
        return GdprExportTokenDomainObject::class;
    }
}
