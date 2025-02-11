<?php

namespace App\Http\Controllers\API\Main;
use App\Http\Controllers\API\BaseController;
use App\Includes\Constant;
use App\Models\User;
use App\Models\UserPasswordReset;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserPasswordResetController extends BaseController
{
    public function requestResetPassword(Request $request){
        $phone_number = $request->input('phone_number');

        // checking phone number
        $user_exists = User::where([
            ['phone_number', $phone_number],
            ['phone_verified_at', '<>', null]
        ])->exists();
        
        if (!$user_exists)
            return $this->sendResponse(Constant::$USER_NOT_FOUND, null);
        
        $password_reset = UserPasswordReset::where('phone_number', $phone_number)->first();
        if($password_reset)
            if($password_reset->created_at > Carbon::now()->subMinutes(Constant::$PASSWORD_RESET_REQUEST_LIMIT_MIN)->toDateTimeString())
                return $this->sendResponse(Constant::$PASSWORD_RESET_REQUEST_LIMIT_ERROR, null);
        else
            $password_reset->delete();
        
        $password_reset = new UserPasswordReset();
        $password_reset->phone_number = $phone_number;
        $password_reset->token = bin2hex(random_bytes(16));
        $password_reset->save();

        // TODO Generate a link and send it via sms API

        return $this->sendResponse(Constant::$SUCCESS, null);
    }

   // TODO need a cron job to remove all left-alone password resets
   public function checkPasswordResetToken(Request $request){
        $token = $request->input('token');
        $password_reset = UserPasswordReset::where('token', $token)->first();

        if(!$password_reset)
            return $this->sendResponse(Constant::$INVALID_TOKEN, null);
        else if($password_reset->created_at <= Carbon::now()->subMinutes(Constant::$PASSWORD_RESET_VALID_LIMIT_MIN)->toDateTimeString())
            return $this->sendResponse(Constant::$PASSWORD_RESET_VALID_LIMIT_ERROR, null);
        else
            return $this->sendResponse(Constant::$SUCCESS, null);
   }

   public function resetPassword(Request $request){
        $password_reset = UserPasswordReset::where('token', $request->input('token'))->first();
        if(!$password_reset)
            return $this->sendResponse(Constant::$INVALID_TOKEN, null);

        $user = User::where('phone_number', $password_reset->phone_number)->first();
        if(!$user)
            return $this->sendResponse(Constant::$USER_NOT_FOUND, null);
        
        $user->password = Hash::make($request->input('password'));
        $user->save();

        return $this->sendResponse(Constant::$SUCCESS, null);
   }

}
