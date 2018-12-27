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

    /** 
     * 
     * method for requesting phone verification
     * 
     * @param Request
     * @return JsonObject
     * @throws Exception
    */
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

    /**
     * 
     * Method for refreshing user token.
     * 
     * @param Request token which will be renewed
     * @return JsonObject
     * @throws Exception
     */
    public function ramenRefresh(Request $request){
        // the token is valid and we have found the user via the sub claim
        $rules = config('ramenauth.refresh_rules');
        $manager = app(config('ramenauth.manager'));        
        list($result, $meta) = $manager->ramenRefresh($request, $rules);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    /**
     * 
     * function for registering new user
     * 
     * @param Request json request containing registered data
     * @return  JsonObject
     * @throws Exception if there is missing data or failed in storing data to db
     */
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

    /**
     * 
     * method for determine which rules to be used.
     * 
     * @param null
     * @return ArrayObject array of defined rules
     * @throws Exception 
     */
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

    /**
     * 
     * Method for assigning a role to a user
     * 
     * @param Request
     * @param id id of intended user
     * @return JsonObject
     * @throws Exception
     */
    public function ramenAssignRoleToUser(Request $request, $id){
        $manager = app(config('ramenauth.manager'));
        $rules = config('ramenauth.roles_assign_rules');
        list($result, $meta) = $manager ->ramenAssignRoleToUser($request, $id, $rules, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    /**
     * 
     * Method for removing role from a user
     * 
     * @param Request
     * @param id id of intended user
     * @return JsonObject
     * @throws Exception
     */
    public function ramenRemoveRoleFromUser(Request $request, $id){
        $manager = app(config('ramenauth.manager'));
        $rules = config('ramenauth.roles_remove_rules');
        list($result, $meta) = $manager ->ramenRemoveRoleFromUser($request, $id, $rules, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    /**
     * 
     * Method for Assigning a permission to a role.
     * 
     * @param Request
     * @param id id of intended role
     * @return JsonObject
     * @throws Exception
     */
    public function ramenAssignPermissionToRole(Request $request, $id){
        $manager = app(config('ramenauth.manager'));
        $rules = config('ramenauth.permissions_assign_rules');
        list($result, $meta) = $manager ->ramenAssignPermissionToRole($request, $id, $rules, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    /**
     * 
     * Method for removing a permission from a role
     * 
     * @param Request
     * @param id id of intended role
     * @return JsonObject
     * @throws Exception
     */
    public function ramenRemovePermissionFromRole(Request $request, $id){
        $manager = app(config('ramenauth.manager'));
        $rules = config('ramenauth.permissions_remove_rules');
        list($result, $meta) = $manager ->ramenRemovePermissionFromRole($request, $id, $rules, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function ramenForget(Request $request, $type = 'email'){
        
    }

    /**
     * 
     * Method for initiating a forgot password process. This method will send a mail 
     * containing code which will be checked in next step.
     * 
     * @param Request
     * @param type type of related user recovery method. Either the code will be sent by email or phone
     * @return JsonObject
     * @throws Exception
     */
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
    
    /**
     * 
     * Method for verifying the recovery code which sent by email or phone, with the recovery code
     * which is inserted by user.
     * 
     * @param Request 
     * @param type type of method which used to send recovery code
     * @return JsonObject
     * @throws Exception
     */
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

    /**
     * 
     * Method for completing the forgot password process. This method checks user answer for the security question
     * validate users new password.
     * 
     * @param Request
     * @param type type of method which used to send recovery code
     * @return JsonObject
     * @throws Exception
     */
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

    /**
     * 
     * Method to start verification process by sending verification code which sent based on type
     * 
     * @param Request
     * @param type type of method which used to send verification code
     * @return JsonObject
     * @throws Exception
     */
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

    /**
     * 
     * 
     */
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

    /**
     * 
     * Method to change user identity (email, phone) based on selected type, email or phone.
     * 
     * @param Request
     * @param type type of selected identity type
     * @return JsonObject
     * @throws Exception
     */
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

    /**
     * 
     * Method for finalizing the changes in  user identity, based on selected type
     * 
     * @param Request
     * @param type String type of selected identity type.
     */
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