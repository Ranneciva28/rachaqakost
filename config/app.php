<?php
return ['name'=>env('APP_NAME','RachaqaKost'),'env'=>env('APP_ENV','production'),'debug'=>(bool)env('APP_DEBUG',false),'url'=>env('APP_URL','http://localhost'),'timezone'=>env('APP_TIMEZONE','Asia/Jakarta'),'locale'=>env('APP_LOCALE','id'),'fallback_locale'=>'id','faker_locale'=>'id_ID','cipher'=>'AES-256-CBC','key'=>env('APP_KEY'),'previous_keys'=>[],'maintenance'=>['driver'=>'file']];
