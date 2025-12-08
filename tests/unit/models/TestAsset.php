<?php

namespace Hirtz\Media\video\tests\unit\models;

use Hirtz\Media\models\interfaces\AssetInterface;
use Hirtz\Media\models\interfaces\AssetParentInterface;
use Hirtz\Media\models\traits\AssetTrait;
use Hirtz\Skeleton\db\ActiveRecord;

class TestAsset extends ActiveRecord implements AssetInterface
{
    use AssetTrait;

    public function attributes(): array
    {
        return [
            'id',
            'type',
            'file_id',
            'parent_id',
        ];
    }

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
