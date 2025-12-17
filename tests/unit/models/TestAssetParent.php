<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\tests\unit\Models;

use Hirtz\Media\Models\interfaces\AssetParentInterface;
use Hirtz\Media\Models\Traits\AssetParentTrait;
use Hirtz\Skeleton\Db\ActiveQuery;
use Hirtz\Skeleton\Db\ActiveRecord;

class TestAssetParent extends ActiveRecord implements AssetParentInterface
{
    use AssetParentTrait;

    #[\Override]
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
