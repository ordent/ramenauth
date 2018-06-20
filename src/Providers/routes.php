<?php

use Illuminate\Http\Request;

Route::post('/api/auth/login', config('ramenauth.controller').'@ramenLogin');
Route::post('/api/auth/check', config('ramenauth.controller').'@ramenCheck');
Route::post('/api/auth/register', config('ramenauth.controller').'@ramenRegister');
Route::get('/api/auth/verify', config('ramenauth.controller').'@ramenVerifyStart');
Route::post('/api/auth/verify/{type}', config('ramenauth.controller').'@ramenVerify');
Route::get('/api/auth/verify/{identity}/verify_accounts/{code}/{type?}', config('ramenauth.controller').'@ramenVerifyFinish');