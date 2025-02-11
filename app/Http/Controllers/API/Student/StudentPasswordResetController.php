<?php

namespace App\Http\Controllers\API\Main;
use App\Http\Controllers\API\BaseController;
use App\Includes\Constant;
use App\Models\Student;
use App\Models\StudentPasswordReset;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentPasswordResetController extends BaseController
{
    public function requestResetPassword(Request $request){
        $phone_number = $request->input('phone_number');

        // checking phone number
        $student_exists = Student::where([
            ['phone_number', $phone_number],
            ['phone_verified_at', '<>', null]
        ])->exists();
        
        if (!$student_exists)
            return $this->sendResponse(Constant::$STUDENT_NOT_FOUND, null);
        
        $password_reset = StudentPasswordReset::where('phone_number', $phone_number)->first();
        if($password_reset)
            if($password_reset->created_at > Carbon::now()->subMinutes(Constant::$PASSWORD_RESET_REQUEST_LIMIT_MIN)->toDateTimeString())
                return $this->sendResponse(Constant::$PASSWORD_RESET_REQUEST_LIMIT_ERROR, null);
        else
            $password_reset->delete();
        
        $password_reset = new StudentPasswordReset();
        $password_reset->phone_number = $phone_number;
        $password_reset->token = bin2hex(random_bytes(16));
        $password_reset->save();

        // TODO Generate a link and send it via sms API

        return $this->sendResponse(Constant::$SUCCESS, null);
    }

   public function checkPasswordResetToken(Request $request){
        $token = $request->input('token');
        $password_reset = StudentPasswordReset::where('token', $token)->first();

        if(!$password_reset)
            return $this->sendResponse(Constant::$INVALID_TOKEN, null);
        else if($password_reset->created_at <= Carbon::now()->subMinutes(Constant::$PASSWORD_RESET_VALID_LIMIT_MIN)->toDateTimeString())
            return $this->sendResponse(Constant::$PASSWORD_RESET_VALID_LIMIT_ERROR, null);
        else
            return $this->sendResponse(Constant::$SUCCESS, null);
   }

   public function resetPassword(Request $request){
        $password_reset = StudentPasswordReset::where('token', $request->input('token'))->first();
        if(!$password_reset)
            return $this->sendResponse(Constant::$INVALID_TOKEN, null);

        $student = Student::where('phone_number', $password_reset->phone_number)->first();
        if(!$student)
            return $this->sendResponse(Constant::$STUDENT_NOT_FOUND, null);
        
        $student->password = Hash::make($request->input('password'));
        $student->save();

        return $this->sendResponse(Constant::$SUCCESS, null);
   }

}
