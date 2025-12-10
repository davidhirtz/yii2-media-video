<?php

declare(strict_types=1);

use Hirtz\Media\Models\collections\FolderCollection;
use Hirtz\Media\Models\Folder;
use yii\db\Expression;

$folder = FolderCollection::getDefault();

return [
    'image' => [
        'name' => 'Image',
        'folder_id' => $folder->id,
        'alt_text' => 'Image Alt Text',
        'basename' => 'image',
        'extension' => 'jpg',
        'width' => 200,
        'height' => 100,
        'created_at' => new Expression('UTC_TIMESTAMP()'),
    ],
    'video' => [
        'name' => 'Video',
        'folder_id' => $folder->id,
        'basename' => 'video',
        'extension' => 'mp4',
        'width' => 200,
        'height' => 100,
        'created_at' => new Expression('UTC_TIMESTAMP()'),
    ],
];
