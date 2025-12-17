<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\tests\unit\Models;

use Hirtz\Media\Models\interfaces\AssetInterface;
use Hirtz\Media\Models\interfaces\AssetParentInterface;
use Hirtz\Media\Models\Traits\AssetTrait;
use Hirtz\Skeleton\Db\ActiveRecord;

class TestAsset extends ActiveRecord implements AssetInterface
{
    use AssetTrait;

    #[\Override]
    public function attributes(): array
    {
        return [
            'id',
            'type',
            'file_id',
            'parent_id',
        ];
    }

    #[\Override]
    public function rules(): array
    {
        return [
            [
                ['alt_text'],
                'string',
            ],
        ];
    }

    public function getFileCountAttribute(): string
    {
        return 'asset_count';
    }

    public function getParent(): AssetParentInterface
    {
        return TestAssetParent::instance();
    }

    public function getParentGridView(): string
    {
        return '';
    }

    public function getParentName(): string
    {
        return 'Test Parent';
    }
}
