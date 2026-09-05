<?php

namespace App\Http\Controllers;

use App\Models\{AppSetting, MediaFile, RoomCategory};

class HomeController extends Controller
{
    public function index()
    {
        $settings=array_replace(WebsiteController::defaults(),AppSetting::whereIn('key',WebsiteController::SETTING_KEYS)->pluck('value','key')->all());
        $categories=RoomCategory::with(['photos'=>fn($query)=>$query->metadata()])->withCount('rooms')->orderBy('name')->get();
        $hero=MediaFile::metadata()->where('kind','HERO')->latest()->first();
        return view('home',compact('settings','categories','hero'));
    }
}
