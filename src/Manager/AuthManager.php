<?php
namespace Ordent\RamenAuth\Manager;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Schema;
use Ordent\RamenAuth\Notifications\RestNotification;
class AuthManager{
    public function ramenLogin(Request $request, $rules = [], $model){
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $type = head(array_intersect(array_keys(array_except($request->all(), 'password')), ['email', 'phone', 'username']));
        $roles = $request->input('roles', false);
        $password = $request->input('password');
        try{
            $check = \Auth::attempt([$type => $request[$type], 'password' => $password]);
        }catch(ModelNotFoundException $e){
            return $e;
        }

        if($check){
            return $this->authenticateFromModel($model->where($type, $request[$type])->get()->first(), $roles);
        }else{
            abort(401, 'Login failed, wrong username or password');
        }
    }

    private function authenticateFromModel($user, $roles = false){
        if($roles){
            $user->roles;
        }
        $meta = new \StdClass;
        $meta->status_code = 200;
        $meta->message = 'Login is successful';
        $post = function($data) use ($user){
            $users = $data['data'];
            $data['data'] = null;
            $data['data']['users'] = $users;
            $data['data']['token'] = \JWTAuth::fromUser($user);
            return $data;
        };
        return [$user, $meta, $post];
    }

    public function ramenCheck(Request $request, $rules = [], $model){
        $type = $request->type;
        $identity = $request[$type];
        $validator = Validator::make($request->all(), $rules);

        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        try{
            $model = $model->where($type, $identity)->first();
        }catch(ModelNotFoundException $e){
            return response()->error(404, 'We can\'t found any account with this credential');
        }
        if(!is_null($model)){
            $result = [ 'type' => $type, 'value' => $identity, 'status' => false ];
            $meta = [ 'status_code' => 422, 'message' => 'Account with this identity already exists.' ];
            $status = 422;
        }else{
            $result = [ 'type' => $type, 'value' => $identity, 'status' => true ];
            $meta = [ 'status_code' => 200, 'message' => 'You are allowed to use this identity as an account.' ];
            $status = 200;
        }
        return [$result, $meta, $status];        
    }

    public function ramenRefresh(Request $request, $rules){
        $validator = Validator::make($request->all(), $rules);

        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $new_token = \JWTAuth::refresh($requests->token);
        $result = [ 'token' => $new_token ];
        $meta = [ 'status_code' => 200, 'message' => 'Token refresh successful.' ];
        return [$result, $meta];
    }

    public function ramenRegister(Request $request, $rules = [], $model){
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $data = array_only($request->toArray(), $model->getFillable());
        $result = $model->create($data)->refresh();
        $meta = [ 'status_code' => 200, 'message' => 'You have been succesfully registered.' ];
        return [$result, $meta];
    }

    public function ramenAssignRoleToUser(Request $request, $id, $rules = [], $model){
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $model = $model->findOrFail($id);
        $role = $request->input('role_name', $request->input('role_id'));
        if(!is_array($role)){
            $role = [$role];
        }
        if(is_integer($role)){
            $role = Role::whereIn('id', $id)->get();
            abort(404, 'Role not found');
        }else{
            $role = Role::whereIn('name', $role)->get();
            if($role->count()<1){
                $temp = $request->input('role_name');
                if(!is_array($temp)){
                    $temp = [$temp];
                }
                $role = [];
                foreach ($temp as $key => $value) {
                    array_push($role, Role::create(['name'=>$value]));
                }
            }
        }
        $result = $model->fresh();
        foreach ($role as $key => $value) {
            if(!$model->hasRole($value)){
                $result = $result->assignRole($role);
            }
        }
        $result->fresh();
        $result->roles;
        $meta = [ 'status_code' => 200, 'message' => 'You have been succesfully add Role to User.' ];
        return [$result, $meta];
    }

    public function ramenRemoveRoleFromUser(Request $request, $id, $rules = [], $model){
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $model = $model->findOrFail($id);
        $role = $request->input('role_name', $request->input('role_id'));
        if(!is_array($role)){
            $role = [$role];
        }
        if(is_integer($role)){
            $role = Role::whereIn('id', $id)->get();
        }
        foreach ($role as $key => $value) {
            $model->removeRole($value);
        }
        $result = $model->fresh();
        $result->roles;

        $meta = $meta = [ 'status_code' => 200, 'message' => 'You have been succesfully remove Role from User.' ];
        return [$result, $meta];
    }

