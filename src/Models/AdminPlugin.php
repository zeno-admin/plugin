<?php

namespace Zeno\Plugin\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Zeno\Plugin\Enums\PluginStatus;

/**
 * @property string $key
 * @property string $version
 * @property string|null $reference
 * @property PluginStatus $status
 */
#[Fillable('key', 'version', 'reference', 'status')]
final class AdminPlugin extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'status' => PluginStatus::class,
        ];
    }
}
