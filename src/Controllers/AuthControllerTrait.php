<?php

namespace Ordent\RamenAuth\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
/**
 * AuthControllerTrait trait
 * Main Trait to use in your account class
 */
trait AuthControllerTrait{
    /**
     * ramenLogin
     *
     * @param Request $request
     * @return JsonObject
     * @throws Exception if account with email or the phone number not found.
     */
    public function ramenLogin(Request $request){
        $rules = config('ramenauth.login_rules');
        $manager = app(config('ramenauth.manager'));
        list($result, $meta, $post) = $manager->ramenLogin($request, $rules, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, $post);
    }
    /**
     * ramenCheck function
     *
     * @param Request $request
     * @return JsonObject
     * @throws Exception If there is any failed validation or The Account with questionend username, email or phone is alrady exist.
     */
    public function ramenCheck(Request $request){
        $rules = config('ramenauth.check_rules');
        $manager = app(config('ramenauth.manager'));
        list($result, $meta, $status) = $manager->ramenCheck($request, $rules, $this->model);
        if($status == 422){
            return response()->errorValidation($result, 'Account with this identity is already exist');
        }
        return $this->processor->wrapModel($result, null, null, $meta, null, null);
    }

    public function ramenAskPhoneForLoginVerification(Request $request){
        $manager = app(config('ramenauth.manager'));
        $rules = config('ramenauth.phone_login_rules', []);
        list($status, $message) = $manager->ramenAskPhoneForLoginVerification($request, $rules, $this->model);
        if($status != 200){
            return abort($status, $message);
        }
        $result = new \StdClass;
        $result->data = null;
        $result->meta = new \StdClass;
        $result->meta->status_code = $status;
        $result->meta->message = $message;
        return response()->successResponse($result);
    }

