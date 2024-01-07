<?php

use davidhirtz\yii2\media\models\Folder;
use yii\db\Expression;

return [
    'image' => [
        'name' => 'Image',
        'folder_id' => Folder::getDefault()->id,
        'alt_text' => 'Image Alt Text',
        'basename' => 'image',
        'extension' => 'jpg',
        'width' => 200,
        'height' => 100,
        'created_at' => new Expression('UTC_TIMESTAMP()'),
    ],
    'video' => [
        'name' => 'Video',
        'folder_id' => Folder::getDefault()->id,
        'basename' => 'video',
        'extension' => 'mp4',
        'width' => 200,
        'height' => 100,
        'created_at' => new Expression('UTC_TIMESTAMP()'),
    ],
];