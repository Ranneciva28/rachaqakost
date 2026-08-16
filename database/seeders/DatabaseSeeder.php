<?php
namespace Database\Seeders;use App\Models\User;use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder { public function run():void{$password=env('INITIAL_OWNER_PASSWORD');if(blank($password))throw new \RuntimeException('INITIAL_OWNER_PASSWORD wajib diisi; tidak ada password default demi keamanan.');User::updateOrCreate(['email'=>env('INITIAL_OWNER_EMAIL','owner@rachaqakost.id')],['name'=>env('INITIAL_OWNER_NAME','RachaqaKost Owner'),'password'=>$password,'role'=>'OWNER']);} }
