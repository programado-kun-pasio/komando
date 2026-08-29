<?php declare(strict_types=1);

namespace Programado\Komando\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Programado\Komando\Files\Contracts\HasFileAttachmentsContract;
use Programado\Komando\Files\Traits\HasFileAttachments;

final class TestOwner extends Model implements HasFileAttachmentsContract
{
    use HasFileAttachments;

    protected $table = 'owners';

    protected $guarded = ['id'];
}
