<?php

namespace Hirtz\Media\video\tests\unit\models;

use Hirtz\Media\models\interfaces\AssetParentInterface;
use Hirtz\Media\models\traits\AssetParentTrait;
use Hirtz\Skeleton\Db\ActiveQuery;
use Hirtz\Skeleton\Db\ActiveRecord;

class TestAssetParent extends ActiveRecord implements AssetParentInterface
{
    use AssetParentTrait;

    public function attributes(): array
    {
        return [
            'id',
            'type',
        ];
    }


    public function getAssets(): ActiveQuery
    {
        return $this->hasMany(TestAsset::class, ['parent_id' => 'id']);
    }
}
