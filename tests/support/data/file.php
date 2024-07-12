<?php

use davidhirtz\yii2\media\models\collections\FolderCollection;
use davidhirtz\yii2\media\models\Folder;
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