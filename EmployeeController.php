<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\MasterTable;
use App\PayrollRun;

use Validator;
use PDF;
use Session;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    
    // payslip viewing
    public function payslip(Request $request){
        
        // check employee number
        $id = $request->session()->get('employee_number') ? $request->session()->get('employee_number') : $request->get('id');
        
        // get payroll details for the selected employee
        $get_payroll_runs = MasterTable::where(['employee_number' => $id])->with(['getPayrollRun'])->get();
        
        return view('pages.users.employee.view_payslip')->with(['payslip_details'=>[],'payroll_runs' => $get_payroll_runs]);
    }
    
    public function viewPayslip(Request $request,$id){
        // relationship
        $relationships = [
            'getOtherIncome',
            'getOtherAllowance',
            'getDeminimis',
            'getOTPay',
            'getGovernmentPayments',
            'getOtherDeductions',
            'getUser',
            'getUser.getCompany',
            'getPayrollRun'
        ];
        
        // get data of the employee with specific upload id
        $payslip_dets = MasterTable::where(['employee_number' => $id, 'upload_id' => $request->get('upload_id')])->with($relationships)->first();
        
        
        // sum all earnings
        $total_earnings = $payslip_dets['total_other_income'] + $payslip_dets['total_deminimis'] + $payslip_dets['total_other_allowances'] + $payslip_dets['total_ot_pay'] + $payslip_dets['basic_salary'];
        // get payroll runs for payroll date
        
        $get_payroll_runs = $get_payroll_runs = MasterTable::where(['employee_number' => $id])->with(['getPayrollRun'])->get();
        
        return view('pages.users.employee.view_payslip')->with(['payslip_details'=>$payslip_dets,'total_earnings' => $total_earnings,'payroll_runs' => $get_payroll_runs]);
    }
    
    // view edit profile
    public function editprofile($user_id){
        $user_info = User::where('user_id',$user_id)->first();
        return view('pages.users.employee.profile')->with(['user_info' => $user_info]);
    }
    // update profile info
    public function updateProfile(Request $request,$user_id){
        $validation = Validator::make($request->all(),[
            'first_name' => 'required',
            'middle_name' => 'nullable',
            'last_name' => 'required',
            'email' => 'required|email',
            
        ]);
        // if validation succeeds
        if(!$validation->fails()){
            // update user info
            $user_update = User::where('user_id',$user_id)->update([
                'first_name' => $request->post('first_name'),
                'middle_name' => $request->post('middle_name'),
                'last_name' => $request->post('last_name'),
                'email' => $request->post('email'),
            ]);
            
            
            if($user_update){
                // if succefully updated
                return back()->withInput()->with([
                    'notif.style' => 'success',
                    'notif.icon' => 'plus-circle',
                    'notif.message' => 'Succesfully Updated!',
                ]);
            }else{
                // if failed to update
                return back()->withInput()->with([
                    'notif.style' => 'danger',
                    'notif.icon' => 'times-circle',
                    'notif.message' => 'Failed to update!',
                ]);
            }
        }else{
            // if validation fails
            return back()->withErrors($validation->errors())->withInput();
            
            
        }
        
    }
    
    
    // view cahnge password
    public function changePassword($user_id){
        $user_info = User::where('user_id',$user_id)->first();
        return view('pages.users.employee.change_password')->with(['user_info' => $user_info]);
    }
    
    // view change password for new Employee
    public function newEmpchangePassword($user_id){
        $user_info = User::where('user_id',$user_id)->first();
        return view('pages.users.employee.newemp_changepass')->with(['user_info' => $user_info,'type' => 'newEmp']);
    }
    
    // update password
    public function updatePassword(Request $request,$user_id){
        $validation = Validator::make($request->all(),[
            'current_password' => 'required',
            'new_password' => 'required_with:confirm_password|same:confirm_password|min:8',
            'confirm_password' => 'required'
        ]);
        // if validation succeeds
        if(!$validation->fails()){
            // find user information
            $user_info = User::where('user_id',$user_id)->first();
            
            if($user_info){
                // if user info found, check current password || old password if matched
                if(Hash::check($request->post('current_password'),$user_info['password'])){
                    // check if password is different from the old one, if different proceed
                    if($request->post('current_password') != $request->post('new_password')){
                        // update password
                        $update_password = User::where('user_id',$user_id)->update([
                            'password' => bcrypt($request->post('new_password')),
                            'temp_pass' => null,
                            
                        ]);
                        
                        if($update_password){
                            // if succesfully updated
                            if($request->post('type')){
                                Session::flush();
                                return redirect()->route('login')->with([
                                    'notif.style' => 'success',
                                    'notif.icon' => 'plus-circle',
                                    'notif.message' => 'Succesfully Updated!',
                                ]);
                            }else{
                                return back()->withInput()->with([
                                    'notif.style' => 'success',
                                    'notif.icon' => 'plus-circle',
                                    'notif.message' => 'Succesfully Updated!',
                                ]);
                            }
                            
                            
                        }else{
                            // if failed to update
                            return back()->withInput()->with([
                                'notif.style' => 'danger',
                                'notif.icon' => 'times-circle',
                                'notif.message' => 'Failed to update!',
                            ]);
                        }
                    }else{
                        // if password does not different from the current one
                        return back()->withInput()->with([
                            'notif.style' => 'danger',
                            'notif.icon' => 'times-circle',
                            'notif.message' => 'Please use a new password!',
                        ]);
                        
                    }
                    
                }else{
                    
                    // if validation fails
                    return back()->withInput()->with([
                        'notif.style' => 'danger',
                        'notif.icon' => 'times-circle',
                        'notif.message' => 'Please input your correct current password',
                    ]);
                }
            }else{
                // if user info not found
                return back()->withInput()->with([
                    'notif.style' => 'danger',
                    'notif.icon' => 'times-circle',
                    'notif.message' => 'Cannot find this user, Please contact the Admin!',
                ]);
            }
        }else{
            
            // if validation fails
            return back()->withErrors($validation->errors())->withInput();
        }
        
        
    }
    // download payslip as pdf
    public function downloadPayslip(Request $request,$id){
        // relationship
        $relationships = [
            'getOtherIncome',
            'getOtherAllowance',
            'getDeminimis',
            'getOTPay',
            'getGovernmentPayments',
            'getOtherDeductions',
            'getUser',
            'getUser.getCompany',
            'getPayrollRun'
        ];
        
        $payslip_dets = MasterTable::where(['employee_number' => $id, 'upload_id' => $request->get('date')])->with($relationships)->first();
        
        // sum all earnings
        $total_earnings = $payslip_dets->total_other_income + $payslip_dets->total_deminimis + $payslip_dets->total_other_allowances + $payslip_dets->total_ot_pay + $payslip_dets->basic_salary;
        
        
        $pdf = PDF::loadView('pages.users.employee.payslip' ,[
            'payslip_details' => $payslip_dets,
            'total_earnings' => $total_earnings
        ]);
        return $pdf->setPaper('a4', 'landscape')->download(strtoupper($payslip_dets->employee_name).'-payslip-'.$payslip_dets->payroll_date .'.pdf');
    }
}
