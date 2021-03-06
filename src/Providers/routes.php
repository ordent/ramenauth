<?php

use Illuminate\Http\Request;

Route::post('/api/auth/login', config('ramenauth.controller').'@ramenLogin');
Route::post('/api/v2/auth/login', config('ramenauth.controller').'@ramenLoginV2');
Route::post('/api/auth/check', config('ramenauth.controller').'@ramenCheck');
Route::post('/api/auth/register', config('ramenauth.controller').'@ramenRegister');
Route::get('/api/auth/verify/{identity}/verify_accounts/{code}/{type?}', config('ramenauth.controller').'@ramenVerifyFinish');
Route::post('/api/auth/phone', config('ramenauth.controller').'@ramenAskPhoneForLoginVerification');
Route::post('/api/auth/verify/{type}', config('ramenauth.controller').'@ramenVerifyStart');
Route::post('/api/auth/complete/{type}', config('ramenauth.controller').'@ramenVerifyComplete');
Route::post('/api/auth/forgot/{type}',config('ramenauth.controller').'@ramenForgotStart');
Route::post('/api/auth/forgot-check/{type}',config('ramenauth.controller').'@ramenForgotCheck');
Route::post('/api/auth/remember/{type}',config('ramenauth.controller').'@ramenForgotComplete');
Route::post('/api/auth/change/{type}', config('ramenauth.controller').'@ramenChangeIdentity');
Route::post('/api/auth/complete-change/{type}', config('ramenauth.controller').'@ramenCompleteChangeIdentity');