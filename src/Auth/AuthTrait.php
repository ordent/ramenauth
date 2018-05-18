<?php

namespace Ordent\RamenAuth\Auth;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Validator;
use Illuminate\Validation\ValidationException;
trait AuthTrait{
    public function ramenLogin(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'requiredWithoutAll:phone,username',
            'username' => 'requiredWithoutAll:phone,email',
            'phone' => 'requiredWithoutAll:email,username',
            'password' => 'required'
        ]);

        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $type = head(array_keys(array_except($request->all(), 'password')));
        $roles = $request->input('roles', false);
        return $this->typeLogin($request[$type], $request->password, $type, $roles);

    }

    public function ramenCheck(Request $request){
        $type = $request->type;
        $identity = $request[$type];
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'username' => 'requiredWithoutAll:phone,email',
            'email' => 'requiredWithoutAll:phone,username',
            'phone' => 'requiredWithoutAll:email,username',
        ]);

        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        try{
            $model = $this->model->where($type, $identity)->firstOrFail();
        }catch(ModelNotFoundException $e){
            return response()->error(404, 'We can\'t found any account with this credential');
        }
        if($model){
            return response()->successResponse(
                ['data' => [
                    'type' => $type,
                    'value' => $identity,
                    'status' => true
                ], 
                 'meta' => [
                     'status_code' => 200
                 ]
                ]
            );
        }

    }

    public function ramenRefresh(Request $requests){
        // the token is valid and we have found the user via the sub claim
        $new_token = JWTAuth::refresh($requests->token);
        return response()->successResponse([
            'old_token' => $requests->token,
            'new_token' => $new_token
        ]);
    }


    private function typeLogin($identity, $password, $type = 'email', $roles = false){
        try{
            $check = \Auth::attempt([$type => $identity, 'password'=>$password]);
        }catch(ModelNotFoundException $e){
            return $e;
        }
        if($check){
            return $this->authenticateFromModel($this->model->where($type, $identity)->get()->first(), $roles);
        }
    }

    private function authenticateFromModel($user, $roles = false){
        if($roles){
            $user->roles;
        }
        $meta = new \StdClass;
        $meta->status_code = 200;
        $meta->message = 'Login is successful';
        return $this->wrapResponse($user, \JWTAuth::fromUser($user), $meta);
    }

    private function authenticate($credentials){
        try{
            if(!$token = \JWTAuth::attempt($credentials)){
                return response()->error(401, 'We can not found any users with that credentials');
            }
        }catch(JWTException $e){
            return response()->error(500,'There\'s something wrong, token can\'t be created. Check your configuration');
        }

        return $this->wrapResponse(\JWTAuth::toUser($token), $token);
    }

    private function wrapResponse($model, $token, $meta = null){
        $result = new \StdClass;
        $result->data = new \StdClass;
        $result->meta = new \StdClass;
        if(!is_null($meta)){
            $result->meta = $meta;
        }
        $result->data->users = $model;
        $result->data->token = $token;
        return response()->successResponse($result);
    }
}