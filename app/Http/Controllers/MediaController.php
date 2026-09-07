<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;

class MediaController extends Controller
{
    public function show(MediaFile $media)
    {
        abort_unless(in_array($media->kind,['HERO','CATEGORY','LOGO','FAVICON'],true),404);
        return $this->stream($media,['Content-Type'=>$media->mime_type,'Content-Length'=>(string)$media->size,
            'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'public, max-age=604800, immutable']);
    }

    private function stream(MediaFile $media,array $headers)
    {
        $contents=$media->contents;
        return response()->stream(function()use($contents){
            if(is_resource($contents)){
                $metadata=stream_get_meta_data($contents);
                if($metadata['seekable']??false)rewind($contents);
                fpassthru($contents);
                return;
            }
            echo $contents;
        },200,$headers);
    }
}
