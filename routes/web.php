<?php
use App\Http\Controllers\{AuthController,FinanceController,KostController,UserController};use Illuminate\Support\Facades\Route;
Route::middleware('guest')->group(function(){Route::get('/login',[AuthController::class,'create'])->name('login');Route::post('/login',[AuthController::class,'store'])->middleware('throttle:6,1');});
Route::middleware('auth')->group(function(){
 Route::get('/',[KostController::class,'index'])->name('dashboard');Route::post('/logout',[AuthController::class,'destroy'])->name('logout');
 Route::get('/keuangan',[FinanceController::class,'index'])->name('finance');
 Route::post('/categories',[KostController::class,'storeCategory'])->name('categories.store');Route::patch('/categories/{category}',[KostController::class,'updateCategory'])->name('categories.update');Route::delete('/categories/{category}',[KostController::class,'destroyCategory'])->name('categories.destroy');
 Route::post('/rooms',[KostController::class,'storeRoom'])->name('rooms.store');Route::patch('/rooms/{room}',[KostController::class,'updateRoom'])->name('rooms.update');
 Route::post('/tenants',[KostController::class,'tenantIn'])->name('tenants.store');Route::patch('/tenants/{tenant}/checkout',[KostController::class,'tenantOut'])->name('tenants.checkout');
 Route::post('/payments',[KostController::class,'payment'])->name('payments.store');Route::post('/expenses',[KostController::class,'expense'])->name('expenses.store');
 Route::post('/expense-categories',[KostController::class,'storeExpenseCategory'])->name('expense-categories.store');Route::patch('/expense-categories/{expenseCategory}',[KostController::class,'updateExpenseCategory'])->name('expense-categories.update');Route::delete('/expense-categories/{expenseCategory}',[KostController::class,'destroyExpenseCategory'])->name('expense-categories.destroy');
 Route::patch('/settings/whatsapp-template',[KostController::class,'updateWhatsAppTemplate'])->name('settings.whatsapp-template');
 Route::post('/maintenances',[KostController::class,'maintenance'])->name('maintenances.store');Route::patch('/maintenances/{maintenance}/done',[KostController::class,'maintenanceDone'])->name('maintenances.done');
 Route::post('/users',[UserController::class,'store'])->name('users.store');Route::patch('/users/{user}',[UserController::class,'update'])->name('users.update');
});
