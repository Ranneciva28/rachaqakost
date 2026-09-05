<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;

class MediaController extends Controller
{
    public function show(MediaFile $media)
    {
        abort_unless(in_array($media->kind,['HERO','CATEGORY'],true),404);
        return response($media->contents,200,['Content-Type'=>$media->mime_type,'Content-Length'=>(string)$media->size,
            'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'public, max-age=604800, immutable']);
    }
}
