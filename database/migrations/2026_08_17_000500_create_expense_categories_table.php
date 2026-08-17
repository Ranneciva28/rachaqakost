<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('color', 7)->default('#DF8A42');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $defaults = [
            ['name'=>'Utilitas', 'color'=>'#5367A7'],
            ['name'=>'Maintenance', 'color'=>'#DF8A42', 'is_system'=>true],
            ['name'=>'Kebersihan', 'color'=>'#2F8977'],
            ['name'=>'Keamanan', 'color'=>'#7057A3'],
            ['name'=>'Internet', 'color'=>'#3B82B8'],
            ['name'=>'Lainnya', 'color'=>'#7A8582'],
        ];

        foreach ($defaults as $category) {
            DB::table('expense_categories')->insertOrIgnore($category + [
                'is_system'=>false,
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);
        }

        DB::table('expenses')->select('category')->distinct()->orderBy('category')->each(function ($expense) {
            DB::table('expense_categories')->insertOrIgnore([
                'name'=>$expense->category,
                'color'=>'#7A8582',
                'is_system'=>$expense->category === 'Maintenance',
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE expense_categories ENABLE ROW LEVEL SECURITY');
            DB::statement('REVOKE ALL ON TABLE expense_categories FROM anon, authenticated');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