    public function ramenRefresh(Request $requests){
        // the token is valid and we have found the user via the sub claim
        $rules = config('ramenauth.refresh_rules');
        $manager = app(config('ramenauth.manager'));        
        list($result, $meta) = $manager->ramenRefresh($request, $rules);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function ramenRegister(Request $request){
        $request = $this->preRamenRegister($request);
        $rules = $this->resolveRules();
        $manager = app(config('ramenauth.manager'));
        list($result, $meta) = $manager->ramenRegister($request, $rules, $this->model);

        if(!is_null($result)){
            list($result, $meta) = $this->postRamenRegister($result, $meta, $manager);
        }
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function preRamenRegister($request){
        return $request;
    }

    public function postRamenRegister($result, $meta, $manager){
        return [$result, $meta];
    }

    private function resolveRules(){
        if(!property_exists($this, 'rules') && !is_null($this->model)){
            if(method_exists($this->model, 'getRules')){
                return $this->model->getRules('store');                
            }
        }else{
            return $this->rules;
        }
        return config('ramenauth.register_rules');
    }

    public function ramenAssignRoleToUser(Request $request, $id){
        $manager = app(config('ramenauth.manager'));
        $rules = config('ramenauth.roles_assign_rules');
        list($result, $meta) = $manager ->ramenAssignRoleToUser($request, $id, $rules, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function ramenRemoveRoleFromUser(Request $request, $id){
        $manager = app(config('ramenauth.manager'));
        $rules = config('ramenauth.roles_remove_rules');
        list($result, $meta) = $manager ->ramenRemoveRoleFromUser($request, $id, $rules, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function ramenAssignPermissionToRole(Request $request, $id){
        $manager = app(config('ramenauth.manager'));
        $rules = config('ramenauth.permissions_assign_rules');
        list($result, $meta) = $manager ->ramenAssignPermissionToRole($request, $id, $rules, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function ramenRemovePermissionFromRole(Request $request, $id){
        $manager = app(config('ramenauth.manager'));
        $rules = config('ramenauth.permissions_remove_rules');
        list($result, $meta) = $manager ->ramenRemovePermissionFromRole($request, $id, $rules, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function ramenForget(Request $request, $type = 'email'){
        
    }

    public function ramenForgotStart(Request $request, $type = 'email'){
        $rules = array_merge([], ['identity' => 'required']);
        $validator = \Validator::make($request->all(), $rules);
        if($validator->fails()){
            abort(422, json_encode($validator->errors()->getMessages()));
        }
        $identity = $request->input('identity');
        $model = $this->model->where($type, $identity)->firstOrFail();
        $manager = app(config('ramenauth.manager'));
        list($result, $meta) = $manager->ramenForgot($type, $model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }
    
    public function ramenForgotCheck(Request $request, $type = 'email'){
        $rules = array_merge([], ['identity'=>'required','answer'=>'required']);
        $validator = \Validator::make($request->all(), $rules);
        if($validator->fails()){
            abort(422, json_encode($validator->errors()->getMessages()));
        }
        $manager = app(config('ramenauth.manager'));
        list($result, $meta) = $manager->ramenCheckByIdentity($type, $request, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function ramenForgotComplete(Request $request, $type = 'email'){
        $rules = array_merge([], ['identity'=>'required','answer'=>'required','password' => 'required|confirmed']);
        $validator = \Validator::make($request->all(), $rules);
        if($validator->fails()){
            abort(422, json_encode($validator->errors()->getMessages()));
        }
        $manager = app(config('ramenauth.manager'));
        list($result, $meta) = $manager->ramenCompleteForgotten($type, $request, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function ramenVerifyStart(Request $request, $type){
        $validator = \Validator::make($request->all(), [
            'identity' => 'required'
        ]);
        if($validator->fails()){
            abort(422, json_encode($validator->errors()->getMessages()));
        }
        $identity = $request->input('identity');
        $model = $this->model->where($type, $identity)->firstOrFail();
        $manager = app(config('ramenauth.manager'));
        if(config('ramenauth.verification')){
            list($result, $meta) = $manager->ramenAskVerification($type, $model);
        }
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
        // $title = "Start Verification";
        // return view('ramenauth::verify-start', compact('title'));
    }

    public function ramenVerifyComplete(Request $request, $type = 'email'){
        $rules = config('ramenauth.verifications_rules');
        $validator = \Validator::make($request->all(), [
            'identity' => 'required',
            'answer' => 'required'
        ]);
        if($validator->fails()){
            abort(422, json_encode($validator->errors()->getMessages()));
        }
        $manager = app(config('ramenauth.manager'));

        list($result, $meta, $post) = $manager->ramenCompleteVerification($type, $request, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, $post);
    }

    public function ramenVerifyFinish(Request $request, $identity, $code, $type = 'json'){
        $manager = app(config('ramenauth.manager'));
        
        if($type == 'json'){
            list($result, $meta) = $manager->ramenVerifyFinish($request, $identity, $code, $this->model);
            return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
        }else{
            try{
                list($result, $meta) = $manager->ramenVerifyFinish($request, $identity, $code, $this->model);
            }catch(\Exception $e){
                return view('ramenauth::verify-failed', ['message'=>$e->getMessage(), 'title' => 'RamenAuth | Verification failed.']);
            }
            return view('ramenauth::verify-finish');
        }
    }

    public function ramenChangeIdentity(Request $request, $type = 'email'){
        $validator = \Validator::make($request->all(), [
            'old_identity' => 'required',
            'identity' => 'required|unique:'.config('ramenauth.users_table','users').','.$type
        ]);
        if($validator->fails()){
            abort(422, json_encode($validator->errors()->getMessages()));
        }
        $manager = app(config('ramenauth.manager'));
        list($result, $meta) = $manager->ramenChangeIdentity($type, $request, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function ramenCompleteChangeIdentity(Request $request, $type = 'email'){
        $validator = \Validator::make($request->all(), [
            'old_identity' => 'required',
            'answer' => 'required',
            'identity' => 'required|unique:'.config('ramenauth.users_table','users').','.$type
        ]);

        if($validator->fails()){
            abort(422, json_encode($validator->errors()->getMessages()));
        }
        $manager = app(config('ramenauth.manager'));
        list($result, $meta) = $manager->ramenCompleteChangeIdentity($type, $request, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }
}