    public function ramenAssignPermissionToRole(Request $request, $id, $rules = []){
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $role = Role::findOrFail($id);
        $permission = $request->input('permission_name', $request->input('permission_id'));
        if(!is_array($permission)){
            $permission = [$permission];
        }
        if(is_integer($permission)){
            $permission = Permission::whereIn('id', $id)->get();
        }else{
            $permission = Permission::whereIn('name', $permission)->get();
            if($permission->count()<1){
                $temp = $request->input('permission_name');
                if(!is_array($temp)){
                    $temp = [$temp];
                }
                $permission = [];
                foreach ($temp as $key => $value) {
                    array_push($permission, Permission::create(['name'=>$value]));
                }
            }
        }

        $permission = $role->permissions->concat($permission);
        $role->syncPermissions($permission);
        $result = $role->fresh();
        $result->permissions;
        $meta = $meta = [ 'status_code' => 200, 'message' => 'You have been succesfully add Permission to Roles.' ];
        return [$result, $meta];
    }

    public function ramenRemovePermissionFromRole(Request $request, $id, $rules = []){
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $role = Role::findOrFail($id);
        $permission = $request->input('permission_name', $request->input('permission_id'));
        if(!is_array($permission)){
            $permission = [$permission];
        }
        if(is_integer($permission)){
            $permission = \Permission::whereIn('id', $id)->get();
        }
        $permissions = array_diff(array_pluck($role->permissions->toArray(), 'name'), $permission);
        $role->syncPermissions($permissions);
        $result = $role->fresh();
        $result->permissions;
        $meta = $meta = [ 'status_code' => 200, 'message' => 'You have been succesfully remove Permission from Role' ];
        return [$result, $meta];
    }

    // verify
    public function ramenVerify(Request $request, $rules = [], $type = 'email',$model){
        $validator = Validator::make($request->all(), $rules);

        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        if(!Schema::hasTable('ramen_verifications')){
            abort(400, 'Can\'t continue to verify because the verification table is not exists.');
        }
        $identity = $request->input('identity');
        $users = $model->where($type, $identity)->first();
        if(is_null($users)){
            abort(404, "Account with that identity is not found.");
        }
        $query_string = 'SELECT * FROM ramen_verifications WHERE user_id = '.$users->id;
        $code = "";
        $query = \DB::table('ramen_verifications')->where('user_id', $users->id)->get();
        if(is_null($query) || count($query) < 1){
            $query_insert = 'INSERT INTO ramen_verifications (user_id, code) VALUES (?,?)';
            $code = strtoupper(str_random(6));
            $query = \DB::insert($query_insert, [$users->id, $code, null, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
            if($query){
                $query = \DB::select($query_string);
            }
        }else{
            if(is_null(array_first($query)->verified_at)){
                $code = array_first($query)->code;
            }else{
                abort(400, 'This user has already been verified');
            }
        }
        $meta = [];
        
        if(count($query)>0){
            $value = [
                'users' => $users,
                'code' => $code
            ];
            $users->notify(new RestNotification($type, 'verify', $value));
            $meta['message'] = 'Verification is now being sent, please wait a couple of minutes.';
            $meta['status_code'] = 200;
            return [$value, $meta];    
        }else{
            abort(400, 'Verification process failed, please wait a couple of minutes to try again.');
        }
        
        
    }

    public function ramenVerifyFinishHTML(Request $request, $type, $identity, $code, $model){
        $result = $this->ramenVerifyFinish($request, $type, $identity, $code, $model);
    }

    public function ramenVerifyFinishJSON(){

    }

    public function ramenVerifyFinish(Request $request, $identity, $code, $model){
        $type = 'email';
        $identity = base64_decode($identity);
        $rules = [
            'identity' => 'email'
        ];

        $validator = Validator::make(['identity'=>$identity], $rules);

        if($validator->fails()){
            $type = 'phone';
        }

        if(!Schema::hasTable('ramen_verifications')){
            abort(400, 'Can\'t continue to verify because the verification table is not exists.');
        }
        $users = $model->where($type, $identity)->first();
        if(is_null($users)){
            abort(404, "Account with that identity is not found.");
        }

        $query_string = 'SELECT * FROM ramen_verifications WHERE user_id = '.$users->id.' AND verified_at = NULL';
        $query = \DB::table('ramen_verifications')->where('user_id', $users->id)->whereNull('verified_at')->get();
        if(is_null($query) || count($query) < 1){
            $query = \DB::table('ramen_verifications')->where('user_id', $users->id)->get();
            if(is_null($query) || count($query) < 1){
                abort(400, 'We can\'t find any account with that identity requesting for verification');
            }else{
                abort(400, 'It seems like the account is already verified.');
            }
        }else{
            $accounts = array_first($query);
            if($accounts->code == $code){
                $query = \DB::table('ramen_verifications')->where('user_id', $users->id)->whereNull('verified_at')->update(['verified_at' => date('Y-m-d H:i:s')]);
                if($query){
                    $meta['message'] = 'Verification now completed, you can now try to login';
                    $meta['status_code'] = 200;
                    return [$users, $meta]; 
                }
            }else{
                abort(400, 'We can\'t seem to verify the accounts, it looks like the code is invalid');
            }
        }
    }
    // forgot password
    public function ramenForgot(Request $request, $id){
        
    }
}