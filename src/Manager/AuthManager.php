<?php
namespace Ordent\RamenAuth\Manager;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Validator;
use Ordent\RamenAuth\Model\RamenVerification;

class AuthManager
{
    protected $phone;
    protected $model;
    public function __construct($phone = null, RamenVerification $model){
        $this->phone = ($phone == null) ? app('Nexmo\Client') : $phone;
        $this->model = $model;
    }

    public function ramenLogin(Request $request, $rules = [], $model)
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $type = head(array_keys(array_except($request->all(), 'password')));
        $roles = $request->input('roles', false);
        $password = $request->input('password');
        $credentials = $request->only($type, 'password');

        try {
            $check = \Auth::attempt($credentials);
        } catch (ModelNotFoundException $e) {
            return $e;
        }

        if ($check) {
            return $this->authenticateFromModel($model->where($type, $request[$type])->get()->first(), $roles);
        } else {
            abort(401, 'Login failed, wrong username or password');
        }
    }

    private function authenticateFromModel($user, $roles = false)
    {
        if ($roles) {
            $user->roles;
        }
        $meta = new \StdClass;
        $meta->status_code = 200;
        $meta->message = 'Login is successful';
        $post = function ($data) use ($user) {
            $users = $data['data'];
            $data['data'] = null;
            $data['data']['users'] = $users;
            $data['data']['token'] = \JWTAuth::fromUser($user);
            return $data;
        };
        return [$user, $meta, $post];
    }

    public function ramenCheck(Request $request, $rules = [], $model)
    {
        $type = $request->type;
        $identity = $request[$type];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        try {
            $model = $model->where($type, $identity)->first();
        } catch (ModelNotFoundException $e) {
            return response()->error(404, 'We can\'t found any account with this credential');
        }
        if (!is_null($model)) {
            $result = ['type' => $type, 'value' => $identity, 'status' => false];
            $meta = ['status_code' => 422, 'message' => 'Account with this identity already exists.'];
            $status = 422;
        } else {
            $result = ['type' => $type, 'value' => $identity, 'status' => true];
            $meta = ['status_code' => 200, 'message' => 'You are allowed to use this identity as an account.'];
            $status = 200;
        }
        return [$result, $meta, $status];
    }

    public function ramenRefresh(Request $request, $rules)
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $new_token = \JWTAuth::refresh($requests->token);
        $result = ['token' => $new_token];
        $meta = ['status_code' => 200, 'message' => 'Token refresh successful.'];
        return [$result, $meta];
    }

    public function ramenRegister(Request $request, $rules = [], $model)
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $data = array_only($request->toArray(), $model->getFillable());
        $result = $model->create($data)->refresh();
        $verification = $this->postRamenRegister($result);
        $meta = ['status_code' => 200, 'message' => 'You have been succesfully registered.'];
        return [$result, $meta];
    }

    public function postRamenRegister($result){
        $verification = null;
        if(config('ramenauth.verification')){
            if(array_search('status', $result->getFillable() !== FALSE)){
                if($result->status < 2){
                    if(!is_null($result->phone)){
                        $verification = $this->ramenAskVerification('phone', $result);
                    }
                }
            }
        }
        return $verification;;
    }

    public function ramenAssignRoleToUser(Request $request, $id, $rules = [], $model)
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $model = $model->findOrFail($id);
        $role = array_wrap($request->input('role_name', $request->input('role_id')));

        $role_id = [];
        $role_name = [];

        foreach ($role as $r) {
            if (is_numeric($r)) {
                array_push($role_id, $r);
            } else {
                array_push($role_name, $r);
            }
        }
        $role_id = Role::whereIn('id', $role_id)->get();
        $role_name = Role::whereIn('', $role_name)->get();

        $role = $role_id->merge($role_name);

        if ($role->count() <= 0) {
            abort(404, 'Role not found');
        }

        $result = $model->fresh();
        foreach ($role as $key => $value) {
            if (!$result->hasRole($value)) {
                $result = $result->assignRole($value);
            }
        }
        $result->fresh();
        $result->roles;
        $meta = ['status_code' => 200, 'message' => 'You have been succesfully add Role to User.'];
        return [$result, $meta];
    }

    public function ramenRemoveRoleFromUser(Request $request, $id, $rules = [], $model)
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $model = $model->findOrFail($id);
        $role = array_wrap($request->input('role_name', $request->input('role_id')));

        $role_id = [];
        $role_name = [];

        foreach ($role as $r) {
            if (is_numeric($r)) {
                array_push($role_id, $r);
            } else {
                array_push($role_name, $r);
            }
        }
        $role_id = Role::whereIn('id', $role_id)->get();
        $role_name = Role::whereIn('', $role_name)->get();

        $role = $role_id->merge($role_name);

        if ($role->count() <= 0) {
            abort(404, 'Role not found');
        }

        $result = $model->fresh();
        foreach ($role as $key => $value) {
            if (!$result->hasRole($value)) {
                $result = $result->removeRole($value);
            }
        }
        $result->fresh();
        $result->roles;
        $meta = ['status_code' => 200, 'message' => 'You have been succesfully remove Role to User.'];
        return [$result, $meta];

    }

    public function ramenAssignPermissionToRole(Request $request, $id, $rules = [])
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $role = Role::findOrFail($id);
        $permission = $request->input('permission_name', $request->input('permission_id'));
        if (!is_array($permission)) {
            $permission = [$permission];
        }
        if (is_integer($permission)) {
            $permission = Permission::whereIn('id', $id)->get();
        } else {
            $permission = Permission::whereIn('name', $permission)->get();
            if ($permission->count() < 1) {
                $temp = $request->input('permission_name');
                if (!is_array($temp)) {
                    $temp = [$temp];
                }
                $permission = [];
                foreach ($temp as $key => $value) {
                    array_push($permission, Permission::create(['name' => $value]));
                }
            }
        }

        $permission = $role->permissions->concat($permission);
        $role->syncPermissions($permission);
        $result = $role->fresh();
        $result->permissions;
        $meta = $meta = ['status_code' => 200, 'message' => 'You have been succesfully add Permission to Roles.'];
        return [$result, $meta];
    }

    public function ramenRemovePermissionFromRole(Request $request, $id, $rules = [])
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $role = Role::findOrFail($id);
        $permission = $request->input('permission_name', $request->input('permission_id'));
        if (!is_array($permission)) {
            $permission = [$permission];
        }
        if (is_integer($permission)) {
            $permission = \Permission::whereIn('id', $id)->get();
        }
        $permissions = array_diff(array_pluck($role->permissions->toArray(), 'name'), $permission);
        $role->syncPermissions($permissions);
        $result = $role->fresh();
        $result->permissions;
        $meta = $meta = ['status_code' => 200, 'message' => 'You have been succesfully remove Permission from Role'];
        return [$result, $meta];
    }

    // verify
    public function ramenAskVerification($type, $model)
    {
        switch ($type) {
            case 'phone':
                $this->ramenAskVerificationByPhone($model);
                break;
            
            default:
                # code... =
                break;
        }
    }
    

    protected function ramenAskVerificationByPhone($model){
        $verification = $this->phone->verify()->start([
            'number' => $this->resolvePhoneNumber($model->phone),
            'brand' => 'Phone Verification'
        ]);

        $this->model->user_id = $model->id;
        $this->model->code = $verification->getRequestId();
        $this->model->save();

        return $this->model;
    }

    protected function resolvePhoneNumber($number){

        if(substr($number, 0, 1) == '0'){
            $number = "62".substr($number,1);
        }

        if(substr($number, 0, 1) != "+"){
            $number = "+".$number;
        }

        return $number;
    }

    public function ramenCompleteVerification($type, Request $request, $model){
        switch($type){
            case 'phone':
                return $this->ramenCompleteVerificationByPhone($request, $model);
        }
    }

    public function ramenCompleteVerificationByPhone($request, $model){
        $model = $model->where('phone', $request->input('phone'))->firstOrFail();
        $verification = $this->model->where('user_id', $model->id)->firstOrFail();
        $answer = $this->phone->verify()->check($verification->code, $request->input('answer'));
        if($answer->getStatus() == 0){
            $verification->verified_at = date('Y-m-d H:i:s');
            $verification->response = $request->input('answer');
            if(array_search('status', $model->getFillable()) !== false){
                $model->status = 2;
            }
        }
        $verification->save();
        $model->save();
        $model->fresh();
        return [$model, ['verification' => 'success', 'status_code' => 200]];
    }

    // forgot password
    public function ramenForgot(Request $request, $id)
    {

    }
}
