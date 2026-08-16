<?php
return ['default'=>env('CACHE_STORE','database'),'stores'=>['array'=>['driver'=>'array'],'file'=>['driver'=>'file','path'=>storage_path('framework/cache/data')],'database'=>['driver'=>'database','connection'=>env('DB_CACHE_CONNECTION'),'table'=>'cache','lock_table'=>'cache_locks']],'prefix'=>'rachaqakost-cache-'];
