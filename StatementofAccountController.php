<?php

namespace App\Http\Controllers\FinanceControllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use PDF;
use TCPDF;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use App\Models\Finance\StatementofAccountModel;
use App\Models\Finance\FinanceUtilityModel;
use App\FinanceModel;
use Carbon\Carbon;
use App\Http\Controllers\DeanControllers\CollegeStudentLoadingController;

class StatementofAccountController extends Controller
{
    public function index(Request $request)
    {
        $schoolyears = DB::table('sy')
            ->get();

        $semesters = DB::table('semester')
            ->where('deleted', '0')
            ->get();

        $monthsetups = DB::table('monthsetup')
            ->get();


        $notes = DB::table('schoolreportsnote')
            ->where('deleted', '0')
            ->where('type', '1')
            ->get();

        $status = 0;

        if (count($notes) > 0) {
            foreach ($notes as $note) {
                if ($note->status) {
                    $status += 1;
                }
            }
        }

        $gradelevels = DB::table('gradelevel')
            ->where('deleted', '0')
            ->orderBy('sortid')
            ->get();

        $schoolinfo = DB::table('schoolinfo')->first();

        return view('finance.statementofaccount.index')
            ->with('gradelevels', $gradelevels)
            ->with('semesters', $semesters)
            ->with('schoolinfo', $schoolinfo)
            // ->with('students', $students)
            ->with('schoolyears', $schoolyears)
            ->with('monthsetups', $monthsetups)
            ->with('notes', $notes)
            ->with('status', $status);
    }


    public function statementofacctsetup(Request $request)
    {
        $value = $request->get('value');
        $setuptype = $request->get('setuptype');

        try {
            // Update all records in month_range_setup based on setup type
            if ($setuptype == 'discount') {
                DB::table('month_range_setup')
                    ->where('deleted', 0)
                    ->update([
                        'wdiscount' => $value,
                        'updateddatetime' => now(),
                        'updatedby' => auth()->user()->id ?? 1
                    ]);
            } elseif ($setuptype == 'adjustment') {
                DB::table('month_range_setup')
                    ->where('deleted', 0)
                    ->update([
                        'wadjustment' => $value,
                        'updateddatetime' => now(),
                        'updatedby' => auth()->user()->id ?? 1
                    ]);
            } elseif ($setuptype == 'oldaccounts') {
                DB::table('month_range_setup')
                    ->where('deleted', 0)
                    ->update([
                        'woldacc' => $value,
                        'updateddatetime' => now(),
                        'updatedby' => auth()->user()->id ?? 1
                    ]);
            } elseif ($setuptype == 'dp') {
                DB::table('month_range_setup')
                    ->where('deleted', 0)
                    ->update([
                        'wdp' => $value,
                        'updateddatetime' => now(),
                        'updatedby' => auth()->user()->id ?? 1
                    ]);
            }

            return response()->json(1);
        } catch (\Exception $e) {
            return response()->json(0);
        }
    }

    //working v2 code
    // public function generate(Request $request)
    // {
    //     $semid = $request->get('selectedsemester');
    //     $syid = $request->get('selectedschoolyear');
    //     $levelid = $request->get('selectedgradelevel');
    //     $sectionid = $request->get('selectedsection');
    //     $courseid = $request->get('selectedcourse');

    //     if ($request->get('selectedmonth') == null) {
    //         $month = null;
    //     } else {
    //         $month = date('m', strtotime($request->get('selectedmonth')));
    //     }

    //     if ($levelid == 0) {
    //         $students = collect(StatementofAccountModel::allstudents())->values();
    //     } else {
    //         $students = collect(StatementofAccountModel::allstudents($levelid, $syid, $semid, $sectionid, $courseid))->values();
    //     }
    //     return view('finance.statementofaccount.filtertable')
    //         ->with('students', $students);

    // }

    public function generate(Request $request)
    {
        $semid = $request->get('selectedsemester');
        $syid = $request->get('selectedschoolyear');
        $levelid = $request->get('selectedgradelevel');
        $sectionid = $request->get('selectedsection');
        $courseid = $request->get('selectedcourse');

        if ($request->get('selectedmonth') == null) {
            $month = null;
        } else {
            $month = date('m', strtotime($request->get('selectedmonth')));
        }

        if ($levelid == '00') {
            $levels = DB::table('gradelevel')
                ->where('deleted', '0')
                ->get();

            $students = collect();
            foreach ($levels as $level) {
                $students = $students->merge(collect(StatementofAccountModel::allstudents($level->id, $syid, $semid, $sectionid, $courseid))->values());
            }
        } else if ($levelid == 0) {
            $students = collect(StatementofAccountModel::allstudents())->values();
        } else {
            $students = collect(StatementofAccountModel::allstudents($levelid, $syid, $semid, $sectionid, $courseid))->values();
        }
        return view('finance.statementofaccount.filtertable')
            ->with('students', $students);

    }

    public function getaccount(Request $request)
    {
        $studid = $request->get('studid');
        $semid = $request->get('selectedsemester');
        $syid = $request->get('selectedschoolyear');
        $monthsetupid = $request->get('selectedmonth');
        $month = null;
        if ($request->get('selectedmonth') > 0) {
            if (date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month']) {
                if (strlen(date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month']) == 1) {
                    $month = '0' . date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month'];
                } else {
                    $month = date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month'];
                }
            }
        }

        $studinfo = db::table('studinfo')
            ->select('studinfo.id', 'lastname', 'firstname', 'middlename', 'suffix', 'levelname', 'sectionname', 'levelid')
            ->join('gradelevel', 'studinfo.levelid', '=', 'gradelevel.id')
            ->where('studinfo.id', $studid)
            ->first();

        if ($studinfo->levelid == 14 || $studinfo->levelid == 15) {
            $ledger = db::table('studledger')
                ->select('studledger.*', 'chrngsetup.groupname')
                ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                ->where('studledger.studid', $studid)
                ->where('studledger.syid', $syid)
                ->where(function ($q) use ($semid) {
                    if (DB::table('schoolinfo')->first()->shssetup == 0) {
                        $q->where('semid', $semid);
                    }
                })
                ->where('studledger.void', 0)
                ->where('studledger.deleted', 0)
                ->orderBy('studledger.id', 'asc')
                ->get();
        } elseif ($studinfo->levelid >= 17 && $studinfo->levelid <= 25) {
            $ledger = db::table('studledger')
                ->select('studledger.*', 'chrngsetup.groupname')
                ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                ->where('studledger.studid', $studid)
                ->where('studledger.syid', $syid)
                ->where('studledger.semid', $semid)
                ->where('studledger.deleted', 0)
                ->where('studledger.void', 0)
                ->orderBy('studledger.id', 'asc')
                ->get();
        } else {
            $ledger = db::table('studledger')
                ->select('studledger.*', 'chrngsetup.groupname')
                ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                ->where('studledger.studid', $studid)
                ->where('studledger.syid', $syid)
                ->where('studledger.deleted', 0)
                ->where('studledger.void', 0)
                ->orderBy('studledger.id', 'asc')
                ->get();
        }
        // if($studinfo->levelid == 14 || $studinfo->levelid == 15)
        // {

        // $ledger = db::table('studledger')
        //     ->where('studid', $studid)
        //     ->where('syid', $syid)
        //     ->where(function($q) use($semid){
        //         if(DB::table('schoolinfo')->first()->shssetup == 0)
        //         {
        //             $q->where('semid', $semid);
        //         }
        //     })
        //     ->where('void', 0)
        //     ->where('deleted', 0)
        //     ->orderBy('id', 'asc')
        //     ->get();
        // }
        // elseif($studinfo->levelid >= 17 && $studinfo->levelid <= 21)
        // {
        // $ledger = db::table('studledger')
        //     ->where('studid', $studid)
        //     ->where('syid', $syid)
        //     ->where('semid', $semid)
        //     ->where('void', 0)
        //     ->where('deleted', 0)
        //     ->orderBy('id', 'asc')
        //     ->get();
        // }
        // else
        // {
        // $ledger = db::table('studledger')
        //     ->where('studid', $studid)
        //     ->where('syid', $syid)
        //     ->where('void', 0)
        //     ->where('deleted', 0)
        //     ->orderBy('id', 'asc')
        //     ->get();
        // }

        $bal = 0;
        $debit = 0;
        $credit = 0;

        if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'xai') {

            foreach ($ledger as $led) {
                $debit += $led->amount;

                if ($led->void == 0) {
                    $credit += $led->payment;
                }

                $lDate = date_create($led->createddatetime);
                $lDate = date_format($lDate, 'm-d-Y');

                if ($led->amount > 0) {
                    $amount = number_format($led->amount, 2);
                } else {
                    $amount = '';
                }

                if ($led->payment > 0) {
                    $payment = number_format($led->payment, 2);
                } else {
                    $payment = '';
                }

                if ($led->void == 0) {
                    $bal += $led->amount - $led->payment;
                }

            }
            if ($studinfo->levelid == 14 || $studinfo->levelid == 15) {
                $getPaySched = db::table('studpayscheddetail')
                    ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
                    ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
                    ->where('studid', $studid)
                    ->where('syid', $syid)
                    ->where(function ($q) use ($semid) {
                        if (db::table('schoolinfo')->first()->shssetup == 0) {
                            $q->where('semid', $semid);
                        }
                    })
                    ->where('studpayscheddetail.deleted', 0)

                    ->groupBy(db::raw('MONTH(duedate)'))
                    ->get();
                // return $getPaySched;

            } else if ($studinfo->levelid >= 17 && $studinfo->levelid <= 20) {

                $getPaySched = db::table('studpayscheddetail')
                    ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
                    ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
                    ->where('studid', $studid)
                    ->where('syid', $syid)
                    ->where('semid', $semid)
                    ->where('studpayscheddetail.deleted', 0)
                    ->groupBy(db::raw('MONTH(duedate)'))
                    ->get();
                // $getPaySched = db::table('studpayscheddetail')
                // ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
                //     ->join('itemclassification','studpayscheddetail.classid','=','itemclassification.id')
                //     ->where('studid', $studid)
                //     ->where('syid', $syid)
                //     ->where('semid', $semid)
                //     ->where('deleted', 0)
                //     ->groupBy(db::raw('MONTH(duedate)'))
                //     ->get();
            } else {
                // $getPaySched = db::select('description, sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate
                //     from studpayscheddetail
                //     INNER JOIN itemclassification
                //     ON studpayscheddetail.`classid` = itemclassification.id
                //     where studid = ? and syid = ? and studpayscheddetail.deleted = 0
                //     group by MONTH(duedate)
                //     order by duedate', [$studid, $syid]);

                $getPaySched = db::table('studpayscheddetail')
                    ->select(db::raw('description1, sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate'))
                    ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
                    ->where('studid', $studid)
                    ->where('syid', $syid)
                    ->groupBy(db::raw('month(duedate)'))
                    ->orderBy('duedate');
            }
            $assessbilling = 0;
            $assesspayment = 0;
            $assessbalance = 0;
            $totalBal = collect($getPaySched)->sum('balance');
            ;

            if (count($getPaySched) > 0) {
                foreach ($getPaySched as $psched) {

                    // return $getPaySched;
                    // $totalBal += $psched->balance;
                    $assessbilling += $psched->amountdue;
                    $assesspayment += $psched->amountpay;
                    $assessbalance += $psched->balance;

                    $m = date_create($psched->duedate);
                    $f = date_format($m, 'F');
                    $m = date_format($m, 'm');

                    if ($psched->duedate != '') {
                        $particulars = 'PAYABLES FOR ' . strtoupper($f);
                    } else {
                        if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'faai') {
                            $particulars = 'REGISTRATION/MISCELLANEOUS/BOOKS/GENYO';
                        } else {
                            $particulars = 'ONE-TIME PAYMENT';
                        }
                        $m = 0;
                    }
                }


                $monthname = date('M', strtotime('2020-' . $month));
                return view('finance.statementofaccount.table_xai')
                    ->with('studinfo', $studinfo)
                    ->with('monthname', $monthname)
                    ->with('ledger', $ledger)
                    ->with('getPaySched', $getPaySched);



            } else {

                $monthname = date('M', strtotime('2020-' . $month));
                return view('finance.statementofaccount.table_xai')
                    ->with('studinfo', $studinfo)
                    ->with('monthname', $monthname)
                    ->with('ledger', $ledger)
                    ->with('getPaySched', $getPaySched);
            }

        } else {

            $output = '<table class="table table-bordered" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th colspan="5">LEDGER</th>
                            </tr>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Billing</th>
                                <th>Deduction</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>';

            foreach ($ledger as $led) {
                $debit += $led->amount;

                if ($led->void == 0) {
                    $credit += $led->payment;
                }

                $lDate = date_create($led->createddatetime);
                $lDate = date_format($lDate, 'm-d-Y');

                if ($led->amount > 0) {
                    $amount = number_format($led->amount, 2);
                } else {
                    $amount = '';
                }

                if ($led->payment > 0) {
                    $payment = number_format($led->payment, 2);
                } else {
                    $payment = '';
                }

                if ($led->void == 0) {
                    $bal += $led->amount - $led->payment;
                }

                if ($led->void == 0) {
                    $output .= '
                    <tr>
                        <td>' . $lDate . ' </td>
                        <td>' . $led->particulars . '</td>
                        <td class="text-right">' . $amount . '</td>
                        <td class="text-right">' . $payment . '</td>
                        <td class="text-right">' . number_format($bal, 2) . '</td>
                    </tr>
                    ';
                } else {
                    $output .= '
                    <tr>
                        <td class="text-danger"><del>' . $lDate . ' </del></td>
                        <td class="text-danger"><del>' . $led->particulars . '</del></td>
                        <td class="text-right text-danger"><del>' . $amount . '</del></td>
                        <td class="text-right text-danger"><del>' . $payment . '</del></td>
                        <td class="text-right text-danger"><del>' . number_format($bal, 2) . '</del></td>
                    </tr>
                    ';
                }

            }

            $output .= '
            <tr style="background-color:#007bff91">
                <th></th>
                <th style="text-align:right">
                    <strong>TOTAL:<strong>
                </th>
                <th class="text-right">
                    <strong><u>' . number_format($debit, 2) . '</u></strong>
                </th>
                <th class="text-right">
                    <strong><u>' . number_format($credit, 2) . '</u></strong>
                </th>
                <th class="text-right">
                    <strong><u>' . number_format($bal, 2) . '</u></strong>
                </th>
            </tr>
            </tbody>
            <thead>
                <tr>
                    <th colspan="5">ASSESSMENT</th>
                </tr>
            </thead>
            <tbody>';
            if ($studinfo->levelid == 14 || $studinfo->levelid == 15) {
                //   $getPaySched = db::select('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate
                //       from studpayscheddetail
                //       where studid = ? and syid = ? and semid = ? and deleted = 0
                //       group by MONTH(duedate)
                //       order by duedate', [$studid, $syid, $semid]);

                $getPaySched = db::table('studpayscheddetail')
                    ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate'))
                    ->where('studid', $studid)
                    ->where('syid', $syid)
                    ->where(function ($q) use ($semid) {
                        if (DB::table('schoolinfo')->first()->shssetup == 0) {
                            $q->where('semid', $semid);
                        }
                    })
                    ->where('deleted', 0)
                    ->groupBy(db::raw('MONTH(duedate)'))
                    ->get();

            } else if ($studinfo->levelid >= 17 && $studinfo->levelid <= 20) {

                $getPaySched = db::table('studpayscheddetail')
                    ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
                    ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
                    ->where('studid', $studid)
                    ->where('syid', $syid)
                    ->where('semid', $semid)
                    ->where('studpayscheddetail.deleted', 0)
                    ->groupBy(db::raw('MONTH(duedate)'))
                    ->get();
            } else {

                $getPaySched = db::table('studpayscheddetail')
                    ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
                    ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
                    ->where('studid', $studid)
                    ->where('syid', $syid)
                    ->where('studpayscheddetail.deleted', 0)
                    ->groupBy(db::raw('MONTH(duedate)'))
                    ->get();
            }
            $monthsetup = DB::table('monthsetup')
                ->get();

            if (count($getPaySched) > 0) {
                foreach ($getPaySched as $eachpaysched) {
                    if ($eachpaysched->duedate == null) {
                        $eachpaysched->monthid = 0;
                        $eachpaysched->monthnumber = 0;
                    } else {
                        if (collect($monthsetup)->where('description', strtoupper(strtolower(date('F', strtotime($eachpaysched->duedate)))))->count() > 0) {
                            $eachpaysched->monthid = collect($monthsetup)->where('description', strtoupper(strtolower(date('F', strtotime($eachpaysched->duedate)))))->first()->id;
                        } else {
                            $eachpaysched->monthid = 0;
                        }
                        if (strlen(date_parse(date('F', strtotime($eachpaysched->duedate)))['month']) == 1) {
                            $eachpaysched->monthnumber = '0' . date_parse(date('F', strtotime($eachpaysched->duedate)))['month'];
                        } else {
                            $eachpaysched->monthnumber = date_parse(date('F', strtotime($eachpaysched->duedate)))['month'];
                        }
                    }
                }
            }
            $getPaySched = collect($getPaySched)->sortBy('monthid')->sortBy('monthnum')->values();
            // return $getPaySched;
            $assessbilling = 0;
            $assesspayment = 0;
            $assessbalance = 0;
            $totalBal = collect($getPaySched)->sum('balance');

            if (count($getPaySched) > 0) {
                foreach ($getPaySched as $key => $psched) {
                    if ($psched->monthid <= $monthsetupid) {
                        if ($monthsetupid == 0) {
                            $assessbilling += $psched->amountdue;
                            $assesspayment += $psched->amountpay;
                            $assessbalance += $psched->balance;

                            $m = date_create($psched->duedate);
                            $f = date_format($m, 'F');
                            $m = date_format($m, 'm');

                            if ($psched->duedate != '') {
                                $particulars = 'PAYABLES FOR ' . strtoupper($f);
                            } else {
                                if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'faai') {
                                    $particulars = 'REGISTRATION/MISCELLANEOUS/BOOKS/GENYO';
                                } else {
                                    $particulars = 'ONE-TIME PAYMENT';
                                }
                                $m = 0;
                            }
                            $output .= '
                                <tr>
                                <td></td>
                                <td>' . $particulars . '</td>
                                <td class="text-right">' . number_format($psched->amountdue, 2) . '</td>
                                <td class="text-right">' . number_format($psched->amountpay, 2) . '</td>
                                <td class="text-right">' . number_format($psched->balance, 2) . '</td>
                                </tr>
                            ';
                        } else {
                            $assessbilling += $psched->amountdue;
                            $assesspayment += $psched->amountpay;
                            $assessbalance += $psched->balance;

                            $m = date_create($psched->duedate);
                            $f = date_format($m, 'F');
                            $m = date_format($m, 'm');
                            if ($psched->duedate != '') {
                                $particulars = 'PAYABLES FOR ' . strtoupper($f);
                            } else {
                                if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'faai') {
                                    $particulars = 'REGISTRATION/MISCELLANEOUS/BOOKS/GENYO';
                                } else {
                                    $particulars = 'ONE-TIME PAYMENT';
                                }
                                $m = 0;
                            }

                            $arraymonthsetups = collect($monthsetup)->where('id', '<=', $monthsetupid)->values();
                            if (count($arraymonthsetups) > 0) {
                                if ($psched->monthid == 0) {
                                    $output .= '
                                    <tr>
                                    <td></td>
                                        <td>' . $particulars . '</td>
                                        <td class="text-right">' . number_format($psched->amountdue, 2) . '</td>
                                        <td class="text-right">' . number_format($psched->amountpay, 2) . '</td>
                                        <td class="text-right">' . number_format($psched->balance, 2) . '</td>
                                    </tr>
                                    ';
                                } else {
                                    if (collect($arraymonthsetups)->where('id', $psched->monthid)->count() > 0) {
                                        $output .= '
                                        <tr>
                                        <td></td>
                                            <td>' . $particulars . '</td>
                                            <td class="text-right">' . number_format($psched->amountdue, 2) . '</td>
                                            <td class="text-right">' . number_format($psched->amountpay, 2) . '</td>
                                            <td class="text-right">' . number_format($psched->balance, 2) . '</td>
                                        </tr>
                                        ';

                                    }

                                }
                            }
                        }
                    }
                }


                $output .= '
                    <tr style="background-color:#007bff91">
                        <th></th>
                        <th style="text-align:right">
                            <strong>TOTAL:<strong>
                        </th>
                        <th class="text-right">
                            <strong><u>' . number_format($assessbilling, 2) . '</u></strong>
                        </th>
                        <th class="text-right">
                            <strong><u>' . number_format($assesspayment, 2) . '</u></strong>
                        </th>
                        <th class="text-right">
                            <strong><u>' . number_format($assessbalance, 2) . '</u></strong>
                        </th>
                    </tr>
                    <tr style="background-color:#ffc1078c">
                        <th></th>
                        <th style="text-align:right">
                            <strong>TOTAL BALANCE:<strong>
                        </th>
                        <th class="text-right">
                            <strong><u>' . number_format($assessbilling, 2) . '</u></strong>
                        </th>
                        <th class="text-right">
                            <strong><u>' . number_format($assesspayment, 2) . '</u></strong>
                        </th>
                        <th class="text-right">
                            <strong><u>' . number_format(($assessbalance), 2) . '</u></strong>
                        </th>
                    </tr>
                    <tr style="background-color:#ffc1078c">
                        <th></th>
                        <th style="text-align:right">
                            <strong>TOTAL AMOUNT DUE:<strong>
                        </th>
                        <th class="text-right">


                        </th>
                        <th class="text-right">

                        </th>
                        <th class="text-right" style="font-size:">
                            <h4><strong><u>' . number_format(($assessbalance), 2) . '</u></strong></h4>
                        </th>
                    </tr>
                </tbody>
                </table>';

            } else {

                $output .= '
                <tr style="background-color:#ffc1078c">
                    <th></th>
                    <th style="text-align:right">
                        <strong>TOTAL BALANCE:<strong>
                    </th>
                    <th class="text-right">
                        <strong><u>' . number_format($debit, 2) . '</u></strong>
                    </th>
                    <th class="text-right">
                        <strong><u>' . number_format($credit, 2) . '</u></strong>
                    </th>
                    <th class="text-right">
                        <strong><u>' . number_format($bal, 2) . '</u></strong>
                    </th>
                </tr>
                <tr style="background-color:#ffc1078c">
                    <th></th>
                    <th style="text-align:right">
                        <strong>TOTAL AMOUNT DUE:<strong>
                    </th>
                    <th class="text-right">


                    </th>
                    <th class="text-right">

                    </th>
                    <th class="text-right" style="font-size:">
                        <h4><strong><u>' . number_format($totalBal, 2) . '</u></strong></h4>
                    </th>
                </tr>
              </tbody>
              </table>';
            }
        }

        return $output;
    }

    // public function getaccount_v2(Request $request)
    // {
    //     $studid = $request->get('studid');
    //     $semid = $request->get('selectedsemester');
    //     $syid = $request->get('selectedschoolyear');
    //     $monthsetupid = $request->get('selectedmonth');
    //     $month  = null;
    //     if($request->get('selectedmonth') > 0)
    //     {
    //         if(date_parse(DB::table('monthsetup')->where('id',$monthsetupid)->first()->description)['month'])
    //         {
    //             if(strlen(date_parse(DB::table('monthsetup')->where('id',$monthsetupid)->first()->description)['month']) == 1)
    //             {
    //                 $month      = '0'.date_parse(DB::table('monthsetup')->where('id',$monthsetupid)->first()->description)['month'];
    //             }else{
    //                 $month      =date_parse(DB::table('monthsetup')->where('id',$monthsetupid)->first()->description)['month'];
    //             }
    //         }
    //     }

    //     $studinfo = db::table('studinfo')
    //         ->select('studinfo.id', 'lastname', 'firstname', 'middlename', 'suffix', 'levelname', 'sectionname', 'levelid')
    //         ->join('gradelevel', 'studinfo.levelid', '=', 'gradelevel.id')
    //         ->where('studinfo.id', $studid)
    //         ->first();

    //     if($studinfo->levelid == 14 || $studinfo->levelid == 15)
    //     {
    //         $ledger = db::table('studledger')
    //             ->select('studledger.*','chrngsetup.groupname')
    //             ->leftJoin('chrngsetup','studledger.classid','=','chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where(function($q) use($semid){
    //                 if(DB::table('schoolinfo')->first()->shssetup == 0)
    //                 {
    //                     $q->where('semid', $semid);
    //                 }
    //             })
    //             ->where('studledger.void', 0)
    //             ->where('studledger.deleted', 0)
    //             ->orderBy('studledger.id', 'asc')
    //             ->get();
    //     }
    //     elseif($studinfo->levelid >= 17 && $studinfo->levelid <= 21)
    //     {
    //         $ledger = db::table('studledger')
    //             ->select('studledger.*','chrngsetup.groupname')
    //             ->leftJoin('chrngsetup','studledger.classid','=','chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where('studledger.semid', $semid)
    //             ->where('studledger.deleted', 0)
    //             ->where('studledger.void', 0)
    //             ->orderBy('studledger.id', 'asc')
    //             ->get();
    //     }
    //     else
    //     {
    //         $ledger = db::table('studledger')
    //             ->select('studledger.*','chrngsetup.groupname')
    //             ->leftJoin('chrngsetup','studledger.classid','=','chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where('studledger.deleted', 0)
    //             ->where('studledger.void', 0)
    //             ->orderBy('studledger.id', 'asc')
    //             ->get();
    //     }
    //     // if($studinfo->levelid == 14 || $studinfo->levelid == 15)
    //     // {

    //     // $ledger = db::table('studledger')
    //     //     ->where('studid', $studid)
    //     //     ->where('syid', $syid)
    //     //     ->where(function($q) use($semid){
    //     //         if(DB::table('schoolinfo')->first()->shssetup == 0)
    //     //         {
    //     //             $q->where('semid', $semid);
    //     //         }
    //     //     })
    //     //     ->where('void', 0)
    //     //     ->where('deleted', 0)
    //     //     ->orderBy('id', 'asc')
    //     //     ->get();
    //     // }
    //     // elseif($studinfo->levelid >= 17 && $studinfo->levelid <= 21)
    //     // {
    //     // $ledger = db::table('studledger')
    //     //     ->where('studid', $studid)
    //     //     ->where('syid', $syid)
    //     //     ->where('semid', $semid)
    //     //     ->where('void', 0)
    //     //     ->where('deleted', 0)
    //     //     ->orderBy('id', 'asc')
    //     //     ->get();
    //     // }
    //     // else
    //     // {
    //     // $ledger = db::table('studledger')
    //     //     ->where('studid', $studid)
    //     //     ->where('syid', $syid)
    //     //     ->where('void', 0)
    //     //     ->where('deleted', 0)
    //     //     ->orderBy('id', 'asc')
    //     //     ->get();
    //     // }

    //     $bal = 0;
    //     $debit = 0;
    //     $credit = 0;

    //     if(strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'xai')
    //     {

    //         foreach($ledger as $led)
    //         {
    //             $debit += $led->amount;

    //             if($led->void == 0)
    //             {
    //                 $credit += $led->payment;
    //             }

    //             $lDate = date_create($led->createddatetime);
    //             $lDate = date_format($lDate, 'm-d-Y');

    //             if($led->amount > 0)
    //             {
    //                 $amount = number_format($led->amount,2);
    //             }
    //             else
    //             {
    //                 $amount = '';
    //             }

    //             if($led->payment > 0)
    //             {
    //                 $payment = number_format($led->payment,2);
    //             }
    //             else
    //             {
    //                 $payment = '';
    //             }

    //             if($led->void == 0)
    //             {
    //                 $bal += $led->amount - $led->payment;
    //             }

    //         }
    //         if($studinfo->levelid == 14 || $studinfo->levelid == 15)
    //         {
    //             $getPaySched = db::table('studpayscheddetail')
    //                 ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
    //                 ->join('itemclassification','studpayscheddetail.classid','=','itemclassification.id')
    //                 ->where('studid', $studid)
    //                 ->where('syid', $syid)
    //                 ->where(function($q) use($semid){
    //                     if(db::table('schoolinfo')->first()->shssetup == 0)
    //                     {
    //                         $q->where('semid', $semid);
    //                     }
    //                 })
    //                 ->where('studpayscheddetail.deleted', 0)

    //                 ->groupBy(db::raw('MONTH(duedate)'))
    //                 ->get();
    //                 // return $getPaySched;

    //         }
    //         else if($studinfo->levelid >= 17 && $studinfo->levelid <= 20)
    //         {

    //             $getPaySched = db::table('studpayscheddetail')
    //             ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
    //                 ->join('itemclassification','studpayscheddetail.classid','=','itemclassification.id')
    //                 ->where('studid', $studid)
    //                 ->where('syid', $syid)
    //                 ->where('semid', $semid)
    //                 ->where('studpayscheddetail.deleted', 0)
    //                 ->groupBy(db::raw('MONTH(duedate)'))
    //                 ->get();
    //             // $getPaySched = db::table('studpayscheddetail')
    //             // ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
    //             //     ->join('itemclassification','studpayscheddetail.classid','=','itemclassification.id')
    //             //     ->where('studid', $studid)
    //             //     ->where('syid', $syid)
    //             //     ->where('semid', $semid)
    //             //     ->where('deleted', 0)
    //             //     ->groupBy(db::raw('MONTH(duedate)'))
    //             //     ->get();
    //         }
    //         else
    //         {
    //             // $getPaySched = db::select('description, sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate
    //             //     from studpayscheddetail
    //             //     INNER JOIN itemclassification
    //             //     ON studpayscheddetail.`classid` = itemclassification.id
    //             //     where studid = ? and syid = ? and studpayscheddetail.deleted = 0
    //             //     group by MONTH(duedate)
    //             //     order by duedate', [$studid, $syid]);

    //             $getPaySched = db::table('studpayscheddetail')
    //                 ->select(db::raw('description1, sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate'))
    //                     ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
    //                     ->where('studid', $studid)
    //                     ->where('syid', $syid)
    //                     ->groupBy(db::raw('month(duedate)'))
    //                     ->orderBy('duedate');
    //         }
    //         $assessbilling = 0;
    //         $assesspayment = 0;
    //         $assessbalance = 0;
    //         $totalBal = collect($getPaySched)->sum('balance');;

    //         if(count($getPaySched) > 0)
    //         {
    //             foreach($getPaySched as $psched)
    //             {

    //                 // return $getPaySched;
    //                 // $totalBal += $psched->balance;
    //                 $assessbilling += $psched->amountdue;
    //                 $assesspayment += $psched->amountpay;
    //                 $assessbalance += $psched->balance;

    //                 $m = date_create($psched->duedate);
    //                 $f = date_format($m, 'F');
    //                 $m = date_format($m, 'm');

    //                 if($psched->duedate != '')
    //                 {
    //                     $particulars = 'PAYABLES FOR ' . strtoupper($f);
    //                 }
    //                 else
    //                 {
    //                     if(strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'faai')
    //                     {
    //                         $particulars = 'REGISTRATION/MISCELLANEOUS/BOOKS/GENYO';
    //                     }else{
    //                         $particulars = 'ONE-TIME PAYMENT';
    //                     }
    //                     $m = 0;
    //                 }
    //             }


    //             $monthname = date('M', strtotime('2020-'.$month));
    //             return view('finance.statementofaccount.table_xai')
    //                 ->with('studinfo', $studinfo)
    //                 ->with('monthname', $monthname)
    //                 ->with('ledger', $ledger)
    //                 ->with('getPaySched', $getPaySched);



    //         }else{

    //             $monthname = date('M', strtotime('2020-'.$month));
    //             return view('finance.statementofaccount.table_xai')
    //                 ->with('studinfo', $studinfo)
    //                 ->with('monthname', $monthname)
    //                 ->with('ledger', $ledger)
    //                 ->with('getPaySched', $getPaySched);
    //         }

    //     }else{

    //         $output = '<table class="table table-bordered" style="font-size: 12px;">
    //                     <thead>
    //                         <tr>
    //                             <th colspan="5">LEDGER</th>
    //                         </tr>
    //                         <tr>
    //                             <th>Date</th>
    //                             <th>Description</th>
    //                             <th>Billing</th>
    //                             <th>Payment</th>
    //                             <th>Balance</th>
    //                         </tr>
    //                     </thead>
    //                     <tbody>';

    //         foreach($ledger as $led)
    //         {
    //             $debit += $led->amount;

    //             if($led->void == 0)
    //             {
    //                 $credit += $led->payment;
    //             }

    //             $lDate = date_create($led->createddatetime);
    //             $lDate = date_format($lDate, 'm-d-Y');

    //             if($led->amount > 0)
    //             {
    //                 $amount = number_format($led->amount,2);
    //             }
    //             else
    //             {
    //                 $amount = '';
    //             }

    //             if($led->payment > 0)
    //             {
    //                 $payment = number_format($led->payment,2);
    //             }
    //             else
    //             {
    //                 $payment = '';
    //             }

    //             if($led->void == 0)
    //             {
    //                 $bal += $led->amount - $led->payment;
    //             }

    //             if($led->void == 0)
    //             {
    //                 $output .='
    //                 <tr>
    //                     <td>' .$lDate.' </td>
    //                     <td>'.$led->particulars.'</td>
    //                     <td class="text-right">'.$amount.'</td>
    //                     <td class="text-right">'.$payment.'</td>
    //                     <td class="text-right">'.number_format($bal, 2).'</td>
    //                 </tr>
    //                 ';
    //             }
    //             else
    //             {
    //                 $output .='
    //                 <tr>
    //                     <td class="text-danger"><del>' .$lDate.' </del></td>
    //                     <td class="text-danger"><del>'.$led->particulars.'</del></td>
    //                     <td class="text-right text-danger"><del>'.$amount.'</del></td>
    //                     <td class="text-right text-danger"><del>'.$payment.'</del></td>
    //                     <td class="text-right text-danger"><del>'.number_format($bal, 2).'</del></td>
    //                 </tr>
    //                 ';
    //             }

    //         }

    //         $output .='
    //         <tr style="background-color:#007bff91">
    //             <th></th>
    //             <th style="text-align:right">
    //                 <strong>TOTAL:<strong>
    //             </th>
    //             <th class="text-right">
    //                 <strong><u>'.number_format($debit, 2).'</u></strong>
    //             </th>
    //             <th class="text-right">
    //                 <strong><u>'.number_format($credit, 2).'</u></strong>
    //             </th>
    //             <th class="text-right">
    //                 <strong><u>'.number_format($bal, 2).'</u></strong>
    //             </th>
    //         </tr>
    //         </tbody>
    //         <thead>
    //             <tr>
    //                 <th colspan="5">ASSESSMENT</th>
    //             </tr>
    //         </thead>
    //         <tbody>';

    //         $_monthid = db::table('monthsetup')->where('id', $monthsetupid)->first()->monthid;

    //         $assessment = FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $_monthid);
    //         $a_totalbill = 0;
    //         $a_totalpay = 0;
    //         $a_totalbalance = 0;

    //         foreach($assessment as $sched)
    //         {
    //             $_particulars = strtoupper($sched->particulars . ' PAYABLES');
    //             $a_totalbill += $sched->amount;
    //             $a_totalpay += $sched->payment;
    //             $a_totalbalance += $sched->balance;

    //             $output .='
    //                 <tr>
    //                     <td></td>
    //                     <td>'.$_particulars.'</td>
    //                     <td class="text-right">'.number_format($sched->amount, 2).'</td>
    //                     <td class="text-right">'.number_format($sched->payment, 2).'</td>
    //                     <td class="text-right">'.number_format($sched->balance, 2).'</td>
    //                 </tr>
    //             ';
    //         }

    //         $output .='
    //             <tr style="background-color:#007bff91">
    //                 <th></th>
    //                 <th style="text-align:right">
    //                     <strong>TOTAL:<strong>
    //                 </th>
    //                 <th class="text-right">
    //                     <strong><u>'.number_format($a_totalbill, 2).'</u></strong>
    //                 </th>
    //                 <th class="text-right">
    //                     <strong><u>'.number_format($a_totalpay, 2).'</u></strong>
    //                 </th>
    //                 <th class="text-right">
    //                     <strong><u>'.number_format($a_totalbalance, 2).'</u></strong>
    //                 </th>
    //             </tr>
    //             </tbody>
    //         ';
    //     }


    //     return $output;
    // }

    // public function getaccount_v2(Request $request)
    // {
    //     $studid = $request->get('studid');
    //     $semid = $request->get('selectedsemester');
    //     $syid = $request->get('selectedschoolyear');
    //     $monthsetupid = $request->get('selectedmonth');
    //     $action = $request->get('action');
    //     $month  = null;
    //     $levelid = 0;
    //     $sectionid = 0;
    //     $courseid = 0;
    //     $sectionname = '';
    //     $courseabrv = '';


    //     // return $request;

    //     if($request->get('selectedmonth') > 0)
    //     {
    //         if(date_parse(DB::table('monthsetup')->where('id',$monthsetupid)->first()->description)['month'])
    //         {
    //             if(strlen(date_parse(DB::table('monthsetup')->where('id',$monthsetupid)->first()->description)['month']) == 1)
    //             {
    //                 $month      = '0'.date_parse(DB::table('monthsetup')->where('id',$monthsetupid)->first()->description)['month'];
    //             }else{
    //                 $month      =date_parse(DB::table('monthsetup')->where('id',$monthsetupid)->first()->description)['month'];
    //             }
    //         }
    //     }

    //     $request['syid'] = $syid;
    //     $request['semid'] = $semid;
    //     $request['monthid'] = $month;

    //     $selectedschoolyear = DB::table('sy')
    //         ->where('id', $syid)
    //         ->first()
    //         ->sydesc;

    //     $selectedsemester = db::table('semester')
    //         ->where('id', $semid)
    //         ->first()
    //         ->semester;

    //     $studinfo = db::table('studinfo')
    //         ->select('id', 'sid', 'lastname', 'firstname', 'middlename', 'suffix')
    //         ->where('studinfo.id', $studid)
    //         ->first();

    //     $estud = db::table('enrolledstud')
    //         ->select('levelid', 'sectionid', 'studstatus')
    //         ->where('studid', $studid)
    //         ->where('syid', $syid)
    //         ->where('deleted', 0)
    //         ->first();

    //     if($estud)
    //     {
    //         $levelid = $estud->levelid;
    //         $sectionid = $estud->sectionid;

    //         $section = db::table('sections')
    //             ->where('id', $sectionid)
    //             ->first();

    //         if($section)
    //         {
    //             $sectionname = $section->sectionname;
    //         }

    //     }
    //     else{
    //         $estud = db::table('sh_enrolledstud')
    //             ->select('levelid', 'sectionid', 'studstatus')
    //             ->where('studid', $studid)
    //             ->where('deleted', 0)
    //             ->first();

    //         if($estud)
    //         {
    //             $levelid = $estud->levelid;
    //             $sectionid = $estud->sectionid;

    //             $section = db::table('sections')
    //                 ->where('id', $sectionid)
    //                 ->first();

    //             if($section)
    //             {
    //                 $sectionname = $section->sectionname;
    //             }

    //         }
    //         else{
    //             $estud = db::table('college_enrolledstud')
    //                 ->select('yearLevel as levelid', 'courseid', 'studstatus')
    //                 ->where('studid', $studid)
    //                 ->where('deleted', 0)
    //                 ->first();

    //             if($estud)
    //             {
    //                 $levelid = $estud->levelid;
    //                 $courseid = $estud->courseid;

    //                 $courses = db::table('college_courses')
    //                     ->where('id', $courseid)
    //                     ->first();

    //                 if($courses)
    //                 {
    //                     $courseabrv = $courses->courseabrv;
    //                 }
    //             }
    //             else{
    //                 $estud = db::table('studinfo')
    //                     ->select('levelid', 'studstatus')
    //                     ->where('id', $studid)
    //                     ->first();

    //                 if($estud)
    //                 {
    //                     $levelid = $estud->levelid;
    //                     $secionid = 0;
    //                 }
    //             }
    //         }
    //     }


    //     if($levelid == 14 || $levelid == 15)
    //     {
    //         $ledger = db::table('studledger')
    //             ->select('studledger.*','chrngsetup.groupname')
    //             ->leftJoin('chrngsetup','studledger.classid','=','chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where('studledger.amount', '>', 0)
    //             ->where(function($q) use($semid){
    //                 if(DB::table('schoolinfo')->first()->shssetup == 0)
    //                 {
    //                     $q->where('semid', $semid);
    //                 }
    //             })
    //             ->where('studledger.void', 0)
    //             ->where('studledger.deleted', 0)
    //             ->orderBy('studledger.createddatetime', 'asc')
    //             ->get();
    //     }
    //     elseif($levelid >= 17 && $levelid <= 21)
    //     {
    //         $ledger = db::table('studledger')
    //             ->select('studledger.*','chrngsetup.groupname')
    //             ->leftJoin('chrngsetup','studledger.classid','=','chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where('studledger.semid', $semid)
    //             ->where('studledger.classid', '!=', null)
    //             ->where('studledger.amount', '>', 0)
    //             ->where('studledger.deleted', 0)
    //             ->where('studledger.void', 0)
    //             ->orderBy('studledger.createddatetime', 'asc')
    //             ->get();
    //     }
    //     else
    //     {
    //         $ledger = db::table('studledger')
    //             ->select('studledger.*','chrngsetup.groupname')
    //             ->leftJoin('chrngsetup','studledger.classid','=','chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where('studledger.deleted', 0)
    //             ->where('studledger.void', 0)
    //             ->where('studledger.amount', '>', 0)
    //             ->orderBy('studledger.createddatetime', 'asc')
    //             ->get();
    //     }


    //     $bal = 0;
    //     $debit = 0;
    //     $credit = 0;

    //     if(strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'xai')
    //     {

    //         foreach($ledger as $led)
    //         {
    //             $debit += $led->amount;

    //             if($led->void == 0)
    //             {
    //                 $credit += $led->payment;
    //             }

    //             $lDate = date_create($led->createddatetime);
    //             $lDate = date_format($lDate, 'm-d-Y');

    //             if($led->amount > 0)
    //             {
    //                 $amount = number_format($led->amount,2);
    //             }
    //             else
    //             {
    //                 $amount = '';
    //             }

    //             if($led->payment > 0)
    //             {
    //                 $payment = number_format($led->payment,2);
    //             }
    //             else
    //             {
    //                 $payment = '';
    //             }

    //             if($led->void == 0)
    //             {
    //                 $bal += $led->amount - $led->payment;
    //             }

    //         }
    //         if($studinfo->levelid == 14 || $studinfo->levelid == 15)
    //         {
    //             $getPaySched = db::table('studpayscheddetail')
    //                 ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
    //                 ->join('itemclassification','studpayscheddetail.classid','=','itemclassification.id')
    //                 ->where('studid', $studid)
    //                 ->where('syid', $syid)
    //                 ->where(function($q) use($semid){
    //                     if(db::table('schoolinfo')->first()->shssetup == 0)
    //                     {
    //                         $q->where('semid', $semid);
    //                     }
    //                 })
    //                 ->where('studpayscheddetail.deleted', 0)

    //                 ->groupBy(db::raw('MONTH(duedate)'))
    //                 ->get();
    //                 // return $getPaySched;

    //         }
    //         else if($studinfo->levelid >= 17 && $studinfo->levelid <= 20)
    //         {

    //             $getPaySched = db::table('studpayscheddetail')
    //             ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
    //                 ->join('itemclassification','studpayscheddetail.classid','=','itemclassification.id')
    //                 ->where('studid', $studid)
    //                 ->where('syid', $syid)
    //                 ->where('semid', $semid)
    //                 ->where('studpayscheddetail.deleted', 0)
    //                 ->groupBy(db::raw('MONTH(duedate)'))
    //                 ->get();
    //             // $getPaySched = db::table('studpayscheddetail')
    //             // ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
    //             //     ->join('itemclassification','studpayscheddetail.classid','=','itemclassification.id')
    //             //     ->where('studid', $studid)
    //             //     ->where('syid', $syid)
    //             //     ->where('semid', $semid)
    //             //     ->where('deleted', 0)
    //             //     ->groupBy(db::raw('MONTH(duedate)'))
    //             //     ->get();
    //         }
    //         else
    //         {
    //             // $getPaySched = db::select('description, sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate
    //             //     from studpayscheddetail
    //             //     INNER JOIN itemclassification
    //             //     ON studpayscheddetail.`classid` = itemclassification.id
    //             //     where studid = ? and syid = ? and studpayscheddetail.deleted = 0
    //             //     group by MONTH(duedate)
    //             //     order by duedate', [$studid, $syid]);

    //             $getPaySched = db::table('studpayscheddetail')
    //                 ->select(db::raw('description1, sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate'))
    //                     ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
    //                     ->where('studid', $studid)
    //                     ->where('syid', $syid)
    //                     ->groupBy(db::raw('month(duedate)'))
    //                     ->orderBy('duedate');
    //         }
    //         $assessbilling = 0;
    //         $assesspayment = 0;
    //         $assessbalance = 0;
    //         $totalBal = collect($getPaySched)->sum('balance');;

    //         if(count($getPaySched) > 0)
    //         {
    //             foreach($getPaySched as $psched)
    //             {

    //                 // return $getPaySched;
    //                 // $totalBal += $psched->balance;
    //                 $assessbilling += $psched->amountdue;
    //                 $assesspayment += $psched->amountpay;
    //                 $assessbalance += $psched->balance;

    //                 $m = date_create($psched->duedate);
    //                 $f = date_format($m, 'F');
    //                 $m = date_format($m, 'm');

    //                 if($psched->duedate != '')
    //                 {
    //                     $particulars = 'PAYABLES FOR ' . strtoupper($f);
    //                 }
    //                 else
    //                 {
    //                     if(strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'faai')
    //                     {
    //                         $particulars = 'REGISTRATION/MISCELLANEOUS/BOOKS/GENYO';
    //                     }else{
    //                         $particulars = 'ONE-TIME PAYMENT';
    //                     }
    //                     $m = 0;
    //                 }
    //             }


    //             $monthname = date('M', strtotime('2020-'.$month));
    //             return view('finance.statementofaccount.table_xai')
    //                 ->with('studinfo', $studinfo)
    //                 ->with('monthname', $monthname)
    //                 ->with('ledger', $ledger)
    //                 ->with('getPaySched', $getPaySched);



    //         }else{

    //             $monthname = date('M', strtotime('2020-'.$month));
    //             return view('finance.statementofaccount.table_xai')
    //                 ->with('studinfo', $studinfo)
    //                 ->with('monthname', $monthname)
    //                 ->with('ledger', $ledger)
    //                 ->with('getPaySched', $getPaySched);
    //         }

    //     }else{

    //         $output = '<table class="table table-bordered" style="font-size: 12px;">
    //             <thead>
    //                 <tr>
    //                     <th colspan="5">LEDGER</th>
    //                 </tr>
    //                 <tr>
    //                     <th>Date</th>
    //                     <th>Description</th>
    //                     <th>Billing</th>
    //                     <th>Balance</th>
    //                 </tr>
    //             </thead>
    //             <tbody>';

    //         foreach($ledger as $led)
    //         {
    //             $debit += $led->amount;

    //             if($led->void == 0)
    //             {
    //                 $credit += $led->payment;
    //             }

    //             $lDate = date_create($led->createddatetime);
    //             $lDate = date_format($lDate, 'm-d-Y');

    //             if($led->amount > 0)
    //             {
    //                 $amount = number_format($led->amount,2);
    //             }
    //             else
    //             {
    //                 $amount = '';
    //             }

    //             if($led->payment > 0)
    //             {
    //                 $payment = number_format($led->payment,2);
    //             }
    //             else
    //             {
    //                 $payment = '';
    //             }

    //             if($led->void == 0)
    //             {
    //                 $bal += $led->amount - $led->payment;
    //             }

    //             if($led->void == 0)
    //             {
    //                 $output .='
    //                 <tr>
    //                     <td>' .$lDate.' </td>
    //                     <td>'.$led->particulars.'</td>
    //                     <td class="text-right">'.$amount.'</td>
    //                     <td class="text-right">'.number_format($bal, 2).'</td>
    //                 </tr>
    //                 ';
    //             }
    //             else
    //             {
    //                 $output .='
    //                 <tr>
    //                     <td class="text-danger"><del>' .$lDate.' </del></td>
    //                     <td class="text-danger"><del>'.$led->particulars.'</del></td>
    //                     <td class="text-right text-danger"><del>'.$amount.'</del></td>
    //                     <td class="text-right text-danger"><del>'.number_format($bal, 2).'</del></td>
    //                 </tr>
    //                 ';
    //             }

    //         }

    //         $output .='
    //         <tr style="background-color:#007bff91">
    //             <th></th>
    //             <th style="text-align:right">
    //                 <strong>TOTAL:<strong>
    //             </th>
    //             <th class="text-right">
    //                 <strong><u>'.number_format($debit, 2).'</u></strong>
    //             </th>
    //             <th class="text-right">
    //                 <strong><u>'.number_format($bal, 2).'</u></strong>
    //             </th>
    //         </tr>
    //         </tbody>
    //         <thead>
    //             <tr>
    //                 <th colspan="5">PAYMENT</th>
    //             </tr>
    //         </thead>
    //         <tbody>';
    //         $output .='
    //             <tr>
    //                 <th>Date</th>
    //                 <th>Description</th>
    //                 <th>Payment</th>
    //                 <th>Balance</th>
    //             </tr>
    //         ';

    //         // $assessment = FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $_monthid);
    //         $a_totalbill = 0;
    //         $a_totalpay = 0;
    //         $a_totalbalance = 0;
    //         $paybal = $bal;

    //         $payments = db::table('studledger')
    //             ->select('studledger.*','chrngsetup.groupname')
    //             ->leftJoin('chrngsetup','studledger.classid','=','chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where(function($q) use($semid, $levelid){
    //                 if($levelid == 14 || $levelid == 15)
    //                 {
    //                     if($semid == 3)
    //                     {
    //                         $q->where('semid', 3);
    //                     }
    //                     else{
    //                         if(DB::table('schoolinfo')->first()->shssetup == 0)
    //                         {
    //                             $q->where('semid', $semid);
    //                         }
    //                         else{
    //                             $q->where('semid', '!=', 3);
    //                         }
    //                     }
    //                 }
    //                 elseif($levelid >= 17 && $levelid <= 21)
    //                 {
    //                     $q->where('semid', $semid);
    //                 }
    //             })
    //             ->where('payment', '>', 0)
    //             ->where('studledger.deleted', 0)
    //             ->where('studledger.void', 0)
    //             ->orderBy('studledger.createddatetime', 'asc')
    //             ->get();

    //         foreach($payments as $pay)
    //         {
    //             // $_particulars = strtoupper($sched->particulars . ' PAYABLES');
    //             $paybal -= $pay->payment;
    //             $a_totalpay += $pay->payment;
    //             $a_totalbalance += $pay->payment;

    //             $lDate = date_create($pay->createddatetime);
    //             $lDate = date_format($lDate, 'm-d-Y');

    //             $output .='
    //                 <tr>
    //                     <td>' .$lDate.' </td>
    //                     <td>'.$pay->particulars.'</td>
    //                     <td class="text-right">'.number_format($pay->payment, 2).'</td>
    //                     <td class="text-right">'.number_format($paybal, 2).'</td>
    //                 </tr>
    //             ';
    //         }

    //         $output .='
    //             <tr style="background-color:#007bff91">
    //                 <th></th>
    //                 <th style="text-align:right">
    //                     <strong>TOTAL:<strong>
    //                 </th>
    //                 <th class="text-right">
    //                     <strong><u>'.number_format($a_totalpay, 2).'</u></strong>
    //                 </th>
    //                 <th class="text-right">
    //                     <strong><u>'.number_format($paybal, 2).'</u></strong>
    //                 </th>
    //             </tr>
    //             </tbody>
    //         ';

    //         //DUE FOR THE MONTH//
    //         $monthinword = '';
    //         $monthsetup = db::table('monthsetup')->where('id', $monthsetupid)->first();

    //         if($monthsetup)
    //         {
    //             $monthinword = $monthsetup->description;
    //         }
    //         else{
    //             $monthinword = '';
    //         }


    //         $monthdue = UtilityController::assessment_gen($request);
    //         // $monthdue =FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $monthsetup);
    //         $totaldue = 0;

    //         // return $monthdue;
    //         if($monthdue)
    //         {
    //             foreach($monthdue as $_due)
    //             {
    //                 // return $_due->amount;
    //                 $totaldue += str_replace(',', '', $_due->amount);
    //             }
    //         }

    //         $output .='
    //             <thead>
    //                 <tr style="background-color:#007bff91">
    //                     <th colspan="3" class="text-right text-lg">DUE FOR THE MONTH OF ' . strtoupper($monthinword) . ' </th>
    //                     <th class="text-right text-lg">'.number_format($totaldue, 2).'</th>
    //                 </tr>
    //             </thead>
    //         ';








    //     }

    //     if($action == 'generate')
    //     {
    //         return $output;
    //     }
    //     elseif($action == 'ep')
    //     {
    //         return $totaldue;
    //     }
    //     else{
    //         $pdf = new MYPDFStatementOfAccount(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    //         // $pdf->SetCreator('CK');
    //         // $pdf->SetAuthor('CK Children\'s Publishing');
    //         // $pdf->SetTitle(DB::table('schoolinfo')->first()->schoolname.' - Statement of Account');
    //         // $pdf->SetSubject('Statement of Account');

    //         // set header and footer fonts
    //         // $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    //         // $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    //         // // set default monospaced font
    //         // $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    //         // set margins
    //         $pdf->SetMargins(3, 10, 5);
    //         $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    //         $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    //         $pdf->setPrintFooter(false);


    //         // $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 4, 'color' => array(255, 0, 0)));
    //         // set auto page breaks
    //         $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    //         // set image scale factor
    //         $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    //         // set some language-dependent strings (optional)
    //         if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
    //             require_once(dirname(__FILE__).'/lang/eng.php');
    //             $pdf->setLanguageArray($l);
    //         }

    //         // if(strtolower($schoolinfo->abbreviation) == 'apmc')
    //         // {
    //         //     $pdf->setPrintHeader(false);
    //         // }


    //         // ---------------------------------------------------------

    //         // set font
    //         // $pdf->SetFont('dejavusans', '', 10);


    //         // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
    //         // Print a table

    //         $pdf->AddPage('8.5x11');



    //         $pdf = PDF::loadView('finance/reports/pdf/pdf_statementofacct_default_v2', compact('ledger', 'payments', 'monthsetup', 'monthdue', 'selectedschoolyear', 'selectedsemester', 'monthinword', 'studinfo', 'levelid', 'courseabrv', 'sectionname'))
    //             ->setPaper('letter');
    //         return $pdf->stream('Statement Of Account.pdf');
    //     }
    // }




    //working v2 code
    // public function getaccount_v2(Request $request)
    // {
    //     $studid = $request->get('studid');
    //     $semid = $request->get('selectedsemester');
    //     $syid = $request->get('selectedschoolyear');
    //     $monthsetupid = $request->get('selectedmonth');
    //     $action = $request->get('action');
    //     $month = null;
    //     $levelid = 0;
    //     $sectionid = 0;
    //     $courseid = 0;
    //     $sectionname = '';
    //     $courseabrv = '';



    //     if ($request->get('selectedmonth') > 0) {
    //         if (date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month']) {
    //             if (strlen(date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month']) == 1) {
    //                 $month = '0' . date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month'];
    //             } else {
    //                 $month = date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month'];
    //             }
    //         }
    //     }

    //     $request['syid'] = $syid;
    //     $request['semid'] = $semid;
    //     $request['monthid'] = $month;

    //     $selectedschoolyear = DB::table('sy')
    //         ->where('id', $syid)
    //         ->first()
    //         ->sydesc;

    //     $selectedsemester = db::table('semester')
    //         ->where('id', $semid)
    //         ->first()
    //         ->semester;

    //     $studinfo = db::table('studinfo')
    //         ->select('id', 'sid', 'lastname', 'firstname', 'middlename', 'suffix')
    //         ->where('studinfo.id', $studid)
    //         ->first();

    //     $estud = db::table('enrolledstud')
    //         ->select('levelid', 'sectionid', 'studstatus')
    //         ->where('studid', $studid)
    //         ->where('syid', $syid)
    //         ->where('deleted', 0)
    //         ->first();

    //     if ($estud) {
    //         $levelid = $estud->levelid;
    //         $sectionid = $estud->sectionid;

    //         $section = db::table('sections')
    //             ->where('id', $sectionid)
    //             ->first();

    //         if ($section) {
    //             $sectionname = $section->sectionname;
    //         }

    //     } else {
    //         $estud = db::table('sh_enrolledstud')
    //             ->select('levelid', 'sectionid', 'studstatus')
    //             ->where('studid', $studid)
    //             ->where('deleted', 0)
    //             ->first();

    //         if ($estud) {
    //             $levelid = $estud->levelid;
    //             $sectionid = $estud->sectionid;

    //             $section = db::table('sections')
    //                 ->where('id', $sectionid)
    //                 ->first();

    //             if ($section) {
    //                 $sectionname = $section->sectionname;
    //             }

    //         } else {
    //             $estud = db::table('college_enrolledstud')
    //                 ->select('yearLevel as levelid', 'courseid', 'studstatus')
    //                 ->where('studid', $studid)
    //                 ->where('deleted', 0)
    //                 ->first();

    //             if ($estud) {
    //                 $levelid = $estud->levelid;
    //                 $courseid = $estud->courseid;

    //                 $courses = db::table('college_courses')
    //                     ->where('id', $courseid)
    //                     ->first();

    //                 if ($courses) {
    //                     $courseabrv = $courses->courseabrv;
    //                 }
    //             } else {
    //                 $estud = db::table('studinfo')
    //                     ->select('levelid', 'studstatus')
    //                     ->where('id', $studid)
    //                     ->first();

    //                 if ($estud) {
    //                     $levelid = $estud->levelid;
    //                     $secionid = 0;
    //                 }
    //             }
    //         }
    //     }


    //     if ($levelid == 14 || $levelid == 15) {
    //         $ledger = db::table('studledger')
    //             ->selectRaw('studledger.*, chrngsetup.groupname')
    //             ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where('studledger.amount', '>', 0)
    //             ->where(function ($q) use ($semid) {
    //                 if (DB::table('schoolinfo')->first()->shssetup == 0) {
    //                     $q->where('semid', $semid);
    //                 }
    //             })
    //             ->where('studledger.void', 0)
    //             ->where('studledger.deleted', 0)
    //             ->groupBy('studledger.id')
    //             ->orderBy('studledger.createddatetime', 'asc')
    //             ->get();
    //     } elseif ($levelid >= 17 && $levelid <= 25) {
    //         $ledger = db::table('studledger')
    //             ->selectRaw('studledger.*, chrngsetup.groupname')
    //             ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where('studledger.semid', $semid)
    //             ->where('studledger.classid', '!=', null)
    //             ->where('studledger.amount', '>', 0)
    //             ->where('studledger.deleted', 0)
    //             ->where('studledger.void', 0)
    //             ->groupBy('studledger.id')
    //             ->orderBy('studledger.createddatetime', 'asc')
    //             ->get();
    //     } else {
    //         $ledger = db::table('studledger')
    //             ->selectRaw('studledger.*, chrngsetup.groupname')
    //             ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where('studledger.deleted', 0)
    //             ->where('studledger.void', 0)
    //             ->where('studledger.amount', '>', 0)
    //             ->groupBy('studledger.id')
    //             ->orderBy('studledger.createddatetime', 'asc')
    //             ->get();
    //     }


    //     $bal = 0;
    //     $debit = 0;
    //     $credit = 0;


    //     if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'xai') {

    //         foreach ($ledger as $led) {
    //             $debit += $led->amount;

    //             if ($led->void == 0) {
    //                 $credit += $led->payment;
    //             }

    //             $lDate = date_create($led->createddatetime);
    //             $lDate = date_format($lDate, 'm-d-Y');

    //             if ($led->amount > 0) {
    //                 $amount = number_format($led->amount, 2);
    //             } else {
    //                 $amount = '';
    //             }

    //             if ($led->payment > 0) {
    //                 $payment = number_format($led->payment, 2);
    //             } else {
    //                 $payment = '';
    //             }

    //             if ($led->void == 0) {
    //                 $bal += $led->amount - $led->payment;
    //             }

    //         }
    //         if ($studinfo->levelid == 14 || $studinfo->levelid == 15) {
    //             $getPaySched = db::table('studpayscheddetail')
    //                 ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
    //                 ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
    //                 ->where('studid', $studid)
    //                 ->where('syid', $syid)
    //                 ->where(function ($q) use ($semid) {
    //                     if (db::table('schoolinfo')->first()->shssetup == 0) {
    //                         $q->where('semid', $semid);
    //                     }
    //                 })
    //                 ->where('studpayscheddetail.deleted', 0)

    //                 ->groupBy(db::raw('MONTH(duedate)'))
    //                 ->get();
    //             // return $getPaySched;

    //         } else if ($studinfo->levelid >= 17 && $studinfo->levelid <= 20 || $studinfo->levelid >= 22 && $studinfo->levelid <= 25) {

    //             $getPaySched = db::table('studpayscheddetail')
    //                 ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
    //                 ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
    //                 ->where('studid', $studid)
    //                 ->where('syid', $syid)
    //                 ->where('semid', $semid)
    //                 ->where('studpayscheddetail.deleted', 0)
    //                 ->groupBy(db::raw('MONTH(duedate)'))
    //                 ->get();
    //             // $getPaySched = db::table('studpayscheddetail')
    //             // ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
    //             //     ->join('itemclassification','studpayscheddetail.classid','=','itemclassification.id')
    //             //     ->where('studid', $studid)
    //             //     ->where('syid', $syid)
    //             //     ->where('semid', $semid)
    //             //     ->where('deleted', 0)
    //             //     ->groupBy(db::raw('MONTH(duedate)'))
    //             //     ->get();
    //         } else {
    //             // $getPaySched = db::select('description, sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate
    //             //     from studpayscheddetail
    //             //     INNER JOIN itemclassification
    //             //     ON studpayscheddetail.`classid` = itemclassification.id
    //             //     where studid = ? and syid = ? and studpayscheddetail.deleted = 0
    //             //     group by MONTH(duedate)
    //             //     order by duedate', [$studid, $syid]);

    //             $getPaySched = db::table('studpayscheddetail')
    //                 ->select(db::raw('description1, sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate'))
    //                 ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
    //                 ->where('studid', $studid)
    //                 ->where('syid', $syid)
    //                 ->groupBy(db::raw('month(duedate)'))
    //                 ->orderBy('duedate');
    //         }
    //         $assessbilling = 0;
    //         $assesspayment = 0;
    //         $assessbalance = 0;
    //         $totalBal = collect($getPaySched)->sum('balance');
    //         ;

    //         if (count($getPaySched) > 0) {
    //             foreach ($getPaySched as $psched) {

    //                 // return $getPaySched;
    //                 // $totalBal += $psched->balance;
    //                 $assessbilling += $psched->amountdue;
    //                 $assesspayment += $psched->amountpay;
    //                 $assessbalance += $psched->balance;

    //                 $m = date_create($psched->duedate);
    //                 $f = date_format($m, 'F');
    //                 $m = date_format($m, 'm');

    //                 if ($psched->duedate != '') {
    //                     $particulars = 'PAYABLES FOR ' . strtoupper($f);
    //                 } else {
    //                     if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'faai') {
    //                         $particulars = 'REGISTRATION/MISCELLANEOUS/BOOKS/GENYO';
    //                     } else {
    //                         $particulars = 'ONE-TIME PAYMENT';
    //                     }
    //                     $m = 0;
    //                 }
    //             }


    //             $monthname = date('M', strtotime('2020-' . $month));
    //             return view('finance.statementofaccount.table_xai')
    //                 ->with('studinfo', $studinfo)
    //                 ->with('monthname', $monthname)
    //                 ->with('ledger', $ledger)
    //                 ->with('getPaySched', $getPaySched);



    //         } else {

    //             $monthname = date('M', strtotime('2020-' . $month));
    //             return view('finance.statementofaccount.table_xai')
    //                 ->with('studinfo', $studinfo)
    //                 ->with('monthname', $monthname)
    //                 ->with('ledger', $ledger)
    //                 ->with('getPaySched', $getPaySched);
    //         }

    //     } else {

    //         $output = '<table class="table table-bordered" style="font-size: 12px;">
    //             <thead>
    //                 <tr>
    //                     <th colspan="5">LEDGER</th>
    //                 </tr>
    //                 <tr>
    //                     <th>Date</th>
    //                     <th>Description</th>
    //                     <th>Billing</th>
    //                     <th>Balance</th>
    //                 </tr>
    //             </thead>
    //             <tbody>';

    //         foreach ($ledger as $led) {
    //             $debit += $led->amount;

    //             if ($led->void == 0) {
    //                 $credit += $led->payment;
    //             }

    //             $lDate = date_create($led->createddatetime);
    //             $lDate = date_format($lDate, 'm-d-Y');

    //             if ($led->amount > 0) {
    //                 $amount = number_format($led->amount, 2);
    //             } else {
    //                 $amount = '';
    //             }

    //             if ($led->payment > 0) {
    //                 $payment = number_format($led->payment, 2);
    //             } else {
    //                 $payment = '';
    //             }

    //             if ($led->void == 0) {
    //                 $bal += $led->amount - $led->payment;
    //             }

    //             if ($led->void == 0) {
    //                 $output .= '
    //                 <tr>
    //                     <td>' . $lDate . ' </td>
    //                     <td>' . $led->particulars . '</td>
    //                     <td class="text-right">' . $amount . '</td>
    //                     <td class="text-right">' . number_format($bal, 2) . '</td>
    //                 </tr>
    //                 ';
    //             } else {
    //                 $output .= '
    //                 <tr>
    //                     <td class="text-danger"><del>' . $lDate . ' </del></td>
    //                     <td class="text-danger"><del>' . $led->particulars . '</del></td>
    //                     <td class="text-right text-danger"><del>' . $amount . '</del></td>
    //                     <td class="text-right text-danger"><del>' . number_format($bal, 2) . '</del></td>
    //                 </tr>
    //                 ';
    //             }

    //         }

    //         $output .= '
    //         <tr style="background-color:#007bff91">
    //             <th></th>
    //             <th style="text-align:right">
    //                 <strong>TOTAL:<strong>
    //             </th>
    //             <th class="text-right">
    //                 <strong><u>' . number_format($debit, 2) . '</u></strong>
    //             </th>
    //             <th class="text-right">
    //                 <strong><u>' . number_format($bal, 2) . '</u></strong>
    //             </th>
    //         </tr>
    //         </tbody>
    //         <thead>
    //             <tr>
    //                 <th colspan="5">PAYMENT</th>
    //             </tr>
    //         </thead>
    //         <tbody>';
    //         $output .= '
    //             <tr>
    //                 <th>Date</th>
    //                 <th>Description</th>
    //                 <th>Payment</th>
    //                 <th>Balance</th>
    //             </tr>
    //         ';

    //         // $assessment = FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $_monthid);
    //         $a_totalbill = 0;
    //         $a_totalpay = 0;
    //         $a_totalbalance = 0;
    //         $paybal = $bal;

    //         $payments = db::table('studledger')
    //             ->select('studledger.*', 'chrngsetup.groupname')
    //             ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where(function ($q) use ($semid, $levelid) {
    //                 if ($levelid == 14 || $levelid == 15) {
    //                     if ($semid == 3) {
    //                         $q->where('semid', 3);
    //                     } else {
    //                         if (DB::table('schoolinfo')->first()->shssetup == 0) {
    //                             $q->where('semid', $semid);
    //                         } else {
    //                             $q->where('semid', '!=', 3);
    //                         }
    //                     }
    //                 } elseif ($levelid >= 17 && $levelid <= 25) {
    //                     $q->where('semid', $semid);
    //                 }
    //             })
    //             ->where('payment', '>', 0)
    //             ->where('studledger.deleted', 0)
    //             ->where('studledger.void', 0)
    //             ->orderBy('studledger.createddatetime', 'asc')
    //             ->get();

    //         foreach ($payments as $pay) {
    //             // $_particulars = strtoupper($sched->particulars . ' PAYABLES');
    //             $paybal -= $pay->payment;
    //             $a_totalpay += $pay->payment;
    //             $a_totalbalance += $pay->payment;

    //             $lDate = date_create($pay->createddatetime);
    //             $lDate = date_format($lDate, 'm-d-Y');

    //             $output .= '
    //                 <tr>
    //                     <td>' . $lDate . ' </td>
    //                     <td>' . $pay->particulars . '</td>
    //                     <td class="text-right">' . number_format($pay->payment, 2) . '</td>
    //                     <td class="text-right">' . number_format($paybal, 2) . '</td>
    //                 </tr>
    //             ';
    //         }


    //         $output .= '
    //             <tr style="background-color:#007bff91">
    //                 <th></th>
    //                 <th style="text-align:right">
    //                     <strong>TOTAL:<strong>
    //                 </th>
    //                 <th class="text-right">
    //                     <strong><u>' . number_format($a_totalpay, 2) . '</u></strong>
    //                 </th>
    //                 <th class="text-right">
    //                     <strong><u>' . number_format($paybal, 2) . '</u></strong>
    //                 </th>
    //             </tr>
    //             </tbody>
    //         ';
    //         //DUE FOR THE MONTH//
    //         $monthinword = '';
    //         $monthsetup = db::table('monthsetup')->where('id', $monthsetupid)->first();

    //         if ($monthsetup) {
    //             $monthinword = $monthsetup->description;
    //         } else {
    //             $monthinword = '';
    //         }


    //         $monthdue = UtilityController::assessment_gen($request);

    //         // $monthdue =FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $monthsetup);
    //         $totaldue = 0;
    //         // return $monthdue;
    //         if ($monthdue) {
    //             foreach ($monthdue as $_due) {
    //                 // return $_due->amount;
    //                 $totaldue += str_replace(',', '', $_due->amount);
    //             }
    //         }

    //         $output .= '
    //             <thead>
    //                 <tr style="background-color:#007bff91">
    //                     <th colspan="3" class="text-right text-lg">DUE FOR THE MONTH OF ' . strtoupper($monthinword) . ' </th>
    //                     <th class="text-right text-lg">' . number_format($totaldue, 2) . '</th>
    //                 </tr>
    //             </thead>
    //         ';








    //     }

    //     if ($action == 'generate') {
    //         return $output;
    //     } elseif ($action == 'ep') {
    //         return $totaldue;
    //     } else {
    //         $pdf = new MYPDFStatementOfAccount(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    //         // $pdf->SetCreator('CK');
    //         // $pdf->SetAuthor('CK Children\'s Publishing');
    //         // $pdf->SetTitle(DB::table('schoolinfo')->first()->schoolname.' - Statement of Account');
    //         // $pdf->SetSubject('Statement of Account');

    //         // set header and footer fonts
    //         // $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    //         // $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    //         // // set default monospaced font
    //         // $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    //         // set margins
    //         $pdf->SetMargins(3, 10, 5);
    //         $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    //         $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    //         $pdf->setPrintFooter(false);


    //         // $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 4, 'color' => array(255, 0, 0)));
    //         // set auto page breaks
    //         $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    //         // set image scale factor
    //         $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    //         // set some language-dependent strings (optional)
    //         if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
    //             require_once(dirname(__FILE__) . '/lang/eng.php');
    //             $pdf->setLanguageArray($l);
    //         }

    //         // if(strtolower($schoolinfo->abbreviation) == 'apmc')
    //         // {
    //         //     $pdf->setPrintHeader(false);
    //         // }


    //         // ---------------------------------------------------------

    //         // set font
    //         // $pdf->SetFont('dejavusans', '', 10);


    //         // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
    //         // Print a table

    //         $pdf->AddPage('8.5x11');



    //         $pdf = PDF::loadView('finance/reports/pdf/pdf_statementofacct_default_v2', compact('ledger', 'payments', 'monthsetup', 'monthdue', 'selectedschoolyear', 'selectedsemester', 'monthinword', 'studinfo', 'levelid', 'courseabrv', 'sectionname'))
    //             ->setPaper('letter');
    //         return $pdf->stream('Statement Of Account.pdf');
    //     }
    // }


    public function getaccount_v2(Request $request)
    {
        $studid = $request->get('studid');
        $semid = $request->get('selectedsemester');
        $syid = $request->get('selectedschoolyear');
        $monthsetupid = $request->get('selectedmonth');
        $action = $request->get('action');
        $monthdesc = $request->get('monthdesc');

        // Get basic info
        $selectedschoolyear = DB::table('sy')->where('id', $syid)->first()->sydesc;
        $selectedsemester = DB::table('semester')->where('id', $semid)->first()->semester;
        $studinfo = DB::table('studinfo')
            ->select('id', 'sid', 'lastname', 'firstname', 'middlename', 'suffix')
            ->where('id', $studid)
            ->first();

        // Get enrollment info and determine level, section, course
        [$levelid, $sectionname, $courseabrv] = $this->getEnrollmentInfo($studid, $syid, $semid);

        // Get ledger data based on level
        $ledger = $this->getLedgerData($studid, $syid, $semid, $levelid);

        $bal = 0;
        $debit = 0;
        $credit = 0;

        // Check if this is XAI school
        if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'xai') {
            return $this->handleXaiSchool($ledger, $studinfo, $semid, $studid, $syid);
        }

        // Handle regular schools
        $output = $this->buildLedgerTable($ledger, $bal, $debit, $credit);
        $output .= $this->buildPaymentTable($studid, $syid, $semid, $levelid, $bal, $debit, $credit);
        $output .= $this->buildMonthDueSection($studid, $syid, $semid, $monthsetupid, $action, $monthdesc, $credit);

        if ($action == 'generate') {
            return $output;
        } elseif ($action == 'ep') {
            $monthdue = FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $monthsetupid, $action);
            $totaldue = 0;
            if ($monthdue) {
                foreach ($monthdue as $_due) {
                    $totaldue += str_replace(',', '', $_due->balance);
                }
            }
            return $totaldue;
        } else {
            return $this->generatePDF($ledger, $studid, $syid, $semid, $monthsetupid, $action, $monthdesc, 
                                    $selectedschoolyear, $selectedsemester, $studinfo, $levelid, $courseabrv, $sectionname);
        }
    }

    private function getEnrollmentInfo($studid, $syid, $semid)
    {
        $levelid = 0;
        $sectionname = '';
        $courseabrv = '';

        // Try enrolledstud first
        $estud = DB::table('enrolledstud')
            ->select('levelid', 'sectionid')
            ->where('studid', $studid)
            ->where('syid', $syid)
            ->where('deleted', 0)
            ->first();

        if ($estud) {
            $levelid = $estud->levelid;
            $section = DB::table('sections')->where('id', $estud->sectionid)->first();
            $sectionname = $section ? $section->sectionname : '';
        } else {
            // Try sh_enrolledstud
            $estud = DB::table('sh_enrolledstud')
                ->select('levelid', 'sectionid')
                ->where('studid', $studid)
                ->where('deleted', 0)
                ->where('syid', $syid)
                ->where(function ($query) use ($semid) {
                    if(DB::table('schoolinfo')->first()->shssetup == 0) {
                        $query->where('semid', $semid);
                    }
                })
                ->first();

            if ($estud) {
                $levelid = $estud->levelid;
                $section = DB::table('sections')->where('id', $estud->sectionid)->first();
                $sectionname = $section ? $section->sectionname : '';
            } else {
                // Try college_enrolledstud
                $estud = DB::table('college_enrolledstud')
                    ->select('yearLevel as levelid', 'courseid')
                    ->where('studid', $studid)
                    ->where('deleted', 0)
                    ->where('syid', $syid)
                    ->where('semid', $semid)
                    ->first();

                if ($estud) {
                    $levelid = $estud->levelid;
                    $course = DB::table('college_courses')->where('id', $estud->courseid)->first();
                    $courseabrv = $course ? $course->courseabrv : '';
                } else {
                    // Fallback to studinfo
                    $estud = DB::table('studinfo')->select('levelid')->where('id', $studid)->first();
                    $levelid = $estud ? $estud->levelid : 0;
                }
            }
        }

        return [$levelid, $sectionname, $courseabrv];
    }

    private function getLedgerData($studid, $syid, $semid, $levelid)
    {
        $baseQuery = DB::table('studledger')
            ->select('studledger.*', 'chrngsetup.groupname')
            ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
            ->where('studledger.studid', $studid)
            ->where('studledger.syid', $syid)
            ->where('studledger.amount', '>', 0)
            ->where('studledger.void', 0)
            ->where('studledger.deleted', 0)
            ->orderBy('studledger.createddatetime', 'asc');

        if ($levelid == 14 || $levelid == 15) {
            $ledger = $baseQuery
                ->where(function ($q) use ($semid) {
                    if (DB::table('schoolinfo')->first()->shssetup == 0) {
                        $q->where('semid', $semid);
                    }
                })
                ->groupBy('studledger.id')
                ->get();
        } elseif ($levelid >= 17 && $levelid <= 21) {
            $ledger = $baseQuery
                ->where('studledger.semid', $semid)
                ->whereNotNull('studledger.classid')
                ->groupBy('studledger.id')
                ->get();

            // Handle back accounts for college
            // $ledger_backacc = DB::table('studledger')
            //     ->where('studid', $studid)
            //     ->where('syid', $syid)
            //     ->where('semid', $semid)
            //     ->whereNotNull('classid')
            //     ->where('amount', '>', 0)
            //     ->where('deleted', 0)
            //     ->where('void', 0)
            //     ->where('particulars', 'like', '%BACK ACCOUNTS%')
            //     ->get();

            // foreach ($ledger as $value) {
            //     if ($value->classid) {
            //         $back_adj = $ledger_backacc->where('classid', $value->classid)->first();
            //         if ($back_adj) {
            //             $value->amount += $back_adj->amount;
            //             $value->payment += $back_adj->payment;
            //             $value->balance = $value->amount - $value->payment;
            //         }
            //     }
            // }
        } else {
            // Get main ledger entries (excluding ADJ)
            $ledger = $baseQuery
                ->where('particulars', 'not like', '%ADJ%')
                ->groupBy('studledger.id')
                ->get();

            // Get adjustment entries as separate items
            $ledger_adj = DB::table('studledger')
                ->select('studledger.*', 'chrngsetup.groupname')
                ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                ->where('studledger.studid', $studid)
                ->where('studledger.syid', $syid)
                ->where('studledger.deleted', 0)
                ->where('studledger.void', 0)
                ->where('studledger.particulars', 'like', '%ADJ%')
                ->where('studledger.amount', '>', 0)
                ->orderBy('studledger.createddatetime', 'asc')
                ->get();

            // Add adjustment entries to the ledger collection
            foreach ($ledger_adj as $adj) {
                $ledger->push($adj);
            }

            // Sort by creation date again after adding adjustments
            $ledger = $ledger->sortBy('createddatetime')->values();
        }

        return $ledger;
    }

    private function handleXaiSchool($ledger, $studinfo, $semid, $studid, $syid)
    {
        $bal = 0;
        $debit = 0;
        $credit = 0;

        foreach ($ledger as $led) {
            $debit += $led->amount;
            if ($led->void == 0) {
                $credit += $led->payment;
                $bal += $led->amount - $led->payment;
            }
        }

        // Get payment schedule based on level
        if ($studinfo->levelid == 14 || $studinfo->levelid == 15) {
            $getPaySched = DB::table('studpayscheddetail')
                ->selectRaw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description')
                ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
                ->where('studid', $studid)
                ->where('syid', $syid)
                ->where(function ($q) use ($semid) {
                    if (DB::table('schoolinfo')->first()->shssetup == 0) {
                        $q->where('semid', $semid);
                    }
                })
                ->where('studpayscheddetail.deleted', 0)
                ->groupBy(DB::raw('MONTH(duedate)'))
                ->get();
        } elseif ($studinfo->levelid >= 17 && $studinfo->levelid <= 20) {
            $getPaySched = DB::table('studpayscheddetail')
                ->selectRaw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description')
                ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
                ->where('studid', $studid)
                ->where('syid', $syid)
                ->where('semid', $semid)
                ->where('studpayscheddetail.deleted', 0)
                ->groupBy(DB::raw('MONTH(duedate)'))
                ->get();
        } else {
            $getPaySched = DB::table('studpayscheddetail')
                ->selectRaw('description1, sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate')
                ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
                ->where('studid', $studid)
                ->where('syid', $syid)
                ->groupBy(DB::raw('month(duedate)'))
                ->orderBy('duedate')
                ->get();
        }

        $monthname = date('M');
        return view('finance.statementofaccount.table_xai')
            ->with('studinfo', $studinfo)
            ->with('monthname', $monthname)
            ->with('ledger', $ledger)
            ->with('getPaySched', $getPaySched);
    }

    private function buildLedgerTable($ledger, &$bal, &$debit, &$credit)
    {
        $output = '<table class="table table-bordered" style="font-size: 12px;">
            <thead>
                <tr><th colspan="5">LEDGER</th></tr>
                <tr><th>Date</th><th>Description</th><th>Billing</th><th>Balance</th></tr>
            </thead>
            <tbody>';

        foreach ($ledger as $led) {
            $debit += $led->amount;
            if ($led->void == 0) {
                $credit += $led->payment;
                $bal += $led->amount - $led->payment;
            }

            $lDate = date_format(date_create($led->createddatetime), 'm-d-Y');
            $amount = $led->amount > 0 ? number_format($led->amount, 2) : '';

            if ($led->void == 0) {
                $output .= '<tr>
                    <td>' . $lDate . '</td>
                    <td>' . $led->particulars . '</td>
                    <td class="text-right">' . $amount . '</td>
                    <td class="text-right">' . number_format($bal, 2) . '</td>
                </tr>';
            } else {
                $output .= '<tr>
                    <td class="text-danger"><del>' . $lDate . '</del></td>
                    <td class="text-danger"><del>' . $led->particulars . '</del></td>
                    <td class="text-right text-danger"><del>' . $amount . '</del></td>
                    <td class="text-right text-danger"><del>' . number_format($bal, 2) . '</del></td>
                </tr>';
            }
        }

        $output .= '<tr style="background-color:#007bff91">
            <th></th>
            <th style="text-align:right"><strong>TOTAL:</strong></th>
            <th class="text-right"><strong><u>' . number_format($debit, 2) . '</u></strong></th>
            <th class="text-right"><strong><u>' . number_format($bal, 2) . '</u></strong></th>
        </tr>
        </tbody>';

        return $output;
    }

    private function buildPaymentTable($studid, $syid, $semid, $levelid, $bal, &$debit, &$credit)
    {
        $output = '<thead>
            <tr><th colspan="5">PAYMENT</th></tr>
            <tr><th>Date</th><th>Description</th><th>Payment</th><th>Balance</th></tr>
        </thead>
        <tbody>';

        $paybal = $bal;
        $a_totalpay = 0;

        $payments = DB::table('studledger')
            ->select('studledger.*')
            ->where('studid', $studid)
            ->where('syid', $syid)
            ->where(function ($q) use ($semid, $levelid) {
                if ($levelid == 14 || $levelid == 15) {
                    // For SHS, filter by semester properly
                    $q->where('semid', $semid);
                } elseif ($levelid >= 17 && $levelid <= 21) {
                    // For college, filter by semester
                    $q->where('semid', $semid);
                } else {
                    // For other levels, filter by semester
                    $q->where('semid', $semid);
                }
            })
            ->where('payment', '>', 0)
            ->where('deleted', 0)
            ->where('void', 0)
            ->orderBy('createddatetime', 'asc')
            ->get();

        foreach ($payments as $pay) {
            $paybal -= $pay->payment;
            $a_totalpay += $pay->payment;
            $lDate = date_format(date_create($pay->createddatetime), 'm-d-Y');

            $output .= '<tr>
                <td>' . $lDate . '</td>
                <td>' . $pay->particulars . '</td>
                <td class="text-right">' . number_format($pay->payment, 2) . '</td>
                <td class="text-right">' . number_format($paybal, 2) . '</td>
            </tr>';
        }

        $output .= '<tr style="background-color:#007bff91">
            <th></th>
            <th style="text-align:right"><strong>TOTAL:</strong></th>
            <th class="text-right"><strong><u>' . number_format($a_totalpay, 2) . '</u></strong></th>
            <th class="text-right"><strong><u>' . number_format($paybal, 2) . '</u></strong></th>
        </tr>
        </tbody>';

        $credit = $a_totalpay;
        return $output;
    }

    private function buildMonthDueSection($studid, $syid, $semid, $monthsetupid, $action, $monthdesc, $a_totalpay)
    {
        $monthdue = FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $monthsetupid, $action);
        $totaldue = 0;

        if ($monthdue) {
            foreach ($monthdue as $_due) {
                $totaldue += str_replace(',', '', $_due->balance);
            }
        }

        return '<thead>
            <tr style="background-color:#007bff91">
                <th colspan="3" class="text-right text-lg">DUE FOR THE MONTH OF ' . strtoupper($monthdesc) . '</th>
                <th class="text-right text-lg">' . number_format(($totaldue - $a_totalpay), 2) . '</th>
            </tr>
        </thead>';
    }

    private function generatePDF($ledger, $studid, $syid, $semid, $monthsetupid, $action, $monthdesc, 
                                $selectedschoolyear, $selectedsemester, $studinfo, $levelid, $courseabrv, $sectionname)
    {
        $monthsetup = (object) ['monthid' => $monthsetupid, 'description' => $monthdesc];
        $monthdue = FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $monthsetupid, $action);
        $monthinword = $monthdesc;

        $payments = DB::table('studledger')
            ->where('studid', $studid)
            ->where('syid', $syid)
            ->where(function ($q) use ($semid, $levelid) {
                if ($levelid == 14 || $levelid == 15) {
                    // For SHS, filter by semester properly
                    $q->where('semid', $semid);
                } elseif ($levelid >= 17 && $levelid <= 21) {
                    // For college, filter by semester
                    $q->where('semid', $semid);
                } else {
                    // For other levels, filter by semester
                    $q->where('semid', $semid);
                }
            })
            ->where('payment', '>', 0)
            ->where('deleted', 0)
            ->where('void', 0)
            ->get();

        $pdf = new MYPDFStatementOfAccount(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetMargins(3, 10, 5);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->AddPage('8.5x11');

        $view = strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'taborin' 
            ? 'finance/reports/pdf/pdf_statementofacct_default_v2_gtc'
            : 'finance/reports/pdf/pdf_statementofacct_default_v2';

        $pdf = PDF::loadView($view, compact('ledger', 'payments', 'monthsetup', 'monthdue', 
            'selectedschoolyear', 'selectedsemester', 'monthinword', 'studinfo', 'levelid', 
            'courseabrv', 'sectionname', 'syid', 'semid'))->setPaper('letter');

        return $pdf->stream('Statement Of Account.pdf');
    }

    public function getIndividualStatements(Request $request)
    {
        $semid = $request->get('selectedsemester');
        $syid = $request->get('selectedschoolyear');
        $monthsetupid = $request->get('selectedmonth');
        $levelid = $request->get('levelid');
        $courseid = $request->get('courseid');
        $studid = $request->get('studid');

        $month = null;
        if ($request->get('selectedmonth', 0) > 0) {
            $monthData = DB::table('monthsetup')->where('id', $monthsetupid)->first();
            if ($monthData && isset($monthData->description)) {
                $parsedMonth = date_parse($monthData->description);
                if (isset($parsedMonth['month']) && $parsedMonth['month']) {
                    $monthNum = $parsedMonth['month'];
                    $month = strlen($monthNum) == 1 ? '0' . $monthNum : $monthNum;
                }
            }
        }

        $schoolinfo = DB::table('schoolinfo')->first();
        $balforwardsetup = DB::table('balforwardsetup')->first();
        $oldclassid = $balforwardsetup->classid ?? null;

        if($levelid == 14 || $levelid == 15)
        {
            $students = DB::table('sh_enrolledstud')
                ->select('studinfo.id', 'studinfo.sid', 'studinfo.lastname', 'studinfo.firstname',
                    'studinfo.middlename', 'studinfo.suffix', 'sh_enrolledstud.levelid', 'sh_enrolledstud.dateenrolled',
                    'sh_enrolledstud.sectionid', 'sections.sectionname','sh_enrolledstud.feesid')
                ->join('studinfo', 'sh_enrolledstud.studid', '=', 'studinfo.id')
                ->leftJoin('sections', 'sh_enrolledstud.sectionid', '=', 'sections.id')
                ->where('sh_enrolledstud.syid', $syid)
                ->where('sh_enrolledstud.semid', $semid)
                ->where('sh_enrolledstud.studid', $studid)
                ->where('sh_enrolledstud.deleted', 0)
                ->where('sh_enrolledstud.studstatus', '!=', 4)
                ->when($levelid, function($q) use ($levelid) {
                    return $q->where('sh_enrolledstud.levelid', $levelid);
                })
                ->get();
        }
        elseif($levelid >= 17 && $levelid <= 21)
        {
            $students = DB::table('college_enrolledstud')
                ->select('studinfo.id', 'studinfo.sid', 'studinfo.lastname', 'studinfo.firstname',
                    'studinfo.middlename', 'studinfo.suffix', 'college_enrolledstud.yearLevel as levelid', 'college_enrolledstud.date_enrolled as dateenrolled',
                    'college_enrolledstud.courseid', 'college_courses.courseabrv','college_enrolledstud.feesid')
                ->join('studinfo', 'college_enrolledstud.studid', '=', 'studinfo.id')
                ->join('college_courses', 'college_enrolledstud.courseid', '=', 'college_courses.id')
                ->where('college_enrolledstud.syid', $syid)
                ->where('college_enrolledstud.semid', $semid)
                ->where('college_enrolledstud.studid', $studid)
                ->where('college_enrolledstud.deleted', 0)
                ->where('college_enrolledstud.studstatus', '!=', 4)
                ->when($levelid, function($q) use ($levelid) {
                    return $q->where('college_enrolledstud.yearLevel', $levelid);
                })
                ->when($courseid, function($q) use ($courseid) {
                    return $q->where('college_enrolledstud.courseID', $courseid);
                })
                ->get();
        }
        else
        {
            $semid = 1;
            $students = DB::table('enrolledstud')
                ->select('studinfo.id', 'studinfo.sid', 'studinfo.lastname', 'studinfo.firstname',
                    'studinfo.middlename', 'studinfo.suffix', 'enrolledstud.levelid', 'enrolledstud.dateenrolled',
                    'enrolledstud.sectionid', 'sections.sectionname','enrolledstud.feesid')
                ->join('studinfo', 'enrolledstud.studid', '=', 'studinfo.id')
                ->leftJoin('sections', 'enrolledstud.sectionid', '=', 'sections.id')
                ->where('enrolledstud.syid', $syid)
                ->where('enrolledstud.studid', $studid)
                ->where('enrolledstud.deleted', 0)
                ->where('enrolledstud.studstatus', '!=', 4)
                ->when($levelid, function($q) use ($levelid) {
                    return $q->where('enrolledstud.levelid', $levelid);
                })
                ->get();

        }

        $selectedschoolyear = optional(DB::table('sy')->where('id', $syid)->first())->sydesc ?? '';
        $selectedsemester = optional(DB::table('semester')->where('id', $semid)->first())->semester ?? '';

        $acadid = DB::table('gradelevel')->get()->keyBy('id');
        $ranges = DB::table('month_range_setup')->where('deleted', 0)->get()->keyBy('acadprogid');

        $statements = [];
        $currentYear = date('Y');
    
        foreach ($students as $student) {
            $levelid = $student->levelid ?? 0;
            $studid = $student->id ?? 0;

            if (!$studid) continue;

            $gradeLevel = $acadid->get($levelid);
            $acadprogid = $gradeLevel->acadprogid ?? null;
            
            $range = $ranges->get($acadprogid);

            $studentFessTotal = 0;
            $studentPaymentsTotal = 0;
            $total_discount = 0;
            $total_adjustment_debit = 0;
            $total_adjustment_credit = 0;
            $totalcharges = 0;
            $totalpayment = 0;
            $totaldiscount = 0;

            $classidArray = [];
            $studentAllFees = collect();
            $studentAllPayments = collect();

            // ----------------------------------------------------------

            $tuition = db::table('tuitionheader')
                ->select('tuitionheader.id', 'tuitiondetail.id as tuitiondetailid', 'syid', 'levelid', 'grantee.description', 'itemclassification.description as particulars', 'amount', 'itemclassification.id as classid', 'pschemeid', 'semid', 'istuition', 'persubj', 'permop', 'permopid')
                ->leftjoin('tuitiondetail', 'tuitionheader.id', '=', 'tuitiondetail.headerid')
                ->join('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
                ->join('grantee', 'tuitionheader.grantee', '=', 'grantee.id')
                ->where('tuitionheader.id', $student->feesid)
                ->where('tuitiondetail.deleted', 0)
                ->where('pschemeid', '!=', null)
                ->get();
        
            if (count($tuition) == 0) {
                $_fees = db::table('tuitionheader')
                    ->where('syid', $syid)
                    ->where(function ($q) use ($levelid, $semid) {
                        if ($levelid >= 17 && $levelid <= 25) {
                            $q->where('semid', $semid);
                        }
                    })
                    ->where('levelid', $levelid)
                    ->where('deleted', 0)
                    ->first();

                if ($_fees) {
                    $feesid = $_fees->id;

                    $tuition = db::table('tuitionheader')
                        ->select('tuitionheader.id', 'tuitiondetail.id as tuitiondetailid', 'syid', 'levelid', 'grantee.description', 'itemclassification.description as particulars', 'amount', 'itemclassification.id as classid', 'pschemeid', 'semid', 'istuition', 'persubj', 'permop', 'permopid')
                        ->leftjoin('tuitiondetail', 'tuitionheader.id', '=', 'tuitiondetail.headerid')
                        ->join('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
                        ->join('grantee', 'tuitionheader.grantee', '=', 'grantee.id')
                        ->where('tuitionheader.id', $student->feesid)
                        ->where('tuitiondetail.deleted', 0)
                        ->get();
                }
            }

            if ($levelid >= 17 && $levelid <= 25) {
                $ausubjects = db::table('tuition_assessmentunit')
                    ->where('deleted', 0)
                    ->get();

                $college_subjects = DB::table('college_subjects')
                    ->where('deleted', 0)
                    ->get();

                $college_prospectus = DB::table('college_prospectus')
                    ->where('deleted', 0)
                    ->get();

                $college_subjects = collect($college_subjects);
                $college_prospectus = collect($college_prospectus);
                $ausubjects = collect($ausubjects);
                // $totalunits = db::table('college_studsched')
                //     ->select(db::raw('SUM(lecunits) + SUM(labunits) AS totalunits'))
                //     ->join('college_classsched', 'college_studsched.schedid', '=', 'college_classsched.id')
                //     ->join('college_prospectus', 'college_classsched.subjectID', '=', 'college_prospectus.id')
                //     ->join('college_sections', 'college_classsched.sectionID', '=', 'college_sections.id')
                //     ->where('college_studsched.studid', $studid)
                //     ->where('college_studsched.deleted', 0)
                //     ->where('college_classsched.deleted', 0)
                //     ->where('college_classsched.syID', $syid)
                //     ->where('college_classsched.semesterID', $semid)
                //     ->where('college_sections.section_specification', '!=', 2)
                //     ->where('college_studsched.schedstatus', '!=', 'DROPPED')
                //     ->first();

                $units = 0;
                $subjcount = 0;
                $finance = null;
                $studentloads = CollegeStudentLoadingController::getAddedStudentLoading($studid, 'all', $syid, $semid, 0, null, null);
            
                $studentloads = collect($studentloads);
                $studentloads = $studentloads['original']['studentLoading'];
                $studentloads = collect($studentloads)->where('loadStatus', 0)->values();
                foreach ($studentloads as $loads) {
                    // $units += $loads['totalUnits'];
                    // $subjcount += 1;

                    $prospectus = $college_prospectus->where('id', $loads['subjectID'])->first();

                    if ($prospectus) {
                        $getsubject = $college_subjects->where('id', $prospectus->subjectID)->first();
                        if ($getsubject) {
                            $getau = $ausubjects->where('subjid', $getsubject->id)->first();
                            if ($getau) {
                                $units += $getau->assessmentunit;
                            } else {
                                $units += $loads['lecunits'] + $loads['labunits'];
                            }
                        }
                    } else {
                        $units += $loads['lecunits'] + $loads['labunits'];
                    }
                }
            }
            // ----------------------------------------------------------

            $student_fees = DB::table('tuitionheader')
                ->select('tuitiondetail.classificationid','tuitiondetail.classificationid','itemclassification.description as particulars', 'tuitiondetail.amount', 'tuitiondetail.istuition', 'tuitiondetail.persubj', 'tuitiondetail.permop', 'tuitiondetail.permopid')
                ->join('tuitiondetail', 'tuitionheader.id', '=', 'tuitiondetail.headerid')
                ->join('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
                ->where('tuitionheader.deleted', 0)
                ->where('tuitiondetail.deleted', 0)
                ->where('tuitionheader.id', $student->feesid)
                ->where('tuitionheader.syid', $syid)
                // ->where('tuitionheader.semid', $semid)
                // ->where('tuitionheader.levelid', $levelid)
                ->get()
                ->map(function($item) use ($student) {
                    $item->createddatetime = $student->dateenrolled;
                    return $item;
                });
          
            foreach ($student_fees as $tui) {
                if ($levelid >= 17 && $levelid <= 25) {
                $totalunits = 0;

                if ($tui->istuition == 1) {
                    // echo $tui->amount . ' * ' . $units;
                    $tuitionamount = $units > 0 ? $tui->amount * $units : $tui->amount;
                    $college_particulars = ' | ' . $units . ' Units';
                } else {
                    $tuitionamount = $tui->amount;
                    $college_particulars = '';
                }

                if ($tui->persubj == 1) {

                    $totalsubj = DB::table('college_loadsubject')
                        ->where('college_loadsubject.studid', $studid)
                        ->where('college_loadsubject.deleted', 0)
                        ->join('college_enrolledstud', 'college_loadsubject.studid', 'college_enrolledstud.studid')
                        ->where('college_enrolledstud.studstatus', '!=', 'DROPPED')
                        ->join('college_sections', function ($join) {
                            $join->on('college_loadsubject.sectionID', '=', 'college_sections.id');
                            $join->where('college_sections.deleted', 0);
                        })
                        ->join('college_prospectus', function ($join) {
                            $join->on('college_loadsubject.subjectID', '=', 'college_prospectus.id');
                            $join->where('college_loadsubject.deleted', 0);
                        })
                        ->join('college_classsched', function ($join) use ($syid, $semid) {
                            $join->on('college_sections.id', '=', 'college_classsched.sectionid');
                            $join->on('college_prospectus.id', '=', 'college_classsched.subjectID');
                            $join->where('college_classsched.syid', $syid);
                            $join->where('college_classsched.deleted', 0);
                            $join->where('college_classsched.semesterID', $semid);
                        })
                        
                        ->leftJoin('college_scheddetail', function ($join) {
                            $join->on('college_classsched.id', '=', 'college_scheddetail.headerid');
                            $join->where('college_scheddetail.deleted', 0);
                        })
                        ->leftJoin('days', function ($join) {
                            $join->on('college_scheddetail.day', '=', 'days.id');
                        })
                        ->leftJoin('college_instructor', function ($join) {
                            $join->where('college_instructor.deleted', 0);
                            $join->on('college_classsched.id', '=', 'college_instructor.classschedid');
                        })
                        ->leftJoin('teacher', function ($join) {
                            $join->on('college_instructor.teacherID', '=', 'teacher.id');
                        })
                        ->leftJoin('rooms', function ($join) {
                            $join->on('college_scheddetail.roomid', '=', 'rooms.id');
                            $join->where('rooms.deleted', 0);
                        })
                        ->select(
                            'days.id as daysort',
                            'days.description',
                            'college_classsched.id',
                            'college_scheddetail.id as schedid',
                            'college_scheddetail.etime',
                            'college_scheddetail.stime',
                            'college_classsched.subjectUnit',
                            'college_prospectus.subjDesc',
                            'college_prospectus.subjCode',
                            'college_prospectus.lecunits',
                            'college_prospectus.labunits',
                            'rooms.roomname',
                            'college_scheddetail.scheddetialclass',
                            'college_scheddetail.schedotherclass',
                            'college_prospectus.id as subjID',
                            'lastname',
                            'firstname',
                            'sectionDesc',
                            DB::raw('GROUP_CONCAT( DISTINCT days.id ORDER BY days.id SEPARATOR ", ") as day')

                        
                        )
                        ->groupBy('college_classsched.id')
                        ->get();

                        if(count($totalsubj) > 0){
                            $tuitionamount *= count($totalsubj);
                        }
                    }

                    if ($tui->permop == 1) {
                        $paymentsetup = db::table('paymentsetup')
                            ->where('id', $tui->permopid)
                            ->first();

                        if ($paymentsetup) {
                            $tuitionamount *= $paymentsetup->noofpayment;
                        }
                    }
                    
                } else {
                    $tuitionamount = $tui->amount;
                    $college_particulars = '';
                }

                $tui->amount = $tuitionamount;
            }
            
            $classidArray = $student_fees->pluck('classificationid')->unique()->values();

            $studentFessTotal += $student_fees->sum('amount');
            $studentAllFees = $studentAllFees->merge($student_fees);

            $totalcharges += $student_fees->sum('amount');

            // PAYMENTS 
            $allpayments_combined = DB::table('chrngtrans as ct')
                ->select('ct.id', 'ct.studid', 'ct.syid', 'ct.semid', 'ct.transdate', 'ct.ornum', 'ct.transno', 'cd.classid', 'cd.amount','cd.chrngtransid', 'cd.itemKind')
                ->leftJoin('chrngtransdetail as cd', 'ct.id', '=', 'cd.chrngtransid')
                ->where('cd.itemKind', 0)
                ->where('ct.studid', $studid)
                ->where('ct.syid', $syid)
                ->where('ct.semid', $semid)
                ->where('ct.cancelled', 0)
                ->get();

            $allpayments_ornum = DB::table('chrngtrans as ct')
                ->select(
                    'ct.id',
                    'ct.studid',
                    'ct.syid',
                    'ct.semid',
                    'ct.transdate as createddatetime',
                    'ct.ornum',
                    'ct.transno',
                    DB::raw('MIN(cd.classid) as classid'),
                    DB::raw('SUM(cd.amount) as amount'),
                    DB::raw('SUM(CASE WHEN cd.itemKind = 1 THEN cd.amount ELSE 0 END) as non_tuition_amount'),
                    DB::raw('MIN(cd.chrngtransid) as chrngtransid'),
                    DB::raw('MAX(cd.itemKind) as has_non_tuition'),
                    DB::raw("CONCAT('Payment for ', GROUP_CONCAT(DISTINCT it.description ORDER BY it.description SEPARATOR '/')) as particulars")
                )
                ->leftJoin('chrngtransdetail as cd', 'ct.id', '=', 'cd.chrngtransid')
                ->leftJoin('itemclassification as it', 'cd.classid', '=', 'it.id')
                ->where('ct.studid', $studid)
                ->where('ct.syid', $syid)
                ->where('ct.semid', $semid)
                ->where('ct.cancelled', 0)
                ->groupBy('ct.id', 'ct.studid', 'ct.syid', 'ct.semid', 'ct.transdate', 'ct.ornum', 'ct.transno')
                ->get();

            $studentPaymentsTotal += $allpayments_combined->sum('amount');
            $studentAllPayments = $studentAllPayments->merge($allpayments_combined);
            $totalpayment += $allpayments_combined->sum('amount');

            // DISCOUNTS ASSESSMENTS
            $discount_amount = DB::table('studdiscounts')
                ->select(
                    'studdiscounts.*', 
                    'studdiscounts.discamount as amount',
                    DB::raw('CONCAT("DISCOUNT: ", ic.description) as particulars')
                )
                ->leftJoin('itemclassification as ic', 'studdiscounts.classid', '=', 'ic.id')
                ->where('studdiscounts.syid', $syid)
                ->where('studdiscounts.semid', $semid)
                ->where('studdiscounts.studid', $studid)
                ->where('studdiscounts.deleted', 0)
                ->get();
            
            // Merge discount ornums
            $allpayments_ornum = $allpayments_ornum->merge(
                collect($discount_amount)->values()
            );

            $overall_discount = $discount_amount->sum('discamount') ?? 0;

            if($range && isset($range->wdiscount) && $range->wdiscount == 0){
                $studentFessTotal -= $overall_discount;
                $totaldiscount -= $overall_discount;
            } else{
                $studentPaymentsTotal += $overall_discount;
                $totaldiscount += $student_fees->sum('amount');
                $totalpayment += $overall_discount;
            }

            // ADJUSTMENTS ASSESSMENTS
            $adjustment_amount = DB::table('adjustments')
                ->select(
                    'adjustments.id',
                    'adjustments.classid as classificationid',
                    'adjustments.classid', 
                    DB::raw('CONCAT("ADJ: ", adjustments.description) as particulars'),
                    'adjustments.amount', 
                    'adjustmentdetails.createddatetime', 
                    'adjustments.isdebit', 
                    'adjustments.iscredit'
                )
                ->join('adjustmentdetails', 'adjustments.id', '=', 'adjustmentdetails.headerid')
                ->where('adjustments.syid', $syid)
                ->where('adjustments.semid', $semid)
                ->where('adjustmentdetails.studid', $studid)
                ->where('adjustments.deleted', 0)
                ->where('adjustmentdetails.deleted', 0)
                ->get();
            
            $allpayments_ornum = $allpayments_ornum->merge(
                collect($adjustment_amount)->where('iscredit', 1)->values()
            );
          
            $classidArray = $classidArray->merge(
                $adjustment_amount->where('isdebit', 1)->pluck('classificationid')
            )->unique()->values();

            $studentAllFees = $studentAllFees->merge(collect($adjustment_amount)->where('isdebit', 1)->values());
        
            $total_adjustment_debit += $adjustment_amount->where('isdebit', 1)->sum('amount') ?? 0;
            $total_adjustment_credit += $adjustment_amount->where('iscredit', 1)->whereIn('classificationid', $classidArray)->sum('amount') ?? 0;
            $studentFessTotal += $total_adjustment_debit;
            $studentAllPayments = $studentAllPayments->merge(collect($adjustment_amount)->where('iscredit', 1)->values());

            if($range && isset($range->wadjustment) && $range->wadjustment == 0){
                $studentFessTotal -= $total_adjustment_credit;
            } else {
                $studentPaymentsTotal += $total_adjustment_credit;
                $totalcharges += $total_adjustment_debit;
                $totalpayment += $total_adjustment_credit;
            }
          
            // OLD ACCOUNT ASSESSMENTS
            $oldaccount_amount = DB::table('studledger')
                ->select('studledger.classid as classificationid', 'studledger.particulars', 'studledger.amount', 'studledger.createddatetime')
                ->where('studid', $studid)
                ->where('syid', $syid)
                ->where('semid', $semid)
                ->where('classid', $balforwardsetup->classid)
                ->where('particulars', 'not like', '%ADJ:%')
                ->where('deleted', 0)
                ->where('payment', 0)
                ->get();
       
            $classidArray = $classidArray->merge(
                $oldaccount_amount->pluck('classificationid')
            );

            $studentFessTotal += $oldaccount_amount->sum('amount');
            $studentAllFees = $studentAllFees->merge($oldaccount_amount);
            $totalcharges += $oldaccount_amount->sum('amount');

            // OLD ACCOUNT PAYMENTS
            $oldaccount_payments = collect($allpayments_combined->where('classid', $balforwardsetup->classid)->values());
            
            if($range && isset($range->woldacc) && $range->woldacc == 0){
                $studentFessTotal -= $oldaccount_payments->sum('amount');
                $totalpayment -= $oldaccount_payments->sum('amount');
            }


            //FIRST PAYMENT WILL TAG AS DOWNPAYMENT
            $downpayment = optional(DB::table('chrngtrans as ct')
                ->where('ct.studid', $studid)
                ->where('ct.syid', $syid)
                ->where('ct.semid', $semid)
                ->where('ct.cancelled', 0)
                ->first())->id;

            if($downpayment){  // Changed from !$downpayment to $downpayment
                $downpayment_amount = collect($allpayments_combined->where('chrngtransid', $downpayment)->values())->sum('amount');

                if($range && isset($range->wdp) && $range->wdp == 0){
                    $studentFessTotal -= $downpayment_amount;
                    $totalpayment -= $downpayment_amount;
                }
            }
            // return $allpayments_ornum;

            // Calculate division amount
            if ($range) {
                $startMonth = intval($range->sdate ?? 0);
                $endMonth = intval($range->edate ?? 0);

                if ($startMonth > 0 && $endMonth > 0 && $startMonth <= 12 && $endMonth <= 12) {
                    if ($startMonth <= $endMonth) {
                        $divamount = $endMonth - $startMonth + 1;
                    } else {
                        $divamount = (12 - $startMonth + 1) + $endMonth;
                    }
                } else {
                    $divamount = 1;
                }
            } else {
                $divamount = 1;
            }

            $amount = $divamount > 0 ? $studentFessTotal / $divamount : 0;
           
            $payment_array = [];

            if ($range && $divamount > 0) {
                $startMonth = intval($range->sdate ?? 0);
                $endMonth = intval($range->edate ?? 0);
                
                // If the range wraps to the next year
                if ($startMonth > $endMonth) {
                    $runningTotal = 0;
                    $monthIndex = 0;
                    
                    // Add months from startMonth to December (current year)
                    for ($i = $startMonth; $i <= 12; $i++) {
                        $monthIndex++;
                        $isLastMonth = ($monthIndex == $divamount);
                        $monthAmount = $isLastMonth ? ($studentFessTotal - $runningTotal) : round($amount, 2);
                        
                        array_push($payment_array, (object) [
                            'duedate' => date('Y-m-d', strtotime($currentYear . '-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-01')),
                            'amount' => number_format($monthAmount, 2, '.', '')
                        ]);
                        
                        $runningTotal += round($monthAmount, 2);
                    }

                    // Add months from January to endMonth (next year)
                    for ($i = 1; $i <= $endMonth; $i++) {
                        $monthIndex++;
                        $isLastMonth = ($monthIndex == $divamount);
                        $monthAmount = $isLastMonth ? ($studentFessTotal - $runningTotal) : round($amount, 2);
                        
                        array_push($payment_array, (object) [
                            'duedate' => date('Y-m-d', strtotime(($currentYear + 1) . '-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-01')),
                            'amount' => number_format($monthAmount, 2, '.', '')
                        ]);
                        
                        $runningTotal += round($monthAmount, 2);
                    }
                } else {
                    // Simple case: same year
                    $runningTotal = 0;
                    $monthIndex = 0;
                    
                    for ($i = $startMonth; $i <= $endMonth; $i++) {
                        $monthIndex++;
                        $isLastMonth = ($monthIndex == $divamount);
                        $monthAmount = $isLastMonth ? ($studentFessTotal - $runningTotal) : round($amount, 2);
                        
                        array_push($payment_array, (object) [
                            'duedate' => date('Y-m-d', strtotime($currentYear . '-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-01')),
                            'amount' => number_format($monthAmount, 2, '.', '')
                        ]);
                        
                        $runningTotal += round($monthAmount, 2);
                    }
                }
            }
           
            $fees = collect($payment_array);
            $assessment = array();

            foreach ($fees as $fee) {
                $month = date_format(date_create($fee->duedate), 'F');
            
                array_push($assessment, (object) [
                    'duedate' => $fee->duedate,
                    'amount' => number_format($fee->amount, 2, '.', ''),
                    'payment' => 0.00,
                    'balance' => number_format($fee->amount, 2, '.', ''),
                    'particulars' => $month,
                    'totalpaymentnotuition' => '',
                    'paymentnotuition' => ''
                ]);
            }
            
            $combined_data = collect($studentAllFees);
           
            foreach ($combined_data as $data) {
                $data->payable = $studentAllFees->where('classificationid', $data->classificationid ?? null)->sum('amount');
                $data->payment = $studentAllPayments->where('classid', $data->classificationid ?? null)->sum('amount');
                $data->discount = $discount_amount->where('classid', $data->classificationid ?? null)->whereNotNull('discountid')->sum('amount');
                
                $data->ornums = $studentAllPayments
                    ->where('classid', $data->classificationid ?? null)
                    ->pluck('ornum')
                    ->filter(function($ornum) {
                        return !empty(trim($ornum ?? ''));
                    })
                    ->unique()
                    ->implode(', ');
            }
           
            $payments = $combined_data;
            $totalbalance = $totalcharges - $totalpayment - $totaldiscount;

            // Old amount for old accounts (classid == $oldclassid)
            $oldamount = $combined_data->where('classid', $oldclassid)->sum('payable');

            $level = DB::table('gradelevel')->where('id', $student->levelid)->first();
            $levelname = $level->levelname ?? '';

            // Month setup + dues
            $monthsetup = DB::table('monthsetup')->where('monthid', $monthsetupid)->first();
            $monthdue = collect($assessment);
           
            $statements[] = [
                'studinfo' => $student,
                'levelname' => $levelname,
                'sectionname' => $student->sectionname ?? '',
                'courseabrv' => $student->courseabrv ?? '',
                'ledger' => $combined_data,
                'payments' => $combined_data,
                'discounts' => '',
                'monthdue' => $monthdue,
                'totalcharges' => $totalcharges,
                'totalpayment' => $totalpayment,
                'totaldiscount' => $totaldiscount,
                'totalbalance' => $totalbalance,
                'oldamount' => $oldamount,
                'monthinword' => $monthsetup->description ?? '',
                'selectedschoolyear' => $selectedschoolyear,
                'selectedsemester' => $selectedsemester,
                'allpayments_ornum' => $allpayments_ornum
            ];
        }

        return view('finance.reports.pdf.bulk_statements_individual', [
            'statements' => $statements,
            'schoolinfo' => $schoolinfo,
            'syid' => $syid,
            'semid' => $semid,
            'monthsetupid' => $monthsetupid,
        ]);
    }

    public function getIndividualStatementsView(Request $request)
    {
        $semid = $request->get('selectedsemester');
        $syid = $request->get('selectedschoolyear');
        $monthsetupid = $request->get('selectedmonth');
        $levelid = $request->get('levelid');
        $courseid = $request->get('courseid');
        $studid = $request->get('studid');

        $month = null;
        if ($request->get('selectedmonth', 0) > 0) {
            $monthData = DB::table('monthsetup')->where('id', $monthsetupid)->first();
            if ($monthData && isset($monthData->description)) {
                $parsedMonth = date_parse($monthData->description);
                if (isset($parsedMonth['month']) && $parsedMonth['month']) {
                    $monthNum = $parsedMonth['month'];
                    $month = strlen($monthNum) == 1 ? '0' . $monthNum : $monthNum;
                }
            }
        }

        $schoolinfo = DB::table('schoolinfo')->first();
        $balforwardsetup = DB::table('balforwardsetup')->first();
        $oldclassid = $balforwardsetup->classid ?? null;

        if($levelid == 14 || $levelid == 15)
        {
            $students = DB::table('sh_enrolledstud')
                ->select('studinfo.id', 'studinfo.sid', 'studinfo.lastname', 'studinfo.firstname',
                    'studinfo.middlename', 'studinfo.suffix', 'sh_enrolledstud.levelid', 'sh_enrolledstud.dateenrolled',
                    'sh_enrolledstud.sectionid', 'sections.sectionname','studinfo.feesid')
                ->join('studinfo', 'sh_enrolledstud.studid', '=', 'studinfo.id')
                ->leftJoin('sections', 'sh_enrolledstud.sectionid', '=', 'sections.id')
                ->where('sh_enrolledstud.syid', $syid)
                ->where('sh_enrolledstud.semid', $semid)
                ->where('sh_enrolledstud.studid', $studid)
                ->where('sh_enrolledstud.deleted', 0)
                ->where('sh_enrolledstud.studstatus', '!=', 4)
                ->when($levelid, function($q) use ($levelid) {
                    return $q->where('sh_enrolledstud.levelid', $levelid);
                })
                ->get();
        }
        elseif($levelid >= 17 && $levelid <= 21)
        {
            $students = DB::table('college_enrolledstud')
                ->select('studinfo.id', 'studinfo.sid', 'studinfo.lastname', 'studinfo.firstname',
                    'studinfo.middlename', 'studinfo.suffix', 'college_enrolledstud.yearLevel as levelid', 'college_enrolledstud.date_enrolled as dateenrolled',
                    'college_enrolledstud.courseid', 'college_courses.courseabrv','studinfo.feesid')
                ->join('studinfo', 'college_enrolledstud.studid', '=', 'studinfo.id')
                ->join('college_courses', 'college_enrolledstud.courseid', '=', 'college_courses.id')
                ->where('college_enrolledstud.syid', $syid)
                ->where('college_enrolledstud.semid', $semid)
                ->where('college_enrolledstud.studid', $studid)
                ->where('college_enrolledstud.deleted', 0)
                ->where('college_enrolledstud.studstatus', '!=', 4)
                ->when($levelid, function($q) use ($levelid) {
                    return $q->where('college_enrolledstud.yearLevel', $levelid);
                })
                ->when($courseid, function($q) use ($courseid) {
                    return $q->where('college_enrolledstud.courseID', $courseid);
                })
                ->get();
        }
        else
        {
            $semid = 1;
            $students = DB::table('enrolledstud')
                ->select('studinfo.id', 'studinfo.sid', 'studinfo.lastname', 'studinfo.firstname',
                    'studinfo.middlename', 'studinfo.suffix', 'enrolledstud.levelid', 'enrolledstud.dateenrolled',
                    'enrolledstud.sectionid', 'sections.sectionname','studinfo.feesid')
                ->join('studinfo', 'enrolledstud.studid', '=', 'studinfo.id')
                ->leftJoin('sections', 'enrolledstud.sectionid', '=', 'sections.id')
                ->where('enrolledstud.syid', $syid)
                ->where('enrolledstud.studid', $studid)
                ->where('enrolledstud.deleted', 0)
                ->where('enrolledstud.studstatus', '!=', 4)
                ->when($levelid, function($q) use ($levelid) {
                    return $q->where('enrolledstud.levelid', $levelid);
                })
                ->get();

        }

        $selectedschoolyear = optional(DB::table('sy')->where('id', $syid)->first())->sydesc ?? '';
        $selectedsemester = optional(DB::table('semester')->where('id', $semid)->first())->semester ?? '';

        $acadid = DB::table('gradelevel')->get()->keyBy('id');
        $ranges = DB::table('month_range_setup')->where('deleted', 0)->get()->keyBy('acadprogid');

        $statements = [];
        $currentYear = date('Y');
       
        foreach ($students as $student) {
            $levelid = $student->levelid ?? 0;
            $studid = $student->id ?? 0;

            if (!$studid) continue;

            $gradeLevel = $acadid->get($levelid);
            $acadprogid = $gradeLevel->acadprogid ?? null;
            
            $range = $ranges->get($acadprogid);

            $studentFessTotal = 0;
            $studentPaymentsTotal = 0;
            $total_discount = 0;
            $total_adjustment_debit = 0;
            $total_adjustment_credit = 0;
            $totalcharges = 0;
            $totalpayment = 0;
            $totaldiscount = 0;

            $classidArray = [];
            $studentAllFees = collect();
            $studentAllPayments = collect();

            // ----------------------------------------------------------

            $tuition = db::table('tuitionheader')
                ->select('tuitionheader.id', 'tuitiondetail.id as tuitiondetailid', 'syid', 'levelid', 'grantee.description', 'itemclassification.description as particulars', 'amount', 'itemclassification.id as classid', 'pschemeid', 'semid', 'istuition', 'persubj', 'permop', 'permopid')
                ->leftjoin('tuitiondetail', 'tuitionheader.id', '=', 'tuitiondetail.headerid')
                ->join('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
                ->join('grantee', 'tuitionheader.grantee', '=', 'grantee.id')
                ->where('tuitionheader.id', $student->feesid)
                ->where('tuitiondetail.deleted', 0)
                ->where('pschemeid', '!=', null)
                ->get();
        
            if (count($tuition) == 0) {
                $_fees = db::table('tuitionheader')
                    ->where('syid', $syid)
                    ->where(function ($q) use ($levelid, $semid) {
                        if ($levelid >= 17 && $levelid <= 25) {
                            $q->where('semid', $semid);
                        }
                    })
                    ->where('levelid', $levelid)
                    ->where('deleted', 0)
                    ->first();

                if ($_fees) {
                    $feesid = $_fees->id;

                    $tuition = db::table('tuitionheader')
                        ->select('tuitionheader.id', 'tuitiondetail.id as tuitiondetailid', 'syid', 'levelid', 'grantee.description', 'itemclassification.description as particulars', 'amount', 'itemclassification.id as classid', 'pschemeid', 'semid', 'istuition', 'persubj', 'permop', 'permopid')
                        ->leftjoin('tuitiondetail', 'tuitionheader.id', '=', 'tuitiondetail.headerid')
                        ->join('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
                        ->join('grantee', 'tuitionheader.grantee', '=', 'grantee.id')
                        ->where('tuitionheader.id', $student->feesid)
                        ->where('tuitiondetail.deleted', 0)
                        ->get();
                }
            }

            if ($levelid >= 17 && $levelid <= 25) {
                $ausubjects = db::table('tuition_assessmentunit')
                    ->where('deleted', 0)
                    ->get();

                $college_subjects = DB::table('college_subjects')
                    ->where('deleted', 0)
                    ->get();

                $college_prospectus = DB::table('college_prospectus')
                    ->where('deleted', 0)
                    ->get();

                $college_subjects = collect($college_subjects);
                $college_prospectus = collect($college_prospectus);
                $ausubjects = collect($ausubjects);
                // $totalunits = db::table('college_studsched')
                //     ->select(db::raw('SUM(lecunits) + SUM(labunits) AS totalunits'))
                //     ->join('college_classsched', 'college_studsched.schedid', '=', 'college_classsched.id')
                //     ->join('college_prospectus', 'college_classsched.subjectID', '=', 'college_prospectus.id')
                //     ->join('college_sections', 'college_classsched.sectionID', '=', 'college_sections.id')
                //     ->where('college_studsched.studid', $studid)
                //     ->where('college_studsched.deleted', 0)
                //     ->where('college_classsched.deleted', 0)
                //     ->where('college_classsched.syID', $syid)
                //     ->where('college_classsched.semesterID', $semid)
                //     ->where('college_sections.section_specification', '!=', 2)
                //     ->where('college_studsched.schedstatus', '!=', 'DROPPED')
                //     ->first();

                $units = 0;
                $subjcount = 0;
                $finance = null;
                $studentloads = CollegeStudentLoadingController::getAddedStudentLoading($studid, 'all', $syid, $semid, 0, null, null);
            
                $studentloads = collect($studentloads);
                $studentloads = $studentloads['original']['studentLoading'];
                $studentloads = collect($studentloads)->where('loadStatus', 0)->values();
                foreach ($studentloads as $loads) {
                    // $units += $loads['totalUnits'];
                    // $subjcount += 1;

                    $prospectus = $college_prospectus->where('id', $loads['subjectID'])->first();

                    if ($prospectus) {
                        $getsubject = $college_subjects->where('id', $prospectus->subjectID)->first();
                        if ($getsubject) {
                            $getau = $ausubjects->where('subjid', $getsubject->id)->first();
                            if ($getau) {
                                $units += $getau->assessmentunit;
                            } else {
                                $units += $loads['lecunits'] + $loads['labunits'];
                            }
                        }
                    } else {
                        $units += $loads['lecunits'] + $loads['labunits'];
                    }
                }
            }
            // ----------------------------------------------------------

            $student_fees = DB::table('tuitionheader')
                ->select('tuitiondetail.classificationid','tuitiondetail.classificationid','itemclassification.description as particulars', 'tuitiondetail.amount', 'tuitiondetail.istuition', 'tuitiondetail.persubj', 'tuitiondetail.permop', 'tuitiondetail.permopid')
                ->join('tuitiondetail', 'tuitionheader.id', '=', 'tuitiondetail.headerid')
                ->join('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
                ->where('tuitionheader.deleted', 0)
                ->where('tuitiondetail.deleted', 0)
                ->where('tuitionheader.id', $student->feesid)
                ->where('tuitionheader.syid', $syid)
                ->where('tuitionheader.semid', $semid)
                // ->where('tuitionheader.levelid', $levelid)
                ->get()
                ->map(function($item) use ($student) {
                    $item->createddatetime = $student->dateenrolled;
                    return $item;
                });

             foreach ($student_fees as $tui) {
                if ($levelid >= 17 && $levelid <= 25) {
                $totalunits = 0;

                if ($tui->istuition == 1) {
                    // echo $tui->amount . ' * ' . $units;
                    $tuitionamount = $units > 0 ? $tui->amount * $units : $tui->amount;
                    $college_particulars = ' | ' . $units . ' Units';
                } else {
                    $tuitionamount = $tui->amount;
                    $college_particulars = '';
                }

                if ($tui->persubj == 1) {

                    $totalsubj = DB::table('college_loadsubject')
                        ->where('college_loadsubject.studid', $studid)
                        ->where('college_loadsubject.deleted', 0)
                        ->join('college_enrolledstud', 'college_loadsubject.studid', 'college_enrolledstud.studid')
                        ->where('college_enrolledstud.studstatus', '!=', 'DROPPED')
                        ->join('college_sections', function ($join) {
                            $join->on('college_loadsubject.sectionID', '=', 'college_sections.id');
                            $join->where('college_sections.deleted', 0);
                        })
                        ->join('college_prospectus', function ($join) {
                            $join->on('college_loadsubject.subjectID', '=', 'college_prospectus.id');
                            $join->where('college_loadsubject.deleted', 0);
                        })
                        ->join('college_classsched', function ($join) use ($syid, $semid) {
                            $join->on('college_sections.id', '=', 'college_classsched.sectionid');
                            $join->on('college_prospectus.id', '=', 'college_classsched.subjectID');
                            $join->where('college_classsched.syid', $syid);
                            $join->where('college_classsched.deleted', 0);
                            $join->where('college_classsched.semesterID', $semid);
                        })
                        
                        ->leftJoin('college_scheddetail', function ($join) {
                            $join->on('college_classsched.id', '=', 'college_scheddetail.headerid');
                            $join->where('college_scheddetail.deleted', 0);
                        })
                        ->leftJoin('days', function ($join) {
                            $join->on('college_scheddetail.day', '=', 'days.id');
                        })
                        ->leftJoin('college_instructor', function ($join) {
                            $join->where('college_instructor.deleted', 0);
                            $join->on('college_classsched.id', '=', 'college_instructor.classschedid');
                        })
                        ->leftJoin('teacher', function ($join) {
                            $join->on('college_instructor.teacherID', '=', 'teacher.id');
                        })
                        ->leftJoin('rooms', function ($join) {
                            $join->on('college_scheddetail.roomid', '=', 'rooms.id');
                            $join->where('rooms.deleted', 0);
                        })
                        ->select(
                            'days.id as daysort',
                            'days.description',
                            'college_classsched.id',
                            'college_scheddetail.id as schedid',
                            'college_scheddetail.etime',
                            'college_scheddetail.stime',
                            'college_classsched.subjectUnit',
                            'college_prospectus.subjDesc',
                            'college_prospectus.subjCode',
                            'college_prospectus.lecunits',
                            'college_prospectus.labunits',
                            'rooms.roomname',
                            'college_scheddetail.scheddetialclass',
                            'college_scheddetail.schedotherclass',
                            'college_prospectus.id as subjID',
                            'lastname',
                            'firstname',
                            'sectionDesc',
                            DB::raw('GROUP_CONCAT( DISTINCT days.id ORDER BY days.id SEPARATOR ", ") as day')

                        
                        )
                        ->groupBy('college_classsched.id')
                        ->get();

                        if(count($totalsubj) > 0){
                            $tuitionamount *= count($totalsubj);
                        }
                    }

                    if ($tui->permop == 1) {
                        $paymentsetup = db::table('paymentsetup')
                            ->where('id', $tui->permopid)
                            ->first();

                        if ($paymentsetup) {
                            $tuitionamount *= $paymentsetup->noofpayment;
                        }
                    }
                    
                } else {
                    $tuitionamount = $tui->amount;
                    $college_particulars = '';
                }

                $tui->amount = $tuitionamount;
            }
            
            $classidArray = $student_fees->pluck('classificationid')->unique()->values();

            $studentFessTotal += $student_fees->sum('amount');
            $studentAllFees = $studentAllFees->merge($student_fees);

            $totalcharges += $student_fees->sum('amount');

            // PAYMENTS 
            $allpayments_combined = DB::table('chrngtrans as ct')
                ->select('ct.id', 'ct.studid', 'ct.syid', 'ct.semid', 'ct.transdate', 'ct.ornum', 'ct.transno', 'cd.classid', 'cd.amount','cd.chrngtransid', 'cd.itemKind')
                ->leftJoin('chrngtransdetail as cd', 'ct.id', '=', 'cd.chrngtransid')
                ->where('cd.itemKind', 0)
                ->where('ct.studid', $studid)
                ->where('ct.syid', $syid)
                ->where('ct.semid', $semid)
                ->where('ct.cancelled', 0)
                ->get();

            $allpayments_ornum = DB::table('chrngtrans as ct')
                ->select(
                    'ct.id',
                    'ct.studid',
                    'ct.syid',
                    'ct.semid',
                    'ct.transdate as createddatetime',
                    'ct.ornum',
                    'ct.transno',
                    DB::raw('MIN(cd.classid) as classid'),
                    DB::raw('SUM(cd.amount) as amount'),
                    DB::raw('SUM(CASE WHEN cd.itemKind = 1 THEN cd.amount ELSE 0 END) as non_tuition_amount'),
                    DB::raw('MIN(cd.chrngtransid) as chrngtransid'),
                    DB::raw('MAX(cd.itemKind) as has_non_tuition'),
                    DB::raw("CONCAT('Payment for ', GROUP_CONCAT(DISTINCT it.description ORDER BY it.description SEPARATOR '/')) as particulars")
                )
                ->leftJoin('chrngtransdetail as cd', 'ct.id', '=', 'cd.chrngtransid')
                ->leftJoin('itemclassification as it', 'cd.classid', '=', 'it.id')
                ->where('ct.studid', $studid)
                ->where('ct.syid', $syid)
                ->where('ct.semid', $semid)
                ->where('ct.cancelled', 0)
                ->groupBy('ct.id', 'ct.studid', 'ct.syid', 'ct.semid', 'ct.transdate', 'ct.ornum', 'ct.transno')
                ->get();

            $studentPaymentsTotal += $allpayments_combined->sum('amount');
            $studentAllPayments = $studentAllPayments->merge($allpayments_combined);
            $totalpayment += $allpayments_combined->sum('amount');

            // DISCOUNTS ASSESSMENTS
            $discount_amount = DB::table('studdiscounts')
                ->select(
                    'studdiscounts.*', 
                    'studdiscounts.discamount as amount',
                    DB::raw('CONCAT("DISCOUNT: ", ic.description) as particulars')
                )
                ->leftJoin('itemclassification as ic', 'studdiscounts.classid', '=', 'ic.id')
                ->where('studdiscounts.syid', $syid)
                ->where('studdiscounts.semid', $semid)
                ->where('studdiscounts.studid', $studid)
                ->where('studdiscounts.deleted', 0)
                ->get();
            
            // Merge discount ornums
            $allpayments_ornum = $allpayments_ornum->merge(
                collect($discount_amount)->values()
            );

            $overall_discount = $discount_amount->sum('discamount') ?? 0;

            if($range && isset($range->wdiscount) && $range->wdiscount == 0){
                $studentFessTotal -= $overall_discount;
                $totaldiscount -= $overall_discount;
            } else{
                $studentPaymentsTotal += $overall_discount;
                $totaldiscount += $student_fees->sum('amount');
                $totalpayment += $overall_discount;
            }

            // ADJUSTMENTS ASSESSMENTS
            $adjustment_amount = DB::table('adjustments')
                ->select(
                    'adjustments.id',
                    'adjustments.classid as classificationid',
                    'adjustments.classid', 
                    DB::raw('CONCAT("ADJ: ", adjustments.description) as particulars'),
                    'adjustments.amount', 
                    'adjustmentdetails.createddatetime', 
                    'adjustments.isdebit', 
                    'adjustments.iscredit'
                )
                ->join('adjustmentdetails', 'adjustments.id', '=', 'adjustmentdetails.headerid')
                ->where('adjustments.syid', $syid)
                ->where('adjustments.semid', $semid)
                ->where('adjustmentdetails.studid', $studid)
                ->where('adjustments.deleted', 0)
                ->where('adjustmentdetails.deleted', 0)
                ->get();
            
            $allpayments_ornum = $allpayments_ornum->merge(
                collect($adjustment_amount)->where('iscredit', 1)->values()
            );
          
            $classidArray = $classidArray->merge(
                $adjustment_amount->where('isdebit', 1)->pluck('classificationid')
            )->unique()->values();

            $studentAllFees = $studentAllFees->merge(collect($adjustment_amount)->where('isdebit', 1)->values());
        
            $total_adjustment_debit += $adjustment_amount->where('isdebit', 1)->sum('amount') ?? 0;
            $total_adjustment_credit += $adjustment_amount->where('iscredit', 1)->whereIn('classificationid', $classidArray)->sum('amount') ?? 0;
            $studentFessTotal += $total_adjustment_debit;
            $studentAllPayments = $studentAllPayments->merge(collect($adjustment_amount)->where('iscredit', 1)->values());

            if($range && isset($range->wadjustment) && $range->wadjustment == 0){
                $studentFessTotal -= $total_adjustment_credit;
            } else {
                $studentPaymentsTotal += $total_adjustment_credit;
                $totalcharges += $total_adjustment_debit;
                $totalpayment += $total_adjustment_credit;
            }
          
            // OLD ACCOUNT ASSESSMENTS
            $oldaccount_amount = DB::table('studledger')
                ->select('studledger.classid as classificationid', 'studledger.particulars', 'studledger.amount', 'studledger.createddatetime')
                ->where('studid', $studid)
                ->where('syid', $syid)
                ->where('semid', $semid)
                ->where('classid', $balforwardsetup->classid)
                ->where('particulars', 'not like', '%ADJ:%')
                ->where('deleted', 0)
                ->where('payment', 0)
                ->get();
       
            $classidArray = $classidArray->merge(
                $oldaccount_amount->pluck('classificationid')
            );

            $studentFessTotal += $oldaccount_amount->sum('amount');
            $studentAllFees = $studentAllFees->merge($oldaccount_amount);
            $totalcharges += $oldaccount_amount->sum('amount');

            // OLD ACCOUNT PAYMENTS
            $oldaccount_payments = collect($allpayments_combined->where('classid', $balforwardsetup->classid)->values());
            
            if($range && isset($range->woldacc) && $range->woldacc == 0){
                $studentFessTotal -= $oldaccount_payments->sum('amount');
                $totalpayment -= $oldaccount_payments->sum('amount');
            }


            //FIRST PAYMENT WILL TAG AS DOWNPAYMENT
            $downpayment = optional(DB::table('chrngtrans as ct')
                ->where('ct.studid', $studid)
                ->where('ct.syid', $syid)
                ->where('ct.semid', $semid)
                ->where('ct.cancelled', 0)
                ->first())->id;

            if($downpayment){  // Changed from !$downpayment to $downpayment
                $downpayment_amount = collect($allpayments_combined->where('chrngtransid', $downpayment)->values())->sum('amount');

                if($range && isset($range->wdp) && $range->wdp == 0){
                    $studentFessTotal -= $downpayment_amount;
                    $totalpayment -= $downpayment_amount;
                }
            }
            // return $allpayments_ornum;

            // Calculate division amount
            if ($range) {
                $startMonth = intval($range->sdate ?? 0);
                $endMonth = intval($range->edate ?? 0);

                if ($startMonth > 0 && $endMonth > 0 && $startMonth <= 12 && $endMonth <= 12) {
                    if ($startMonth <= $endMonth) {
                        $divamount = $endMonth - $startMonth + 1;
                    } else {
                        $divamount = (12 - $startMonth + 1) + $endMonth;
                    }
                } else {
                    $divamount = 1;
                }
            } else {
                $divamount = 1;
            }

            $amount = $divamount > 0 ? $studentFessTotal / $divamount : 0;
           
            $payment_array = [];

            if ($range && $divamount > 0) {
                $startMonth = intval($range->sdate ?? 0);
                $endMonth = intval($range->edate ?? 0);
                
                // If the range wraps to the next year
                if ($startMonth > $endMonth) {
                    $runningTotal = 0;
                    $monthIndex = 0;
                    
                    // Add months from startMonth to December (current year)
                    for ($i = $startMonth; $i <= 12; $i++) {
                        $monthIndex++;
                        $isLastMonth = ($monthIndex == $divamount);
                        $monthAmount = $isLastMonth ? ($studentFessTotal - $runningTotal) : round($amount, 2);
                        
                        array_push($payment_array, (object) [
                            'duedate' => date('Y-m-d', strtotime($currentYear . '-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-01')),
                            'amount' => number_format($monthAmount, 2, '.', '')
                        ]);
                        
                        $runningTotal += round($monthAmount, 2);
                    }

                    // Add months from January to endMonth (next year)
                    for ($i = 1; $i <= $endMonth; $i++) {
                        $monthIndex++;
                        $isLastMonth = ($monthIndex == $divamount);
                        $monthAmount = $isLastMonth ? ($studentFessTotal - $runningTotal) : round($amount, 2);
                        
                        array_push($payment_array, (object) [
                            'duedate' => date('Y-m-d', strtotime(($currentYear + 1) . '-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-01')),
                            'amount' => number_format($monthAmount, 2, '.', '')
                        ]);
                        
                        $runningTotal += round($monthAmount, 2);
                    }
                } else {
                    // Simple case: same year
                    $runningTotal = 0;
                    $monthIndex = 0;
                    
                    for ($i = $startMonth; $i <= $endMonth; $i++) {
                        $monthIndex++;
                        $isLastMonth = ($monthIndex == $divamount);
                        $monthAmount = $isLastMonth ? ($studentFessTotal - $runningTotal) : round($amount, 2);
                        
                        array_push($payment_array, (object) [
                            'duedate' => date('Y-m-d', strtotime($currentYear . '-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-01')),
                            'amount' => number_format($monthAmount, 2, '.', '')
                        ]);
                        
                        $runningTotal += round($monthAmount, 2);
                    }
                }
            }
           
            $fees = collect($payment_array);
            $assessment = array();

            foreach ($fees as $fee) {
                $month = date_format(date_create($fee->duedate), 'F');
            
                array_push($assessment, (object) [
                    'duedate' => $fee->duedate,
                    'amount' => number_format($fee->amount, 2, '.', ''),
                    'payment' => 0.00,
                    'balance' => number_format($fee->amount, 2, '.', ''),
                    'particulars' => $month,
                    'totalpaymentnotuition' => '',
                    'paymentnotuition' => ''
                ]);
            }
            
            $combined_data = collect($studentAllFees);
           
            foreach ($combined_data as $data) {
                $data->payable = $studentAllFees->where('classificationid', $data->classificationid ?? null)->sum('amount');
                $data->payment = $studentAllPayments->where('classid', $data->classificationid ?? null)->sum('amount');
                $data->discount = $discount_amount->where('classid', $data->classificationid ?? null)->whereNotNull('discountid')->sum('amount');
                
                $data->ornums = $studentAllPayments
                    ->where('classid', $data->classificationid ?? null)
                    ->pluck('ornum')
                    ->filter(function($ornum) {
                        return !empty(trim($ornum ?? ''));
                    })
                    ->unique()
                    ->implode(', ');
            }
           
            $payments = $combined_data;
            $totalbalance = $totalcharges - $totalpayment - $totaldiscount;

            // Old amount for old accounts (classid == $oldclassid)
            $oldamount = $combined_data->where('classid', $oldclassid)->sum('payable');

            $level = DB::table('gradelevel')->where('id', $student->levelid)->first();
            $levelname = $level->levelname ?? '';

            // Month setup + dues
            $monthsetup = DB::table('monthsetup')->where('monthid', $monthsetupid)->first();
            $monthdue = collect($assessment);
           
            $statements[] = [
                'studinfo' => $student,
                'levelname' => $levelname,
                'sectionname' => $student->sectionname ?? '',
                'courseabrv' => $student->courseabrv ?? '',
                'ledger' => $combined_data,
                'payments' => $combined_data,
                'discounts' => '',
                'monthdue' => $monthdue,
                'totalcharges' => $totalcharges,
                'totalpayment' => $totalpayment,
                'totaldiscount' => $totaldiscount,
                'totalbalance' => $totalbalance,
                'oldamount' => $oldamount,
                'monthinword' => $monthsetup->description ?? '',
                'selectedschoolyear' => $selectedschoolyear,
                'selectedsemester' => $selectedsemester,
                'allpayments_ornum' => $allpayments_ornum
            ];
        }

        return view('finance.reports.pdf.bulk_statements_individual_view', [
            'statements' => $statements,
            'schoolinfo' => $schoolinfo,
            'syid' => $syid,
            'semid' => $semid,
            'monthsetupid' => $monthsetupid,
        ]);
    }


    // public function getBulkStatements(Request $request)
    // {
    //     $semid = $request->get('selectedsemester');
    //     $syid = $request->get('selectedschoolyear');
    //     $monthsetupid = $request->get('selectedmonth');
    //     $levelid = $request->get('levelid');
    //     $courseid = $request->get('courseid');
    //     // dd($semid, $syid, $monthsetupid, $levelid);

    //     // Get month information if selected
    //     $month = null;

    //     if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'taborin') {
    //         if ($request->get('selectedmonth') > 0) {
    //             $monthData = DB::table('monthsetup')->where('id', $monthsetupid)->first();
    //             if ($monthData && date_parse($monthData->description)['month']) {
    //                 $monthNum = date_parse($monthData->description)['month'];
    //                 $month = strlen($monthNum) == 1 ? '0' . $monthNum : $monthNum;
    //             }
    //         }

    //         // Get school information
    //         $schoolinfo = DB::table('schoolinfo')->first();

    //         // Get all active students based on level
    //         $students = collect();

    //         // 1. Get regular students (K-12)
    //         $regularStudents = DB::table('enrolledstud')
    //             ->select(
    //                 'studinfo.id',
    //                 'studinfo.sid',
    //                 'studinfo.lastname',
    //                 'studinfo.firstname',
    //                 'studinfo.middlename',
    //                 'studinfo.suffix',
    //                 'enrolledstud.levelid',
    //                 'enrolledstud.sectionid',
    //                 'sections.sectionname'
    //             )
    //             ->join('studinfo', 'enrolledstud.studid', '=', 'studinfo.id')
    //             ->leftJoin('sections', 'enrolledstud.sectionid', '=', 'sections.id')
    //             ->where('enrolledstud.syid', $syid)
    //             ->where('enrolledstud.deleted', 0)
    //             ->where('enrolledstud.studstatus', '!=', 4) // not withdrawn
    //             ->when($levelid, function ($query) use ($levelid) {
    //                 return $query->where('enrolledstud.levelid', $levelid);
    //             })
    //             ->orderBy('studinfo.lastname')
    //             ->orderBy('studinfo.firstname')
    //             // ->take(2)
    //             ->get();

    //         // 2. Get senior high students if applicable
    //         $shStudents = DB::table('sh_enrolledstud')
    //             ->select(
    //                 'studinfo.id',
    //                 'studinfo.sid',
    //                 'studinfo.lastname',
    //                 'studinfo.firstname',
    //                 'studinfo.middlename',
    //                 'studinfo.suffix',
    //                 'sh_enrolledstud.levelid',
    //                 'sh_enrolledstud.sectionid',
    //                 'sections.sectionname'
    //             )
    //             ->join('studinfo', 'sh_enrolledstud.studid', '=', 'studinfo.id')
    //             ->leftJoin('sections', 'sh_enrolledstud.sectionid', '=', 'sections.id')
    //             ->where('sh_enrolledstud.deleted', 0)
    //             ->where('sh_enrolledstud.studstatus', '!=', 4) // not withdrawn
    //             ->when($levelid, function ($query) use ($levelid) {
    //                 return $query->where('sh_enrolledstud.levelid', $levelid);
    //             })
    //             ->orderBy('studinfo.lastname')
    //             ->orderBy('studinfo.firstname')
    //             // ->take(2)
    //             ->get();



    //         // 3. Get college students
    //         $collegeStudents = DB::table('college_enrolledstud')
    //             ->select(
    //                 'studinfo.id',
    //                 'studinfo.sid',
    //                 'studinfo.lastname',
    //                 'studinfo.firstname',
    //                 'studinfo.middlename',
    //                 'studinfo.suffix',
    //                 'college_enrolledstud.yearLevel as levelid',
    //                 'college_enrolledstud.courseid',
    //                 'college_courses.courseabrv'
    //             )
    //             ->join('studinfo', 'college_enrolledstud.studid', '=', 'studinfo.id')
    //             ->join('college_courses', 'college_enrolledstud.courseid', '=', 'college_courses.id')
    //             ->join('college_sections', 'college_enrolledstud.sectionID', '=', 'college_sections.id')
    //             ->where('college_enrolledstud.deleted', 0)
    //             ->where('college_enrolledstud.studstatus', '!=', 4) // not withdrawn
    //             ->when($levelid, function ($query) use ($levelid) {
    //                 return $query->where('college_enrolledstud.yearLevel', $levelid);
    //             })
    //             ->when($courseid, function ($query) use ($courseid) {
    //                 return $query->where('college_enrolledstud.courseID', $courseid);
    //             })
    //             ->where('college_enrolledstud.syid', $syid)
    //             ->where('college_enrolledstud.semid', $semid)
    //             ->where('college_enrolledstud.deleted', 0)
    //             ->orderBy('studinfo.lastname')
    //             ->orderBy('studinfo.firstname')
    //             // ->take(2)
    //             ->get();


    //         // Combine all students
    //         $students = $regularStudents->merge($shStudents)->merge($collegeStudents);
    //         // Get school year and semester info
    //         $selectedschoolyear = DB::table('sy')->where('id', $syid)->first()->sydesc;
    //         $selectedsemester = DB::table('semester')->where('id', $semid)->first()->semester;

    //         // Prepare data for each student
    //         $statements = [];
    //         foreach ($students as $student) {
    //             $studid = $student->id;

    //             // Get ledger entries for the student with payment allocation
    //             $ledgerWithPayments = DB::select("
    //                 SELECT
    //                     c.id,
    //                     c.studid,
    //                     c.classid,
    //                     c.particulars,
    //                     c.amount,
    //                     c.createddatetime,
    //                     c.void,
    //                     c.deleted,
    //                     c.groupname,
    //                     GREATEST(
    //                         LEAST(
    //                             total_payments - (running_amount - c.amount),
    //                             c.amount
    //                         ),
    //                         0
    //                     ) AS payment_allocated,
    //                     c.amount - GREATEST(
    //                         LEAST(
    //                             total_payments - (running_amount - c.amount),
    //                             c.amount
    //                         ),
    //                         0
    //                     ) AS remaining_balance
    //                 FROM (
    //                     SELECT
    //                         a.id,
    //                         a.studid,
    //                         a.classid,
    //                         a.particulars,
    //                         a.amount,
    //                         a.createddatetime,
    //                         a.void,
    //                         a.deleted,
    //                         chrngsetup.groupname,
    //                         (
    //                             SELECT SUM(amount)
    //                             FROM studledger b
    //                             WHERE b.studid = a.studid
    //                               AND b.semid = a.semid
    //                               AND b.syid = a.syid
    //                               AND b.amount > 0
    //                               AND b.deleted = 0
    //                               AND b.id <= a.id
    //                         ) AS running_amount,
    //                         (
    //                             SELECT SUM(payment)
    //                             FROM studledger p
    //                             WHERE p.studid = a.studid
    //                               AND p.semid = a.semid
    //                               AND p.syid = a.syid
    //                               AND p.payment > 0
    //                               AND p.deleted = 0
    //                         ) AS total_payments
    //                     FROM studledger a
    //                     LEFT JOIN chrngsetup ON a.classid = chrngsetup.classid
    //                     WHERE a.studid = ?
    //                       AND a.semid = ?
    //                       AND a.syid = ?
    //                       AND a.amount > 0
    //                       AND a.deleted = 0
    //                       AND a.void = 0
    //                 ) c
    //                 ORDER BY c.id
    //             ", [$studid, $semid, $syid]);

    //             // Convert to collection for easier processing
    //             $ledger = collect($ledgerWithPayments);

    //             // Get payments
    //             // $payments = DB::table('studledger')
    //             //     ->select('studledger.*', 'chrngsetup.groupname')
    //             //     ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
    //             //     ->where('studledger.studid', $studid)
    //             //     ->where('studledger.syid', $syid)
    //             //     ->where(function ($q) use ($semid, $student) {
    //             //         if ($student->levelid == 14 || $student->levelid == 15) {
    //             //             if ($semid == 3) {
    //             //                 $q->where('semid', 3);
    //             //             } else {
    //             //                 if (DB::table('schoolinfo')->first()->shssetup == 0) {
    //             //                     $q->where('semid', $semid);
    //             //                 } else {
    //             //                     $q->where('semid', '!=', 3);
    //             //                 }
    //             //             }
    //             //         } elseif ($student->levelid >= 17 && $student->levelid <= 21) {
    //             //             $q->where('semid', $semid);
    //             //         }
    //             //     })
    //             //     ->where('payment', '>', 0)
    //             //     ->where('studledger.deleted', 0)
    //             //     ->where('studledger.void', 0)
    //             //     ->orderBy('studledger.createddatetime', 'asc')
    //             //     ->get();

    //             // //DUE FOR THE MONTH//
    //             // $monthinword = '';
    //             // $monthsetup = db::table('monthsetup')->where('id', $monthsetupid)->first();

    //             // if ($monthsetup) {
    //             //     $monthinword = $monthsetup->description;
    //             // } else {
    //             //     $monthinword = '';
    //             // }

    //             // Get payments
    //             $payments = DB::table('studledger')
    //                 ->select('studledger.*', 'chrngsetup.groupname')
    //                 ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
    //                 ->where('studledger.studid', $studid)
    //                 ->where('studledger.syid', $syid)
    //                 ->where(function ($q) use ($semid, $student) {
    //                     if ($student->levelid == 14 || $student->levelid == 15) {
    //                         if ($semid == 3) {
    //                             $q->where('semid', 3);
    //                         } else {
    //                             if (DB::table('schoolinfo')->first()->shssetup == 0) {
    //                                 $q->where('semid', $semid);
    //                             } else {
    //                                 $q->where('semid', '!=', 3);
    //                             }
    //                         }
    //                     } elseif ($student->levelid >= 17 && $student->levelid <= 21) {
    //                         $q->where('semid', $semid);
    //                     }
    //                 })
    //                 ->where('payment', '>', 0)
    //                 ->where('studledger.deleted', 0)
    //                 ->where('studledger.void', 0)
    //                 ->orderBy('studledger.createddatetime', 'asc')
    //                 ->get();

    //             //DUE FOR THE MONTH//
    //             $monthinword = '';
    //             $monthsetup = db::table('monthsetup')->where('id', $monthsetupid)->first();

    //             if ($monthsetup) {
    //                 $monthinword = $monthsetup->description;
    //             } else {
    //                 $monthinword = '';
    //             }

    //             // Get monthly dues
    //             $monthdue = FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $monthsetup);

    //             // Calculate totals using the new payment allocation
    //             $totalcharges = $ledger->sum('amount');
    //             $totalpayment = $ledger->sum('payment_allocated');
    //             $totalbalance = $ledger->sum('remaining_balance');
    //             $oldamount = $ledger->where('classid', DB::table('balforwardsetup')->first()->classid)->sum('amount');

    //             // Get level name
    //             $level = DB::table('gradelevel')->where('id', $student->levelid)->first();
    //             $levelname = $level ? $level->levelname : '';
    //             $monthName = Carbon::create()->month($monthsetupid)->format('F');
    //             $monthName = strtoupper($monthName); // Example: JANUARY


    //             // Prepare student data
    //             $statements[] = [
    //                 'studinfo' => $student,
    //                 'levelname' => $levelname,
    //                 'sectionname' => $student->sectionname ?? '',
    //                 'courseabrv' => $student->courseabrv ?? '',
    //                 'ledger' => $ledger,
    //                 'payments' => $payments,
    //                 'monthdue' => $monthdue,
    //                 'totalcharges' => $totalcharges,
    //                 'oldamount' => $oldamount,
    //                 'totalpayment' => $totalpayment,
    //                 'totalbalance' => $totalbalance,
    //                 'monthinword' => $monthinword ?? '',
    //                 'selectedschoolyear' => $selectedschoolyear,
    //                 'selectedsemester' => $selectedsemester,
    //                 'monthName' => $monthName
    //             ];
    //         }

    //         // Get active template
    //         $activeTemplate = DB::table('fn_soa_templates')
    //             ->where('status', 1)
    //             ->where('deleted', 0)
    //             ->first();

    //         // Determine template based on ID
    //         $templateName = 'bulk_statements'; // default
    //         if ($activeTemplate) {
    //             switch ($activeTemplate->id) {
    //                 case 1:
    //                     $templateName = 'bulk_statements'; // DEFAULT
    //                     break;
    //                 case 2:
    //                     $templateName = 'bulk_statement_temp2';
    //                     break;
    //                 case 3:
    //                     $templateName = 'bulk_statement_temp3';
    //                     break;
    //                 case 4:
    //                     $templateName = 'bulk_statement_temp4';
    //                     break;
    //                 default:
    //                     $templateName = 'bulk_statements';
    //                     break;
    //             }
    //         }

    //         // Generate PDF
    //         // $pdf = PDF::loadView('finance.reports.pdf.bulk_statements', [
    //         //     'statements' => $statements,
    //         //     'schoolinfo' => $schoolinfo,
    //         //     'syid' => $syid,
    //         //     'semid' => $semid,
    //         //     'monthsetupid' => $monthsetupid,
    //         // ])->setPaper('letter', 'portrait');

    //         return view('finance.reports.pdf.' . $templateName, [
    //             'statements' => $statements,
    //             'schoolinfo' => $schoolinfo,
    //             'syid' => $syid,
    //             'semid' => $semid,
    //             'monthsetupid' => $monthsetupid,
    //         ]);

    //         // return $pdf->stream('Bulk_Statements_of_Account.pdf');
    //     } else {
    //         if ($request->get('selectedmonth') > 0) {
    //             $monthData = DB::table('monthsetup')->where('id', $monthsetupid)->first();
    //             if ($monthData && date_parse($monthData->description)['month']) {
    //                 $monthNum = date_parse($monthData->description)['month'];
    //                 $month = strlen($monthNum) == 1 ? '0' . $monthNum : $monthNum;
    //             }
    //         }

    //         // Get school information
    //         $schoolinfo = DB::table('schoolinfo')->first();

    //         // Get all active students based on level
    //         $students = collect();

    //         // 1. Get regular students (K-12)
    //         $regularStudents = DB::table('enrolledstud')
    //             ->select(
    //                 'studinfo.id',
    //                 'studinfo.sid',
    //                 'studinfo.lastname',
    //                 'studinfo.firstname',
    //                 'studinfo.middlename',
    //                 'studinfo.suffix',
    //                 'enrolledstud.levelid',
    //                 'enrolledstud.sectionid',
    //                 'sections.sectionname'
    //             )
    //             ->join('studinfo', 'enrolledstud.studid', '=', 'studinfo.id')
    //             ->leftJoin('sections', 'enrolledstud.sectionid', '=', 'sections.id')
    //             ->where('enrolledstud.syid', $syid)
    //             ->where('enrolledstud.deleted', 0)
    //             ->where('enrolledstud.studstatus', '!=', 4) // not withdrawn
    //             ->when($levelid, function ($query) use ($levelid) {
    //                 return $query->where('enrolledstud.levelid', $levelid);
    //             })
    //             ->orderBy('studinfo.lastname')
    //             ->orderBy('studinfo.firstname')
    //             // ->take(2)
    //             ->get();

    //         // 2. Get senior high students if applicable
    //         $shStudents = DB::table('sh_enrolledstud')
    //             ->select(
    //                 'studinfo.id',
    //                 'studinfo.sid',
    //                 'studinfo.lastname',
    //                 'studinfo.firstname',
    //                 'studinfo.middlename',
    //                 'studinfo.suffix',
    //                 'sh_enrolledstud.levelid',
    //                 'sh_enrolledstud.sectionid',
    //                 'sections.sectionname'
    //             )
    //             ->join('studinfo', 'sh_enrolledstud.studid', '=', 'studinfo.id')
    //             ->leftJoin('sections', 'sh_enrolledstud.sectionid', '=', 'sections.id')
    //             ->where('sh_enrolledstud.deleted', 0)
    //             ->where('sh_enrolledstud.studstatus', '!=', 4) // not withdrawn
    //             ->when($levelid, function ($query) use ($levelid) {
    //                 return $query->where('sh_enrolledstud.levelid', $levelid);
    //             })
    //             ->orderBy('studinfo.lastname')
    //             ->orderBy('studinfo.firstname')
    //             // ->take(2)
    //             ->get();



    //         // 3. Get college students
    //         $collegeStudents = DB::table('college_enrolledstud')
    //             ->select(
    //                 'studinfo.id',
    //                 'studinfo.sid',
    //                 'studinfo.lastname',
    //                 'studinfo.firstname',
    //                 'studinfo.middlename',
    //                 'studinfo.suffix',
    //                 'college_enrolledstud.yearLevel as levelid',
    //                 'college_enrolledstud.courseid',
    //                 'college_courses.courseabrv'
    //             )
    //             ->join('studinfo', 'college_enrolledstud.studid', '=', 'studinfo.id')
    //             ->join('college_courses', 'college_enrolledstud.courseid', '=', 'college_courses.id')
    //             ->join('college_sections', 'college_enrolledstud.sectionID', '=', 'college_sections.id')
    //             ->where('college_enrolledstud.deleted', 0)
    //             ->where('college_enrolledstud.studstatus', '!=', 4) // not withdrawn
    //             ->when($levelid, function ($query) use ($levelid) {
    //                 return $query->where('college_enrolledstud.yearLevel', $levelid);
    //             })
    //             ->when($courseid, function ($query) use ($courseid) {
    //                 return $query->where('college_enrolledstud.courseID', $courseid);
    //             })
    //             ->where('college_enrolledstud.syid', $syid)
    //             ->where('college_enrolledstud.semid', $semid)
    //             ->where('college_enrolledstud.deleted', 0)
    //             ->orderBy('studinfo.lastname')
    //             ->orderBy('studinfo.firstname')
    //             // ->take(2)
    //             ->get();


    //         // Combine all students
    //         $students = $regularStudents->merge($shStudents)->merge($collegeStudents);
    //         // Get school year and semester info
    //         $selectedschoolyear = DB::table('sy')->where('id', $syid)->first()->sydesc;
    //         $selectedsemester = DB::table('semester')->where('id', $semid)->first()->semester;

    //         // Prepare data for each student
    //         $statements = [];
    //         foreach ($students as $student) {
    //             $studid = $student->id;

    //             // Get ledger entries for the student with payment allocation
    //             $ledgerWithPayments = DB::select("
    //                 SELECT
    //                     c.id,
    //                     c.studid,
    //                     c.classid,
    //                     c.particulars,
    //                     c.amount,
    //                     c.createddatetime,
    //                     c.void,
    //                     c.deleted,
    //                     c.groupname,
    //                     GREATEST(
    //                         LEAST(
    //                             total_payments - (running_amount - c.amount),
    //                             c.amount
    //                         ),
    //                         0
    //                     ) AS payment_allocated,
    //                     c.amount - GREATEST(
    //                         LEAST(
    //                             total_payments - (running_amount - c.amount),
    //                             c.amount
    //                         ),
    //                         0
    //                     ) AS remaining_balance
    //                 FROM (
    //                     SELECT
    //                         a.id,
    //                         a.studid,
    //                         a.classid,
    //                         a.particulars,
    //                         a.amount,
    //                         a.createddatetime,
    //                         a.void,
    //                         a.deleted,
    //                         chrngsetup.groupname,
    //                         (
    //                             SELECT SUM(amount)
    //                             FROM studledger b
    //                             WHERE b.studid = a.studid
    //                               AND b.semid = a.semid
    //                               AND b.syid = a.syid
    //                               AND b.amount > 0
    //                               AND b.deleted = 0
    //                               AND b.id <= a.id
    //                         ) AS running_amount,
    //                         (
    //                             SELECT SUM(payment)
    //                             FROM studledger p
    //                             WHERE p.studid = a.studid
    //                               AND p.semid = a.semid
    //                               AND p.syid = a.syid
    //                               AND p.payment > 0
    //                               AND p.deleted = 0
    //                         ) AS total_payments
    //                     FROM studledger a
    //                     LEFT JOIN chrngsetup ON a.classid = chrngsetup.classid
    //                     WHERE a.studid = ?
    //                       AND a.semid = ?
    //                       AND a.syid = ?
    //                       AND a.amount > 0
    //                       AND a.deleted = 0
    //                       AND a.void = 0
    //                 ) c
    //                 ORDER BY c.id
    //             ", [$studid, $semid, $syid]);

    //             // Convert to collection for easier processing
    //             $ledger = collect($ledgerWithPayments);

    //             // dd($ledger);
    //             // Get payments
    //             $payments = DB::table('studledger')
    //                 ->select('studledger.*', 'chrngsetup.groupname')
    //                 ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
    //                 ->where('studledger.studid', $studid)
    //                 ->where('studledger.syid', $syid)
    //                 ->where(function ($q) use ($semid, $student) {
    //                     if ($student->levelid == 14 || $student->levelid == 15) {
    //                         if ($semid == 3) {
    //                             $q->where('semid', 3);
    //                         } else {
    //                             if (DB::table('schoolinfo')->first()->shssetup == 0) {
    //                                 $q->where('semid', $semid);
    //                             } else {
    //                                 $q->where('semid', '!=', 3);
    //                             }
    //                         }
    //                     } elseif ($student->levelid >= 17 && $student->levelid <= 21) {
    //                         $q->where('semid', $semid);
    //                     }
    //                 })
    //                 ->where('payment', '>', 0)
    //                 ->where('studledger.deleted', 0)
    //                 ->where('studledger.void', 0)
    //                 ->orderBy('studledger.createddatetime', 'asc')
    //                 ->get();

    //             //DUE FOR THE MONTH//
    //             $monthinword = '';
    //             $monthsetup = db::table('monthsetup')->where('id', $monthsetupid)->first();

    //             if ($monthsetup) {
    //                 $monthinword = $monthsetup->description;
    //             } else {
    //                 $monthinword = '';
    //             }



    //             // Get monthly dues
    //             $monthdue = FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $monthsetup);

    //             // Calculate totals using the new payment allocation
    //             $totalcharges = $ledger->sum('amount');
    //             $totalpayment = $ledger->sum('payment_allocated');
    //             $totalbalance = $ledger->sum('remaining_balance');
    //             $oldamount = $ledger->where('classid', DB::table('balforwardsetup')->first()->classid)->sum('amount');

    //             // Get level name
    //             $level = DB::table('gradelevel')->where('id', $student->levelid)->first();
    //             $levelname = $level ? $level->levelname : '';
    //             $monthName = Carbon::create()->month($monthsetupid)->format('F');
    //             $monthName = strtoupper($monthName); // Example: JANUARY

    //             // Prepare student data
    //             $statements[] = [
    //                 'studinfo' => $student,
    //                 'levelname' => $levelname,
    //                 'sectionname' => $student->sectionname ?? '',
    //                 'courseabrv' => $student->courseabrv ?? '',
    //                 'ledger' => $ledger,
    //                 'payments' => $payments,
    //                 'monthdue' => $monthdue,
    //                 'totalcharges' => $totalcharges,
    //                 'oldamount' => $oldamount,
    //                 'totalpayment' => $totalpayment,
    //                 'totalbalance' => $totalbalance,
    //                 'monthinword' => $monthinword ?? '',
    //                 'selectedschoolyear' => $selectedschoolyear,
    //                 'selectedsemester' => $selectedsemester,
    //                 'monthName' => $monthName
    //             ];
    //         }

    //         // Get active template
    //         $activeTemplate = DB::table('fn_soa_templates')
    //             ->where('status', 1)
    //             ->where('deleted', 0)
    //             ->first();

    //         // Determine template based on ID
    //         $templateName = 'bulk_statements'; // default
    //         if ($activeTemplate) {
    //             switch ($activeTemplate->id) {
    //                 case 0:
    //                     $templateName = 'bulk_statements'; // DEFAULT
    //                     break;
    //                 case 1:
    //                     $templateName = 'bulk_statement_temp2';
    //                     break;
    //                 case 2:
    //                     $templateName = 'bulk_statement_temp3';
    //                     break;
    //                 case 3:
    //                     $templateName = 'bulk_statement_temp4';
    //                     break;
    //                 default:
    //                     $templateName = 'bulk_statements';
    //                     break;
    //             }
    //         }

    //         // Generate PDF
    //         // $pdf = PDF::loadView('finance.reports.pdf.bulk_statements', [
    //         //     'statements' => $statements,
    //         //     'schoolinfo' => $schoolinfo,
    //         //     'syid' => $syid,
    //         //     'semid' => $semid,
    //         //     'monthsetupid' => $monthsetupid,
    //         // ])->setPaper('letter', 'portrait');
    //         // dd($statements);
    //         return view('finance.reports.pdf.' . $templateName, [
    //             'statements' => $statements,
    //             'schoolinfo' => $schoolinfo,
    //             'syid' => $syid,
    //             'semid' => $semid,
    //             'monthsetupid' => $monthsetupid,
    //         ]);

    //         // return $pdf->stream('Bulk_Statements_of_Account.pdf');

    //     }

    // }

    public function getBulkStatements(Request $request)
    {
        $semid = $request->get('selectedsemester');
        $syid = $request->get('selectedschoolyear');
        $monthsetupid = $request->get('selectedmonth');
        $levelid = $request->get('levelid');
        $courseid = $request->get('courseid');

        if (!in_array($levelid, [14, 15]) && ($levelid < 17 || $levelid > 21)) {
            $semid = 1;
        }

        $month = null;
        if ($request->get('selectedmonth', 0) > 0) {
            $monthData = DB::table('monthsetup')->where('id', $monthsetupid)->first();
            if ($monthData && isset($monthData->description)) {
                $parsedMonth = date_parse($monthData->description);
                if (isset($parsedMonth['month']) && $parsedMonth['month']) {
                    $monthNum = $parsedMonth['month'];
                    $month = strlen($monthNum) == 1 ? '0' . $monthNum : $monthNum;
                }
            }
        }

        // Cache single-time queries
        $schoolinfo = DB::table('schoolinfo')->first();
        $balforwardsetup = DB::table('balforwardsetup')->first();
        $oldclassid = $balforwardsetup->classid ?? null;

        $regularStudents = DB::table('enrolledstud')
            ->select('studinfo.id', 'studinfo.sid', 'studinfo.lastname', 'studinfo.firstname',
                'studinfo.middlename', 'studinfo.suffix', 'enrolledstud.levelid', 'enrolledstud.dateenrolled',
                'enrolledstud.sectionid', 'sections.sectionname','studinfo.feesid')
            ->join('studinfo', 'enrolledstud.studid', '=', 'studinfo.id')
            ->leftJoin('sections', 'enrolledstud.sectionid', '=', 'sections.id')
            ->where('enrolledstud.syid', $syid)
            ->where('enrolledstud.deleted', 0)
            ->where('enrolledstud.studstatus', '!=', 4)
            ->when($levelid, function($q) use ($levelid) {
                return $q->where('enrolledstud.levelid', $levelid);
            })
            ->get();
     
        $shStudents = DB::table('sh_enrolledstud')
            ->select('studinfo.id', 'studinfo.sid', 'studinfo.lastname', 'studinfo.firstname',
                'studinfo.middlename', 'studinfo.suffix', 'sh_enrolledstud.levelid', 'sh_enrolledstud.dateenrolled',
                'sh_enrolledstud.sectionid', 'sections.sectionname','studinfo.feesid')
            ->join('studinfo', 'sh_enrolledstud.studid', '=', 'studinfo.id')
            ->leftJoin('sections', 'sh_enrolledstud.sectionid', '=', 'sections.id')
            ->where('sh_enrolledstud.syid', $syid)
            ->where('sh_enrolledstud.deleted', 0)
            ->where('sh_enrolledstud.studstatus', '!=', 4)
            ->when($levelid, function($q) use ($levelid) {
                return $q->where('sh_enrolledstud.levelid', $levelid);
            })
            ->get();

        $collegeStudents = DB::table('college_enrolledstud')
            ->select('studinfo.id', 'studinfo.sid', 'studinfo.lastname', 'studinfo.firstname',
                'studinfo.middlename', 'studinfo.suffix', 'college_enrolledstud.yearLevel as levelid', 'college_enrolledstud.date_enrolled as dateenrolled',
                'college_enrolledstud.courseid', 'college_courses.courseabrv','studinfo.feesid')
            ->join('studinfo', 'college_enrolledstud.studid', '=', 'studinfo.id')
            ->join('college_courses', 'college_enrolledstud.courseid', '=', 'college_courses.id')
            ->where('college_enrolledstud.syid', $syid)
            ->where('college_enrolledstud.semid', $semid)
            ->where('college_enrolledstud.deleted', 0)
            ->where('college_enrolledstud.studstatus', '!=', 4)
            ->when($levelid, function($q) use ($levelid) {
                return $q->where('college_enrolledstud.yearLevel', $levelid);
            })
            ->when($courseid, function($q) use ($courseid) {
                return $q->where('college_enrolledstud.courseID', $courseid);
            })
            ->get();

        $students = $regularStudents->merge($shStudents)->merge($collegeStudents);
        // $students = collect($students)->where('id', 1093)->values(); // FOR TESTING PURPOSES ONLY REMOVE ON PRODUCTION

        $selectedschoolyear = optional(DB::table('sy')->where('id', $syid)->first())->sydesc ?? '';
        $selectedsemester = optional(DB::table('semester')->where('id', $semid)->first())->semester ?? '';

        // Pre-load all lookup data to avoid repeated queries
        $acadid = DB::table('gradelevel')->get()->keyBy('id');
        $ranges = DB::table('month_range_setup')->where('deleted', 0)->get()->keyBy('acadprogid');
        $monthsetup = DB::table('monthsetup')->where('monthid', $monthsetupid)->first();
        
        $statements = [];
        $currentYear = date('Y');

        // Get active template
        $activeTemplate = DB::table('fn_soa_templates')
            ->where('status', 1)
            ->where('deleted', 0)
            ->first();

        // Extract student IDs for batch queries
        $studentIds = $students->pluck('id')->toArray();
        
        // Pre-load college-related data (for college students only)
        $collegeStudentIds = $students->where('levelid', '>=', 17)->where('levelid', '<=', 25)->pluck('id')->toArray();
        $ausubjects = collect();
        $college_subjects = collect();
        $college_prospectus = collect();
        
        if (!empty($collegeStudentIds)) {
            $ausubjects = DB::table('tuition_assessmentunit')
                ->where('deleted', 0)
                ->get()
                ->keyBy('subjid');

            $college_subjects = DB::table('college_subjects')
                ->where('deleted', 0)
                ->get()
                ->keyBy('id');

            $college_prospectus = DB::table('college_prospectus')
                ->where('deleted', 0)
                ->get()
                ->keyBy('id');
        }
        
        // Batch load all tuition data
        $allTuitionHeaders = DB::table('tuitionheader')
            ->select('tuitionheader.id', 'tuitiondetail.id as tuitiondetailid', 'syid', 'levelid', 'grantee.description', 
                'itemclassification.description as particulars', 'amount', 'itemclassification.id as classid', 
                'pschemeid', 'semid', 'istuition', 'persubj', 'permop', 'permopid', 'tuitionheader.id as headerid')
            ->leftjoin('tuitiondetail', 'tuitionheader.id', '=', 'tuitiondetail.headerid')
            ->join('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
            ->join('grantee', 'tuitionheader.grantee', '=', 'grantee.id')
            ->whereIn('tuitionheader.id', $students->pluck('feesid')->filter()->toArray())
            ->where('tuitiondetail.deleted', 0)
            ->where('pschemeid', '!=', null)
            ->get()
            ->groupBy('headerid');

        // Batch load all student fees
        $allStudentFees = DB::table('tuitionheader')
            ->select('tuitionheader.id as headerid', 'tuitiondetail.classificationid', 'itemclassification.description as particulars', 
                'tuitiondetail.amount', 'tuitiondetail.istuition', 'tuitiondetail.persubj', 'tuitiondetail.permop', 'tuitiondetail.permopid')
            ->join('tuitiondetail', 'tuitionheader.id', '=', 'tuitiondetail.headerid')
            ->join('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
            ->where('tuitionheader.deleted', 0)
            ->where('tuitiondetail.deleted', 0)
            ->whereIn('tuitionheader.id', $students->pluck('feesid')->filter()->toArray())
            ->where('tuitionheader.syid', $syid)
            ->where('tuitionheader.semid', $semid)
            ->get()
            ->groupBy('headerid');

        // Batch load all payments
        $allPayments = DB::table('chrngtrans as ct')
            ->select('ct.id', 'ct.studid', 'ct.syid', 'ct.semid', 'ct.transdate', 'ct.ornum', 'ct.transno', 
                'cd.classid', 'cd.amount', 'cd.chrngtransid', 'cd.itemKind')
            ->leftJoin('chrngtransdetail as cd', 'ct.id', '=', 'cd.chrngtransid')
            ->where('cd.itemKind', 0)
            ->whereIn('ct.studid', $studentIds)
            ->where('ct.syid', $syid)
            ->where('ct.semid', $semid)
            ->where('ct.cancelled', 0)
            ->get()
            ->groupBy('studid');

        // Batch load all discounts
        $allDiscounts = DB::table('studdiscounts')
            ->select('studdiscounts.*', 'studdiscounts.discamount as amount')
            ->where('syid', $syid)
            ->where('semid', $semid)
            ->whereIn('studid', $studentIds)
            ->where('deleted', 0)
            ->get()
            ->groupBy('studid');

        // Batch load all adjustments
        $allAdjustments = DB::table('adjustments')
            ->select('adjustments.id', 'adjustments.classid as classificationid', 'adjustments.classid', 
                'adjustments.description as particulars', 'adjustments.amount', 'adjustmentdetails.createddatetime', 
                'adjustments.isdebit', 'adjustments.iscredit', 'adjustmentdetails.studid')
            ->join('adjustmentdetails', 'adjustments.id', '=', 'adjustmentdetails.headerid')
            ->where('adjustments.syid', $syid)
            ->where('adjustments.semid', $semid)
            ->whereIn('adjustmentdetails.studid', $studentIds)
            ->where('adjustments.deleted', 0)
            ->where('adjustmentdetails.deleted', 0)
            ->get()
            ->groupBy('studid');

        // Batch load old accounts
        $allOldAccounts = DB::table('studledger')
            ->select('studledger.studid', 'studledger.classid as classificationid', 'studledger.particulars', 
                'studledger.amount', 'studledger.createddatetime')
            ->whereIn('studid', $studentIds)
            ->where('syid', $syid)
            ->where('semid', $semid)
            ->where('classid', $balforwardsetup->classid)
            ->where('particulars', 'not like', '%ADJ:%')
            ->where('deleted', 0)
            ->where('payment', 0)
            ->get()
            ->groupBy('studid');

        // Batch load first payments for downpayment
        $allFirstPayments = DB::table('chrngtrans as ct')
            ->select('ct.studid', 'ct.id')
            ->whereIn('ct.studid', $studentIds)
            ->where('ct.syid', $syid)
            ->where('ct.semid', $semid)
            ->where('ct.cancelled', 0)
            ->groupBy('ct.studid')
            ->get()
            ->keyBy('studid');
        
        foreach ($students as $student) {
            $levelid = $student->levelid ?? 0;
            $studid = $student->id ?? 0;

            if (!$studid) continue;

            $gradeLevel = $acadid->get($levelid);
            $acadprogid = $gradeLevel->acadprogid ?? null;
            
            $range = $ranges->get($acadprogid);
            
            // Fallback if not found and acadprogid is 5
            if (!$range && $acadprogid == 5) {
                $range = $ranges->get(6);
            }

            // if template 3 
            if($activeTemplate && $activeTemplate->id == 3){
                $range->wdp = 1;
                $range->wdiscount = 1;
                $range->wadjustment = 1;
                $range->woldacc = 1;
            }
           
            $studentFessTotal = 0;
            $studentPaymentsTotal = 0;
            $total_discount = 0;
            $total_adjustment_debit = 0;
            $total_adjustment_credit = 0;
            $totalcharges = 0;
            $totalpayment = 0;
            $totaldiscount = 0;

            $classidArray = [];
            $studentAllFees = collect();
            $studentAllPayments = collect();

            // ----------------------------------------------------------

            // Use pre-loaded tuition data
            $tuition = $allTuitionHeaders->get($student->feesid, collect());
        
            if ($tuition->isEmpty()) {
                $_fees = db::table('tuitionheader')
                    ->where('syid', $syid)
                    ->where(function ($q) use ($levelid, $semid) {
                        if ($levelid >= 17 && $levelid <= 25) {
                            $q->where('semid', $semid);
                        }
                    })
                    ->where('levelid', $levelid)
                    ->where('deleted', 0)
                    ->first();

                if ($_fees) {
                    $feesid = $_fees->id;

                    $tuition = db::table('tuitionheader')
                        ->select('tuitionheader.id', 'tuitiondetail.id as tuitiondetailid', 'syid', 'levelid', 'grantee.description', 'itemclassification.description as particulars', 'amount', 'itemclassification.id as classid', 'pschemeid', 'semid', 'istuition', 'persubj', 'permop', 'permopid')
                        ->leftjoin('tuitiondetail', 'tuitionheader.id', '=', 'tuitiondetail.headerid')
                        ->join('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
                        ->join('grantee', 'tuitionheader.grantee', '=', 'grantee.id')
                        ->where('tuitionheader.id', $student->feesid)
                        ->where('tuitiondetail.deleted', 0)
                        ->get();
                }
            }

            if ($levelid >= 17 && $levelid <= 25) {
                $ausubjects = db::table('tuition_assessmentunit')
                    ->where('deleted', 0)
                    ->get();

                $college_subjects = DB::table('college_subjects')
                    ->where('deleted', 0)
                    ->get();

                $college_prospectus = DB::table('college_prospectus')
                    ->where('deleted', 0)
                    ->get();

                $college_subjects = collect($college_subjects);
                $college_prospectus = collect($college_prospectus);
                $ausubjects = collect($ausubjects);
                // $totalunits = db::table('college_studsched')
                //     ->select(db::raw('SUM(lecunits) + SUM(labunits) AS totalunits'))
                //     ->join('college_classsched', 'college_studsched.schedid', '=', 'college_classsched.id')
                //     ->join('college_prospectus', 'college_classsched.subjectID', '=', 'college_prospectus.id')
                //     ->join('college_sections', 'college_classsched.sectionID', '=', 'college_sections.id')
                //     ->where('college_studsched.studid', $studid)
                //     ->where('college_studsched.deleted', 0)
                //     ->where('college_classsched.deleted', 0)
                //     ->where('college_classsched.syID', $syid)
                //     ->where('college_classsched.semesterID', $semid)
                //     ->where('college_sections.section_specification', '!=', 2)
                //     ->where('college_studsched.schedstatus', '!=', 'DROPPED')
                //     ->first();

                $units = 0;
                $subjcount = 0;
                $finance = null;
                $studentloads = CollegeStudentLoadingController::getAddedStudentLoading($studid, 'all', $syid, $semid, 0, null, null);
            
                $studentloads = collect($studentloads);
                $studentloads = $studentloads['original']['studentLoading'];
                $studentloads = collect($studentloads)->where('loadStatus', 0)->values();
                foreach ($studentloads as $loads) {
                    // $units += $loads['totalUnits'];
                    // $subjcount += 1;

                    $prospectus = $college_prospectus->where('id', $loads['subjectID'])->first();

                    if ($prospectus) {
                        $getsubject = $college_subjects->where('id', $prospectus->subjectID)->first();
                        if ($getsubject) {
                            $getau = $ausubjects->where('subjid', $getsubject->id)->first();
                            if ($getau) {
                                $units += $getau->assessmentunit;
                            } else {
                                $units += $loads['lecunits'] + $loads['labunits'];
                            }
                        }
                    } else {
                        $units += $loads['lecunits'] + $loads['labunits'];
                    }
                }
            }
            // ----------------------------------------------------------

            // Use pre-loaded student fees
            $student_fees = $allStudentFees->get($student->feesid, collect())
                ->map(function($item) use ($student) {
                    $item->createddatetime = $student->dateenrolled;
                    $item->source = 'school_fees'; // Add source identifier
                    return $item;
                });
            
             foreach ($student_fees as $tui) {
                if ($levelid >= 17 && $levelid <= 25) {
                $totalunits = 0;

                if ($tui->istuition == 1) {
                    // echo $tui->amount . ' * ' . $units;
                    $tuitionamount = $units > 0 ? $tui->amount * $units : $tui->amount;
                    $college_particulars = ' | ' . $units . ' Units';
                } else {
                    $tuitionamount = $tui->amount;
                    $college_particulars = '';
                }

                if ($tui->persubj == 1) {

                    $totalsubj = DB::table('college_loadsubject')
                        ->where('college_loadsubject.studid', $studid)
                        ->where('college_loadsubject.deleted', 0)
                        ->join('college_enrolledstud', 'college_loadsubject.studid', 'college_enrolledstud.studid')
                        ->where('college_enrolledstud.studstatus', '!=', 'DROPPED')
                        ->join('college_sections', function ($join) {
                            $join->on('college_loadsubject.sectionID', '=', 'college_sections.id');
                            $join->where('college_sections.deleted', 0);
                        })
                        ->join('college_prospectus', function ($join) {
                            $join->on('college_loadsubject.subjectID', '=', 'college_prospectus.id');
                            $join->where('college_loadsubject.deleted', 0);
                        })
                        ->join('college_classsched', function ($join) use ($syid, $semid) {
                            $join->on('college_sections.id', '=', 'college_classsched.sectionid');
                            $join->on('college_prospectus.id', '=', 'college_classsched.subjectID');
                            $join->where('college_classsched.syid', $syid);
                            $join->where('college_classsched.deleted', 0);
                            $join->where('college_classsched.semesterID', $semid);
                        })
                        
                        ->leftJoin('college_scheddetail', function ($join) {
                            $join->on('college_classsched.id', '=', 'college_scheddetail.headerid');
                            $join->where('college_scheddetail.deleted', 0);
                        })
                        ->leftJoin('days', function ($join) {
                            $join->on('college_scheddetail.day', '=', 'days.id');
                        })
                        ->leftJoin('college_instructor', function ($join) {
                            $join->where('college_instructor.deleted', 0);
                            $join->on('college_classsched.id', '=', 'college_instructor.classschedid');
                        })
                        ->leftJoin('teacher', function ($join) {
                            $join->on('college_instructor.teacherID', '=', 'teacher.id');
                        })
                        ->leftJoin('rooms', function ($join) {
                            $join->on('college_scheddetail.roomid', '=', 'rooms.id');
                            $join->where('rooms.deleted', 0);
                        })
                        ->select(
                            'days.id as daysort',
                            'days.description',
                            'college_classsched.id',
                            'college_scheddetail.id as schedid',
                            'college_scheddetail.etime',
                            'college_scheddetail.stime',
                            'college_classsched.subjectUnit',
                            'college_prospectus.subjDesc',
                            'college_prospectus.subjCode',
                            'college_prospectus.lecunits',
                            'college_prospectus.labunits',
                            'rooms.roomname',
                            'college_scheddetail.scheddetialclass',
                            'college_scheddetail.schedotherclass',
                            'college_prospectus.id as subjID',
                            'lastname',
                            'firstname',
                            'sectionDesc',
                            DB::raw('GROUP_CONCAT( DISTINCT days.id ORDER BY days.id SEPARATOR ", ") as day')

                        
                        )
                        ->groupBy('college_classsched.id')
                        ->get();

                        if(count($totalsubj) > 0){
                            $tuitionamount *= count($totalsubj);
                        }
                    }

                    if ($tui->permop == 1) {
                        $paymentsetup = db::table('paymentsetup')
                            ->where('id', $tui->permopid)
                            ->first();

                        if ($paymentsetup) {
                            $tuitionamount *= $paymentsetup->noofpayment;
                        }
                    }
                    
                } else {
                    $tuitionamount = $tui->amount;
                    $college_particulars = '';
                }

                $tui->amount = $tuitionamount;
            }
            
           
            $classidArray = $student_fees->pluck('classificationid')->unique()->values();

            $studentFessTotal += $student_fees->sum('amount');
            $studentAllFees = $studentAllFees->merge($student_fees);

            $totalcharges += $student_fees->sum('amount');

            // PAYMENTS - Use pre-loaded data
            $allpayments_combined = $allPayments->get($studid, collect())
                ->map(function($item) {
                    $item->source = 'payments';
                    return $item;
                });

            $studentPaymentsTotal += $allpayments_combined->sum('amount');
            $studentAllPayments = $studentAllPayments->merge($allpayments_combined);
            $totalpayment += $allpayments_combined->sum('amount');

            // DISCOUNTS ASSESSMENTS - Use pre-loaded data
            $discount_amount = $allDiscounts->get($studid, collect())
                ->map(function($item) {
                    $item->source = 'discounts';
                    return $item;
                });
            
            $overall_discount = $discount_amount->sum('discamount') ?? 0;

            if($range && isset($range->wdiscount) && $range->wdiscount == 0){
                $studentFessTotal -= $overall_discount;
                $totaldiscount -= $overall_discount;
            } else{
                $studentPaymentsTotal += $overall_discount;
                $totaldiscount += $student_fees->sum('amount');
                $totalpayment += $overall_discount;
            }

            // ADJUSTMENTS ASSESSMENTS - Use pre-loaded data
            $adjustment_amount = $allAdjustments->get($studid, collect())
                ->map(function($item) {
                    $item->source = 'adjustment'; // Add source identifier
                    return $item;
                });
           
            $classidArray = $classidArray->merge(
                $adjustment_amount->where('isdebit', 1)->pluck('classificationid')
            )->unique()->values();

            $studentAllFees = $studentAllFees->merge(collect($adjustment_amount)->where('isdebit', 1)->values());
           
            $total_adjustment_debit += $adjustment_amount->where('isdebit', 1)->sum('amount') ?? 0;
            $total_adjustment_credit += $adjustment_amount->where('iscredit', 1)->whereIn('classificationid', $classidArray)->sum('amount') ?? 0;
            $studentFessTotal += $total_adjustment_debit;
            $studentAllPayments = $studentAllPayments->merge(collect($adjustment_amount)->where('iscredit', 1)->values());

            if($range && isset($range->wadjustment) && $range->wadjustment == 0){
                $studentFessTotal -= $total_adjustment_credit;
            } else {
                $studentPaymentsTotal += $total_adjustment_credit;
                $totalcharges += $total_adjustment_debit;
                $totalpayment += $total_adjustment_credit;
            }
            
            // OLD ACCOUNT ASSESSMENTS - Use pre-loaded data
            $oldaccount_amount = $allOldAccounts->get($studid, collect())
                ->map(function($item) {
                    $item->source = 'old_account'; // Add source identifier
                    return $item;
                });
       
            $classidArray = $classidArray->merge(
                $oldaccount_amount->pluck('classificationid')
            );

            $studentFessTotal += $oldaccount_amount->sum('amount');
            $studentAllFees = $studentAllFees->merge($oldaccount_amount);
            $totalcharges += $oldaccount_amount->sum('amount');

            // OLD ACCOUNT PAYMENTS
            $oldaccount_payments = collect($allpayments_combined->where('classid', $balforwardsetup->classid)->values());
            
            if($range && isset($range->woldacc) && $range->woldacc == 0){
                $studentFessTotal -= $oldaccount_payments->sum('amount');
                $totalpayment -= $oldaccount_payments->sum('amount');
            }


            //FIRST PAYMENT WILL TAG AS DOWNPAYMENT - Use pre-loaded data
            $downpayment = optional($allFirstPayments->get($studid))->id;

            if($downpayment){  // Changed from !$downpayment to $downpayment
                $downpayment_amount = collect($allpayments_combined->where('chrngtransid', $downpayment)->values())->sum('amount');

                if($range && isset($range->wdp) && $range->wdp == 0){
                    $studentFessTotal -= $downpayment_amount;
                    $totalpayment -= $downpayment_amount;
                }
            }
            

            // Calculate division amount
            if ($range) {
                $startMonth = intval($range->sdate ?? 0);
                $endMonth = intval($range->edate ?? 0);

                if ($startMonth > 0 && $endMonth > 0 && $startMonth <= 12 && $endMonth <= 12) {
                    if ($startMonth <= $endMonth) {
                        $divamount = $endMonth - $startMonth + 1;
                    } else {
                        $divamount = (12 - $startMonth + 1) + $endMonth;
                    }
                } else {
                    $divamount = 1;
                }
            } else {
                $divamount = 1;
            }

            $amount = $divamount > 0 ? $studentFessTotal / $divamount : 0;
           
            $payment_array = [];

            if ($range && $divamount > 0) {
                $startMonth = intval($range->sdate ?? 0);
                $endMonth = intval($range->edate ?? 0);
                
                // If the range wraps to the next year
                if ($startMonth > $endMonth) {
                    $runningTotal = 0;
                    $monthIndex = 0;
                    
                    // Add months from startMonth to December (current year)
                    for ($i = $startMonth; $i <= 12; $i++) {
                        $monthIndex++;
                        $isLastMonth = ($monthIndex == $divamount);
                        $monthAmount = $isLastMonth ? ($studentFessTotal - $runningTotal) : round($amount, 2);
                        
                        array_push($payment_array, (object) [
                            'duedate' => date('Y-m-d', strtotime($currentYear . '-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-01')),
                            'amount' => number_format($monthAmount, 2, '.', '')
                        ]);
                        
                        $runningTotal += round($monthAmount, 2);
                    }

                    // Add months from January to endMonth (next year)
                    for ($i = 1; $i <= $endMonth; $i++) {
                        $monthIndex++;
                        $isLastMonth = ($monthIndex == $divamount);
                        $monthAmount = $isLastMonth ? ($studentFessTotal - $runningTotal) : round($amount, 2);
                        
                        array_push($payment_array, (object) [
                            'duedate' => date('Y-m-d', strtotime(($currentYear + 1) . '-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-01')),
                            'amount' => number_format($monthAmount, 2, '.', '')
                        ]);
                        
                        $runningTotal += round($monthAmount, 2);
                    }
                } else {
                    // Simple case: same year
                    $runningTotal = 0;
                    $monthIndex = 0;
                    
                    for ($i = $startMonth; $i <= $endMonth; $i++) {
                        $monthIndex++;
                        $isLastMonth = ($monthIndex == $divamount);
                        $monthAmount = $isLastMonth ? ($studentFessTotal - $runningTotal) : round($amount, 2);
                        
                        array_push($payment_array, (object) [
                            'duedate' => date('Y-m-d', strtotime($currentYear . '-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-01')),
                            'amount' => number_format($monthAmount, 2, '.', '')
                        ]);
                        
                        $runningTotal += round($monthAmount, 2);
                    }
                }
            }
           
            $fees = collect($payment_array);
            $assessment = array();

            foreach ($fees as $fee) {
                $month = date_format(date_create($fee->duedate), 'F');
            
                array_push($assessment, (object) [
                    'duedate' => $fee->duedate,
                    'amount' => number_format($fee->amount, 2, '.', ''),
                    'payment' => 0.00,
                    'balance' => number_format($fee->amount, 2, '.', ''),
                    'particulars' => $month,
                    'totalpaymentnotuition' => '',
                    'paymentnotuition' => ''
                ]);
            }
            
            $combined_data = collect($studentAllFees);
            
            // foreach ($combined_data as $data) {
            //     $data->payable = $studentAllFees->where('classificationid', $data->classificationid ?? null)->sum('amount');
            //     $data->payment = $studentAllPayments->where('classid', $data->classificationid ?? null)->sum('amount');
            //     $data->discount = $discount_amount->where('classid', $data->classificationid ?? null)->whereNotNull('discountid')->sum('amount');
                
            //     $data->ornums = $studentAllPayments
            //         ->where('classid', $data->classificationid ?? null)
            //         ->pluck('ornum')
            //         ->filter(function($ornum) {
            //             return !empty(trim($ornum ?? ''));
            //         })
            //         ->unique()
            //         ->implode(', ');
            // }

            // Group by classificationid only
            $grouped_data = $combined_data->groupBy('classificationid');

            // Transform grouped data back to collection with merged amounts
            $combined_data = $grouped_data->map(function($group) use ($studentAllPayments, $discount_amount) {
                $classificationid = $group->first()->classificationid;
                
                // Sum all amounts for this classificationid
                $totalPayable = $group->sum('amount');
                
                // Merge particulars from all sources, separated by " & "
                $particulars = $group->pluck('particulars')
                    ->filter()
                    ->unique()
                    ->implode(' & ');
                
                // Create merged record
                $merged = $group->first();
                $merged->particulars = $particulars;
                $merged->payable = $totalPayable;
                
                // Calculate payment, discount, and ornums for this classificationid
                $merged->payment = $studentAllPayments
                    ->where('classid', $classificationid)
                    ->sum('amount');
                
                $merged->discount = $discount_amount
                    ->where('classid', $classificationid)
                    ->whereNotNull('discountid')
                    ->sum('amount');
                
                $merged->ornums = $studentAllPayments
                    ->where('classid', $classificationid)
                    ->pluck('ornum')
                    ->filter(function($ornum) {
                        return !empty(trim($ornum ?? ''));
                    })
                    ->unique()
                    ->implode(', ');
                
                return $merged;
            })->values();

            $payments = $combined_data;
            
            // Calculate totals using combined data (including discounts)
            // $totalcharges = $combined_data->sum('payable');
            // $totalpayment = $combined_data->sum('payment');
            // $totaldiscount = $combined_data->sum('discount');
            $totalbalance = $totalcharges - $totalpayment - $totaldiscount;

            // Old amount for old accounts (classid == $oldclassid)
            $oldamount = $combined_data->where('classid', $oldclassid)->sum('payable');

            // Use pre-loaded gradelevel data
            $level = $acadid->get($student->levelid);
            $levelname = $level->levelname ?? '';

            // Month setup + dues (already loaded above in batch)
            $monthdue = collect($assessment);
        
            $statements[] = [
                'studinfo' => $student,
                'levelname' => $levelname,
                'sectionname' => $student->sectionname ?? '',
                'courseabrv' => $student->courseabrv ?? '',
                'ledger' => $combined_data,
                'payments' => $combined_data,
                'discounts' => '',
                'monthdue' => $monthdue,
                'totalcharges' => $totalcharges,
                'totalpayment' => $totalpayment,
                'totaldiscount' => $totaldiscount,
                'totalbalance' => $totalbalance,
                'oldamount' => $oldamount,
                'monthinword' => $monthsetup->description ?? '',
                'selectedschoolyear' => $selectedschoolyear,
                'selectedsemester' => $selectedsemester,
                'studentAllPayments' => $studentAllPayments
            ];
        }

        // Determine template based on ID
        $templateName = 'bulk_statements'; // default
        if ($activeTemplate && isset($activeTemplate->id)) {
            switch ($activeTemplate->id) {
                case 1:
                    $templateName = 'bulk_statements';
                    break;
                case 2:
                    $templateName = 'bulk_statement_temp2';
                    break;
                case 3:
                    $templateName = 'bulk_statement_temp4';
                    break;
                case 4:
                    $templateName = 'bulk_statement_temp4';
                    break;
                default:
                    $templateName = 'bulk_statements';
                    break;
            }
        }

        return view('finance.reports.pdf.' . $templateName, [
            'statements' => $statements,
            'schoolinfo' => $schoolinfo,
            'syid' => $syid,
            'semid' => $semid,
            'monthsetupid' => $monthsetupid,
        ]);
    }



    public function getaccount_v2_backup(Request $request)
    {
        $studid = $request->get('studid');
        $semid = $request->get('selectedsemester');
        $syid = $request->get('selectedschoolyear');
        $monthsetupid = $request->get('selectedmonth');
        $action = $request->get('action');
        $month = null;
        $levelid = $request->levelid;
        $sectionid = 0;
        $courseid = 0;
        $sectionname = '';
        $courseabrv = '';
        $output = '';



        if ($request->get('selectedmonth') > 0) {
            if (date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month']) {
                if (strlen(date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month']) == 1) {
                    $month = '0' . date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month'];
                } else {
                    $month = date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month'];
                }
            }
        }

        $request['syid'] = $syid;
        $request['semid'] = $semid;
        $request['monthid'] = $month;

        $selectedschoolyear = DB::table('sy')
            ->where('id', $syid)
            ->first()
            ->sydesc;

        $selectedsemester = db::table('semester')
            ->where('id', $semid)
            ->first()
            ->semester;

        $studinfo = db::table('studinfo')
            ->select('studinfo.id', 'sid', 'lastname', 'firstname', 'middlename', 'suffix', 'gradelevel.levelname as level', 'levelid')
            ->join('gradelevel', 'studinfo.levelid', '=', 'gradelevel.id')
            ->where(function ($query) use ($studid) {
                if (isset($studid)) {
                    $query->where('studinfo.id', $studid);
                }

            })
            ->when(isset($levelid), function ($query) use ($levelid) {
                $query->where('studinfo.levelid', $levelid);
            })
            ->where('studinfo.deleted', 0)
            ->whereIn('studinfo.studstatus', [1, 2])
            ->get();
        // dd($studinfo);

        $balclassid = db::table('balforwardsetup')->first()->classid;

        foreach ($studinfo as $stud) {

            $studledger = db::table('studledger')
                ->where('deleted', 0)
                ->where('studid', $stud->id)
                ->where('syid', $syid)
                ->where(function ($q) use ($levelid, $semid) {
                    if ($levelid == 14 || $levelid == 15) {
                        if ($semid == 3) {
                            $q->where('semid', 3);
                        } else {
                            if (db::table('schoolinfo')->first()->shssetup == 0) {
                                $q->where('semid', $semid);
                            }
                        }
                    } elseif ($levelid >= 17 && $levelid <= 25) {
                        $q->where('semid', $semid);
                    } else {
                        if ($semid == 3) {
                            $q->where('semid', 3);
                        }
                    }
                })
                ->orderBy('studid')
                ->orderBy('createddatetime')
                ->get();

            $ledgercollect = collect($studledger);

            $ledgerold = array();
            $oldclass = $ledgercollect
                ->where('studid', $stud)
                ->where('classid', $balclassid)
                ->where('amount', '>', 0)
                ->all();

            // return $oldclass;

            foreach ($oldclass as $old) {
                array_push($ledgerold, (object) [
                    'createddatetime' => $old->createddatetime,
                    'particulars' => strtoupper($old->particulars),
                    'amount' => number_format($old->amount, 2)
                ]);

                $oldtotal += $old->amount;
                $totaldebit += $old->amount;
            }

            $estud = db::table('enrolledstud')
                ->select('levelid', 'sectionid', 'studstatus')
                ->where('studid', $stud->id)
                ->where('syid', $syid)
                ->where('deleted', 0)
                ->first();

            if ($estud) {
                $levelid = $estud->levelid;
                $sectionid = $estud->sectionid;

                $section = db::table('sections')
                    ->where('id', $sectionid)
                    ->first();

                if ($section) {
                    $sectionname = $section->sectionname;
                }

            } else {
                $estud = db::table('sh_enrolledstud')
                    ->select('levelid', 'sectionid', 'studstatus')
                    ->where('studid', $stud->id)
                    ->where('deleted', 0)
                    ->first();

                if ($estud) {
                    $levelid = $estud->levelid;
                    $sectionid = $estud->sectionid;

                    $section = db::table('sections')
                        ->where('id', $sectionid)
                        ->first();

                    if ($section) {
                        $sectionname = $section->sectionname;
                    }

                } else {
                    $estud = db::table('college_enrolledstud')
                        ->select('yearLevel as levelid', 'courseid', 'studstatus')
                        ->where('studid', $stud->id)
                        ->where('deleted', 0)
                        ->first();

                    if ($estud) {
                        $levelid = $estud->levelid;
                        $courseid = $estud->courseid;

                        $courses = db::table('college_courses')
                            ->where('id', $courseid)
                            ->first();

                        if ($courses) {
                            $courseabrv = $courses->courseabrv;
                        }
                    } else {
                        $estud = db::table('studinfo')
                            ->select('levelid', 'studstatus')
                            ->where('id', $stud->id)
                            ->first();

                        if ($estud) {
                            $levelid = $estud->levelid;
                            $secionid = 0;
                        }
                    }
                }
            }


            if ($levelid == 14 || $levelid == 15) {
                $ledger = db::table('studledger')
                    ->selectRaw('studledger.*, chrngsetup.groupname')
                    ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                    ->where('studledger.studid', $stud->id)
                    ->where('studledger.syid', $syid)
                    ->where('studledger.amount', '>', 0)
                    ->where(function ($q) use ($semid) {
                        if (DB::table('schoolinfo')->first()->shssetup == 0) {
                            $q->where('semid', $semid);
                        }
                    })
                    ->where('studledger.void', 0)
                    ->where('studledger.deleted', 0)
                    ->groupBy('studledger.id')
                    ->orderBy('studledger.createddatetime', 'asc')
                    ->get();
            } elseif ($levelid >= 17 && $levelid <= 25) {
                $ledger = db::table('studledger')
                    ->selectRaw('studledger.*, chrngsetup.groupname')
                    ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                    ->where('studledger.studid', $stud->id)
                    ->where('studledger.syid', $syid)
                    ->where('studledger.semid', $semid)
                    ->where('studledger.classid', '!=', null)
                    ->where('studledger.amount', '>', 0)
                    ->where('studledger.deleted', 0)
                    ->where('studledger.void', 0)
                    ->groupBy('studledger.id')
                    ->orderBy('studledger.createddatetime', 'asc')
                    ->get();
            } else {
                $ledger = db::table('studledger')
                    ->selectRaw('studledger.*, chrngsetup.groupname')
                    ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                    ->where('studledger.studid', $stud->id)
                    ->where('studledger.syid', $syid)
                    ->where('studledger.deleted', 0)
                    ->where('studledger.void', 0)
                    ->where('studledger.amount', '>', 0)
                    ->groupBy('studledger.id')
                    ->orderBy('studledger.createddatetime', 'asc')
                    ->get();
            }


            $bal = 0;
            $debit = 0;
            $credit = 0;


            if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'xai') {

                foreach ($ledger as $led) {
                    $debit += $led->amount;

                    if ($led->void == 0) {
                        $credit += $led->payment;
                    }

                    $lDate = date_create($led->createddatetime);
                    $lDate = date_format($lDate, 'm-d-Y');

                    if ($led->amount > 0) {
                        $amount = number_format($led->amount, 2);
                    } else {
                        $amount = '';
                    }

                    if ($led->payment > 0) {
                        $payment = number_format($led->payment, 2);
                    } else {
                        $payment = '';
                    }

                    if ($led->void == 0) {
                        $bal += $led->amount - $led->payment;
                    }


                }

                if ($studinfo->levelid == 14 || $studinfo->levelid == 15) {
                    $getPaySched = db::table('studpayscheddetail')
                        ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
                        ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
                        ->where('studid', $studid)
                        ->where('syid', $syid)
                        ->where(function ($q) use ($semid) {
                            if (db::table('schoolinfo')->first()->shssetup == 0) {
                                $q->where('semid', $semid);
                            }
                        })
                        ->where('studpayscheddetail.deleted', 0)

                        ->groupBy(db::raw('MONTH(duedate)'))
                        ->get();
                    // return $getPaySched;

                } else if ($studinfo->levelid >= 17 && $studinfo->levelid <= 20 || $studinfo->levelid >= 22 && $studinfo->levelid <= 25) {

                    $getPaySched = db::table('studpayscheddetail')
                        ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
                        ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
                        ->where('studid', $studid)
                        ->where('syid', $syid)
                        ->where('semid', $semid)
                        ->where('studpayscheddetail.deleted', 0)
                        ->groupBy(db::raw('MONTH(duedate)'))
                        ->get();
                    // $getPaySched = db::table('studpayscheddetail')
                    // ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
                    //     ->join('itemclassification','studpayscheddetail.classid','=','itemclassification.id')
                    //     ->where('studid', $studid)
                    //     ->where('syid', $syid)
                    //     ->where('semid', $semid)
                    //     ->where('deleted', 0)
                    //     ->groupBy(db::raw('MONTH(duedate)'))
                    //     ->get();
                } else {
                    // $getPaySched = db::select('description, sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate
                    //     from studpayscheddetail
                    //     INNER JOIN itemclassification
                    //     ON studpayscheddetail.`classid` = itemclassification.id
                    //     where studid = ? and syid = ? and studpayscheddetail.deleted = 0
                    //     group by MONTH(duedate)
                    //     order by duedate', [$studid, $syid]);

                    $getPaySched = db::table('studpayscheddetail')
                        ->select(db::raw('description1, sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate'))
                        ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
                        ->where('studid', $studid)
                        ->where('syid', $syid)
                        ->groupBy(db::raw('month(duedate)'))
                        ->orderBy('duedate');
                }
                $assessbilling = 0;
                $assesspayment = 0;
                $assessbalance = 0;
                $totalBal = collect($getPaySched)->sum('balance');
                ;

                if (count($getPaySched) > 0) {
                    foreach ($getPaySched as $psched) {

                        // return $getPaySched;
                        // $totalBal += $psched->balance;
                        $assessbilling += $psched->amountdue;
                        $assesspayment += $psched->amountpay;
                        $assessbalance += $psched->balance;

                        $m = date_create($psched->duedate);
                        $f = date_format($m, 'F');
                        $m = date_format($m, 'm');

                        if ($psched->duedate != '') {
                            $particulars = 'PAYABLES FOR ' . strtoupper($f);
                        } else {
                            if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'faai') {
                                $particulars = 'REGISTRATION/MISCELLANEOUS/BOOKS/GENYO';
                            } else {
                                $particulars = 'ONE-TIME PAYMENT';
                            }
                            $m = 0;
                        }
                    }


                    $monthname = date('M', strtotime('2020-' . $month));
                    return view('finance.statementofaccount.table_xai')
                        ->with('studinfo', $studinfo)
                        ->with('monthname', $monthname)
                        ->with('ledger', $ledger)
                        ->with('getPaySched', $getPaySched);



                } else {

                    $monthname = date('M', strtotime('2020-' . $month));
                    return view('finance.statementofaccount.table_xai')
                        ->with('studinfo', $studinfo)
                        ->with('monthname', $monthname)
                        ->with('ledger', $ledger)
                        ->with('getPaySched', $getPaySched);
                }

            } else {

                $output = '<table class="table table-bordered" style="font-size: 12px;">
                    <thead>
                        <tr>
                            <th colspan="5">LEDGER</th>
                        </tr>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Billing</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>';

                foreach ($ledger as $led) {
                    $debit += $led->amount;

                    if ($led->void == 0) {
                        $credit += $led->payment;
                    }

                    $lDate = date_create($led->createddatetime);
                    $lDate = date_format($lDate, 'm-d-Y');

                    if ($led->amount > 0) {
                        $amount = number_format($led->amount, 2);
                    } else {
                        $amount = '';
                    }

                    if ($led->payment > 0) {
                        $payment = number_format($led->payment, 2);
                    } else {
                        $payment = '';
                    }

                    if ($led->void == 0) {
                        $bal += $led->amount - $led->payment;
                    }

                    if ($led->void == 0) {
                        $output .= '
                        <tr>
                            <td>' . $lDate . ' </td>
                            <td>' . $led->particulars . '</td>
                            <td class="text-right">' . $amount . '</td>
                            <td class="text-right">' . number_format($bal, 2) . '</td>
                        </tr>
                        ';
                    } else {
                        $output .= '
                        <tr>
                            <td class="text-danger"><del>' . $lDate . ' </del></td>
                            <td class="text-danger"><del>' . $led->particulars . '</del></td>
                            <td class="text-right text-danger"><del>' . $amount . '</del></td>
                            <td class="text-right text-danger"><del>' . number_format($bal, 2) . '</del></td>
                        </tr>
                        ';
                    }

                }

                $output .= '
                <tr style="background-color:#007bff91">
                    <th></th>
                    <th style="text-align:right">
                        <strong>TOTAL:<strong>
                    </th>
                    <th class="text-right">
                        <strong><u>' . number_format($debit, 2) . '</u></strong>
                    </th>
                    <th class="text-right">
                        <strong><u>' . number_format($bal, 2) . '</u></strong>
                    </th>
                </tr>
                </tbody>
                <thead>
                    <tr>
                        <th colspan="5">PAYMENT</th>
                    </tr>
                </thead>
                <tbody>';
                $output .= '
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Payment</th>
                        <th>Balance</th>
                    </tr>
                ';

                // $assessment = FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $_monthid);
                $a_totalbill = 0;
                $a_totalpay = 0;
                $a_totalbalance = 0;
                $paybal = $bal;

                $payments = db::table('studledger')
                    ->select('studledger.*', 'chrngsetup.groupname')
                    ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                    ->where('studledger.studid', $stud->id)
                    ->where('studledger.syid', $syid)
                    ->where(function ($q) use ($semid, $levelid) {
                        if ($levelid == 14 || $levelid == 15) {
                            if ($semid == 3) {
                                $q->where('semid', 3);
                            } else {
                                if (DB::table('schoolinfo')->first()->shssetup == 0) {
                                    $q->where('semid', $semid);
                                } else {
                                    $q->where('semid', '!=', 3);
                                }
                            }
                        } elseif ($levelid >= 17 && $levelid <= 25) {
                            $q->where('semid', $semid);
                        }
                    })
                    ->where('payment', '>', 0)
                    ->where('studledger.deleted', 0)
                    ->where('studledger.void', 0)
                    ->orderBy('studledger.createddatetime', 'asc')
                    ->get();

                foreach ($payments as $pay) {
                    // $_particulars = strtoupper($sched->particulars . ' PAYABLES');
                    $paybal -= $pay->payment;
                    $a_totalpay += $pay->payment;
                    $a_totalbalance += $pay->payment;

                    $lDate = date_create($pay->createddatetime);
                    $lDate = date_format($lDate, 'm-d-Y');

                    $output .= '
                        <tr>
                            <td>' . $lDate . ' </td>
                            <td>' . $pay->particulars . '</td>
                            <td class="text-right">' . number_format($pay->payment, 2) . '</td>
                            <td class="text-right">' . number_format($paybal, 2) . '</td>
                        </tr>
                    ';
                }


                $output .= '
                    <tr style="background-color:#007bff91">
                        <th></th>
                        <th style="text-align:right">
                            <strong>TOTAL:<strong>
                        </th>
                        <th class="text-right">
                            <strong><u>' . number_format($a_totalpay, 2) . '</u></strong>
                        </th>
                        <th class="text-right">
                            <strong><u>' . number_format($paybal, 2) . '</u></strong>
                        </th>
                    </tr>
                    </tbody>
                ';
                //DUE FOR THE MONTH//
                $monthinword = '';
                $monthsetup = db::table('monthsetup')->where('id', $monthsetupid)->first();

                if ($monthsetup) {
                    $monthinword = $monthsetup->description;
                } else {
                    $monthinword = '';
                }

                $request->request->add(['studid' => $stud->id]);
                $monthdue = UtilityController::assessment_gen($request);
                // dd($monthdue);

                // $monthdue =FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $monthsetup);
                $totaldue = 0;
                // return $monthdue;
                if ($monthdue) {
                    foreach ($monthdue as $_due) {
                        // return $_due->amount;
                        $totaldue += str_replace(',', '', $_due->amount);
                    }
                }

                $output .= '
                    <thead>
                        <tr style="background-color:#007bff91">
                            <th colspan="3" class="text-right text-lg">DUE FOR THE MONTH OF ' . strtoupper($monthinword) . ' </th>
                            <th class="text-right text-lg">' . number_format($totaldue, 2) . '</th>
                        </tr>
                    </thead>
                ';

            }

            // Calculate total debit, credit, and balance
            // $totalDebit = 0;
            // $totalCredit = 0;
            // $totalBalance = 0;

            // foreach ($ledger as $led) {
            //     $totalDebit += $led->amount;
            //     $totalCredit += $led->payment;
            //     $totalBalance += ($led->amount - $led->payment);
            // }

            // // // Attach ledger entries and compute total due for each student
            // foreach ($studinfo as $student) {
            //     $student->ledgerQuery = $ledger->get($student->id, collect());

            //     $studentTotal = 0;
            //     foreach ($student->ledgerQuery as $entry) {
            //         $studentTotal += ($entry->amount - $entry->payment);
            //     }
            //     $student->amountdue = number_format($studentTotal, 2);
            // }

            $ledgercollect = collect($studledger);
            $ledger_old = $ledgercollect->where('classid', $balclassid)->where('amount', '>', 0);
            $ledger_old->all();

            $stud->ledgerold = $ledgerold;
            $stud->ledger = $ledger;
            $stud->payments = $payments;

        }

        if ($action == 'generate') {
            return $output;
        } elseif ($action == 'ep') {
            return $totaldue;
        } else {
            $pdf = new MYPDFStatementOfAccount(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            // $pdf->SetCreator('CK');
            // $pdf->SetAuthor('CK Children\'s Publishing');
            // $pdf->SetTitle(DB::table('schoolinfo')->first()->schoolname.' - Statement of Account');
            // $pdf->SetSubject('Statement of Account');

            // set header and footer fonts
            // $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            // $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

            // // set default monospaced font
            // $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

            // set margins
            $pdf->SetMargins(3, 10, 5);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

            $pdf->setPrintFooter(false);


            // $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 4, 'color' => array(255, 0, 0)));
            // set auto page breaks
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

            // set image scale factor
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

            // set some language-dependent strings (optional)
            if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
                require_once(dirname(__FILE__) . '/lang/eng.php');
                $pdf->setLanguageArray($l);
            }

            // if(strtolower($schoolinfo->abbreviation) == 'apmc')
            // {
            //     $pdf->setPrintHeader(false);
            // }


            // ---------------------------------------------------------

            // set font
            // $pdf->SetFont('dejavusans', '', 10);


            // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
            // Print a table

            $pdf->AddPage('8.5x11');

            $schoolInfo = DB::table('schoolinfo')->first();
            $schoolname = $schoolInfo->schoolname;
            $schooladdress = $schoolInfo->address;
            $picurl = explode('?', $schoolInfo->picurl)[0];
            $sy = DB::table('sy')->where('id', $syid ?? '')->first();
            $sem = DB::table('semester')->where('id', $semid)->first();
            $sydesc = $sy ? $sy->sydesc : 'Unknown School Year';
            $semdesc = $sem ? $sem->semester : 'Unknown Semester';
            $cursy = $sydesc . ' - ' . $semdesc;

            // dd($studinfo);
            // $pdf = PDF::loadView('finance/reports/pdf/pdf_statementofacct_default_v2_all', compact('ledger', 'picurl', 'payments', 'monthsetup', 'monthdue', 'selectedschoolyear', 'selectedsemester', 'monthinword', 'studinfo', 'levelid', 'courseabrv', 'sectionname',
            //  'schoolname',  'studinfo', 'schoolname', 'schooladdress', 'picurl', 'cursy', 'month', 'schoolInfo'))
            //     ->setPaper('letter');
            // return $pdf->stream('Statement Of Account.pdf');
            //    Generate PDF
            $pdf = PDF::loadView('finance.reports.pdf.pdf_printallstatementofacct', compact('studinfo', 'schoolname', 'schooladdress', 'picurl', 'cursy', 'month', 'schoolInfo'));

            // dd($studinfo);
            return $pdf->stream('Statement Of Account.pdf');
        }
    }

    public function printallSOA(Request $request)
    {
        $syid = $request->get('selectedschoolyear');
        $semid = $request->get('selectedsemester');
        $levelid = $request->get('levelid');
        $courseid = $request->get('courseid');
        $month = $request->get('selectedmonth');

        // Get school year information
        $sy = DB::table('sy')->where('id', $syid)->first();
        $sydesc = $sy ? $sy->sydesc : 'Unknown School Year';

        // Get semester information
        $sem = DB::table('semester')->where('id', $semid)->first();
        $semdesc = $sem ? $sem->semester : 'Unknown Semester';
        $cursy = $sydesc . ' - ' . $semdesc;

        // Get school information
        $schoolInfo = DB::table('schoolinfo')->first();
        $schoolname = $schoolInfo ? $schoolInfo->schoolname : 'Unknown School';
        $schooladdress = $schoolInfo ? $schoolInfo->address : 'Unknown Address';
        $picurl = $schoolInfo ? explode('?', $schoolInfo->picurl)[0] : '';

        // Get students based on level and course/section
        if ($levelid >= 17 && $levelid <= 25) {
            // College students - filter by courseid
            $students = DB::table('studinfo')
                ->where('levelid', $levelid)
                ->when($courseid != 0, function ($query) use ($courseid) {
                    return $query->where('courseid', $courseid);
                })
                ->get();
        } else {
            // Non-college students - filter by sectionid
            $students = DB::table('studinfo')
                ->where('levelid', $levelid)
                ->when($courseid != 0, function ($query) use ($courseid) {
                    return $query->where('sectionid', $courseid);
                })
                ->get();
        }

        // Get all student IDs for the ledger query
        $studentIds = $students->pluck('id')->toArray();

        // Get ledger entries for all students
        $ledgerQuery = DB::table('studledger')
            ->selectRaw('studledger.*, chrngsetup.groupname, studinfo.sid, studinfo.lastname, studinfo.firstname, studinfo.middlename, studinfo.suffix, studledger.studid')
            ->join('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
            ->join('studinfo', 'studledger.studid', '=', 'studinfo.id')
            ->where('studledger.syid', $syid)
            ->where('studledger.deleted', 0)
            ->where('studledger.void', 0)
            ->where('studledger.amount', '>', 0);

        // Apply semester filter for SHS
        if ($levelid == 14 || $levelid == 15) {
            if ($schoolInfo && $schoolInfo->shssetup == 0) {
                $ledgerQuery->where('studledger.semid', $semid);
            }
        } elseif ($levelid >= 17 && $levelid <= 25) {
            $ledgerQuery->where('studledger.semid', $semid);
        }

        // Optional: Apply month filter (uncomment if needed)
        // if ($month) {
        //     $ledgerQuery->whereMonth('studledger.createddatetime', $month);
        // }

        $ledgerQuery->groupBy('studledger.id');

        $ledger = $ledgerQuery->get();

        // Group ledger entries by student ID
        $ledgerByStudent = $ledger->groupBy('studid');

        // Calculate total debit, credit, and balance
        $totalDebit = 0;
        $totalCredit = 0;
        $totalBalance = 0;

        foreach ($ledger as $led) {
            $totalDebit += $led->amount;
            $totalCredit += $led->payment;
            $totalBalance += ($led->amount - $led->payment);
        }

        // Attach ledger entries and compute total due for each student
        foreach ($students as $student) {
            $student->ledgerQuery = $ledgerByStudent->get($student->id, collect());

            $studentTotal = 0;
            foreach ($student->ledgerQuery as $entry) {
                $studentTotal += ($entry->amount - $entry->payment);
            }
            $student->amountdue = number_format($studentTotal, 2);
        }

        // Generate PDF
        $pdf = PDF::loadView('finance.reports.pdf.pdf_printallstatementofacct', compact(
            'students',
            'schoolname',
            'schooladdress',
            'picurl',
            'cursy',
            'month',
            'schoolInfo',
            'totalDebit',
            'totalCredit',
            'totalBalance'
        ));

        return $pdf->stream('Statement Of Account.pdf');
    }







    // public function getaccount_v2(Request $request)
    // {
    //     // if ($request->get('all') == 1) {
    //     //     return $this->processAllStudents($request);
    //     // }

    //     $studid = $request->get('studid');
    //     $semid = $request->get('selectedsemester');
    //     $syid = $request->get('selectedschoolyear');
    //     $monthsetupid = $request->get('selectedmonth');
    //     $action = $request->get('action');
    //     $month = null;
    //     $levelid = 0;
    //     $sectionid = 0;
    //     $courseid = 0;
    //     $sectionname = '';
    //     $courseabrv = '';



    //     if ($request->get('selectedmonth') > 0) {
    //         if (date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month']) {
    //             if (strlen(date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month']) == 1) {
    //                 $month = '0' . date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month'];
    //             } else {
    //                 $month = date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month'];
    //             }
    //         }
    //     }

    //     $request['syid'] = $syid;
    //     $request['semid'] = $semid;
    //     $request['monthid'] = $month;

    //     $selectedschoolyear = DB::table('sy')
    //         ->where('id', $syid)
    //         ->first()
    //         ->sydesc;

    //     $selectedsemester = db::table('semester')
    //         ->where('id', $semid)
    //         ->first()
    //         ->semester;

    //     $studinfo = db::table('studinfo')
    //         ->select('id', 'sid', 'lastname', 'firstname', 'middlename', 'suffix')
    //         ->where('studinfo.id', $studid)
    //         ->first();

    //     $estud = db::table('enrolledstud')
    //         ->select('levelid', 'sectionid', 'studstatus')
    //         ->where('studid', $studid)
    //         ->where('syid', $syid)
    //         ->where('deleted', 0)
    //         ->first();

    //     if ($estud) {
    //         $levelid = $estud->levelid;
    //         $sectionid = $estud->sectionid;

    //         $section = db::table('sections')
    //             ->where('id', $sectionid)
    //             ->first();

    //         if ($section) {
    //             $sectionname = $section->sectionname;
    //         }

    //     } else {
    //         $estud = db::table('sh_enrolledstud')
    //             ->select('levelid', 'sectionid', 'studstatus')
    //             ->where('studid', $studid)
    //             ->where('deleted', 0)
    //             ->first();

    //         if ($estud) {
    //             $levelid = $estud->levelid;
    //             $sectionid = $estud->sectionid;

    //             $section = db::table('sections')
    //                 ->where('id', $sectionid)
    //                 ->first();

    //             if ($section) {
    //                 $sectionname = $section->sectionname;
    //             }

    //         } else {
    //             $estud = db::table('college_enrolledstud')
    //                 ->select('yearLevel as levelid', 'courseid', 'studstatus')
    //                 ->where('studid', $studid)
    //                 ->where('deleted', 0)
    //                 ->first();

    //             if ($estud) {
    //                 $levelid = $estud->levelid;
    //                 $courseid = $estud->courseid;

    //                 $courses = db::table('college_courses')
    //                     ->where('id', $courseid)
    //                     ->first();

    //                 if ($courses) {
    //                     $courseabrv = $courses->courseabrv;
    //                 }
    //             } else {
    //                 $estud = db::table('studinfo')
    //                     ->select('levelid', 'studstatus')
    //                     ->where('id', $studid)
    //                     ->first();

    //                 if ($estud) {
    //                     $levelid = $estud->levelid;
    //                     $secionid = 0;
    //                 }
    //             }
    //         }
    //     }


    //     if ($levelid == 14 || $levelid == 15) {
    //         $ledger = db::table('studledger')
    //             ->selectRaw('studledger.*, chrngsetup.groupname')
    //             ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where('studledger.amount', '>', 0)
    //             ->where(function ($q) use ($semid) {
    //                 if (DB::table('schoolinfo')->first()->shssetup == 0) {
    //                     $q->where('semid', $semid);
    //                 }
    //             })
    //             ->where('studledger.void', 0)
    //             ->where('studledger.deleted', 0)
    //             ->groupBy('studledger.id')
    //             ->orderBy('studledger.createddatetime', 'asc')
    //             ->get();
    //     } elseif ($levelid >= 17 && $levelid <= 25) {
    //         $ledger = db::table('studledger')
    //             ->selectRaw('studledger.*, chrngsetup.groupname')
    //             ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where('studledger.semid', $semid)
    //             ->where('studledger.classid', '!=', null)
    //             ->where('studledger.amount', '>', 0)
    //             ->where('studledger.deleted', 0)
    //             ->where('studledger.void', 0)
    //             ->groupBy('studledger.id')
    //             ->orderBy('studledger.createddatetime', 'asc')
    //             ->get();
    //     } else {
    //         $ledger = db::table('studledger')
    //             ->selectRaw('studledger.*, chrngsetup.groupname')
    //             ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where('studledger.deleted', 0)
    //             ->where('studledger.void', 0)
    //             ->where('studledger.amount', '>', 0)
    //             ->groupBy('studledger.id')
    //             ->orderBy('studledger.createddatetime', 'asc')
    //             ->get();
    //     }


    //     $bal = 0;
    //     $debit = 0;
    //     $credit = 0;


    //     if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'xai') {

    //         foreach ($ledger as $led) {
    //             $debit += $led->amount;

    //             if ($led->void == 0) {
    //                 $credit += $led->payment;
    //             }

    //             $lDate = date_create($led->createddatetime);
    //             $lDate = date_format($lDate, 'm-d-Y');

    //             if ($led->amount > 0) {
    //                 $amount = number_format($led->amount, 2);
    //             } else {
    //                 $amount = '';
    //             }

    //             if ($led->payment > 0) {
    //                 $payment = number_format($led->payment, 2);
    //             } else {
    //                 $payment = '';
    //             }

    //             if ($led->void == 0) {
    //                 $bal += $led->amount - $led->payment;
    //             }

    //         }
    //         if ($studinfo->levelid == 14 || $studinfo->levelid == 15) {
    //             $getPaySched = db::table('studpayscheddetail')
    //                 ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
    //                 ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
    //                 ->where('studid', $studid)
    //                 ->where('syid', $syid)
    //                 ->where(function ($q) use ($semid) {
    //                     if (db::table('schoolinfo')->first()->shssetup == 0) {
    //                         $q->where('semid', $semid);
    //                     }
    //                 })
    //                 ->where('studpayscheddetail.deleted', 0)

    //                 ->groupBy(db::raw('MONTH(duedate)'))
    //                 ->get();
    //             // return $getPaySched;

    //         } else if ($studinfo->levelid >= 17 && $studinfo->levelid <= 20 || $studinfo->levelid >= 22 && $studinfo->levelid <= 25) {

    //             $getPaySched = db::table('studpayscheddetail')
    //                 ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
    //                 ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
    //                 ->where('studid', $studid)
    //                 ->where('syid', $syid)
    //                 ->where('semid', $semid)
    //                 ->where('studpayscheddetail.deleted', 0)
    //                 ->groupBy(db::raw('MONTH(duedate)'))
    //                 ->get();
    //             // $getPaySched = db::table('studpayscheddetail')
    //             // ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
    //             //     ->join('itemclassification','studpayscheddetail.classid','=','itemclassification.id')
    //             //     ->where('studid', $studid)
    //             //     ->where('syid', $syid)
    //             //     ->where('semid', $semid)
    //             //     ->where('deleted', 0)
    //             //     ->groupBy(db::raw('MONTH(duedate)'))
    //             //     ->get();
    //         } else {
    //             // $getPaySched = db::select('description, sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate
    //             //     from studpayscheddetail
    //             //     INNER JOIN itemclassification
    //             //     ON studpayscheddetail.`classid` = itemclassification.id
    //             //     where studid = ? and syid = ? and studpayscheddetail.deleted = 0
    //             //     group by MONTH(duedate)
    //             //     order by duedate', [$studid, $syid]);

    //             $getPaySched = db::table('studpayscheddetail')
    //                 ->select(db::raw('description1, sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate'))
    //                 ->join('itemclassification', 'studpayscheddetail.classid', '=', 'itemclassification.id')
    //                 ->where('studid', $studid)
    //                 ->where('syid', $syid)
    //                 ->groupBy(db::raw('month(duedate)'))
    //                 ->orderBy('duedate');
    //         }
    //         $assessbilling = 0;
    //         $assesspayment = 0;
    //         $assessbalance = 0;
    //         $totalBal = collect($getPaySched)->sum('balance');
    //         ;

    //         if (count($getPaySched) > 0) {
    //             foreach ($getPaySched as $psched) {

    //                 // return $getPaySched;
    //                 // $totalBal += $psched->balance;
    //                 $assessbilling += $psched->amountdue;
    //                 $assesspayment += $psched->amountpay;
    //                 $assessbalance += $psched->balance;

    //                 $m = date_create($psched->duedate);
    //                 $f = date_format($m, 'F');
    //                 $m = date_format($m, 'm');

    //                 if ($psched->duedate != '') {
    //                     $particulars = 'PAYABLES FOR ' . strtoupper($f);
    //                 } else {
    //                     if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'faai') {
    //                         $particulars = 'REGISTRATION/MISCELLANEOUS/BOOKS/GENYO';
    //                     } else {
    //                         $particulars = 'ONE-TIME PAYMENT';
    //                     }
    //                     $m = 0;
    //                 }
    //             }


    //             $monthname = date('M', strtotime('2020-' . $month));
    //             return view('finance.statementofaccount.table_xai')
    //                 ->with('studinfo', $studinfo)
    //                 ->with('monthname', $monthname)
    //                 ->with('ledger', $ledger)
    //                 ->with('getPaySched', $getPaySched);



    //         } else {

    //             $monthname = date('M', strtotime('2020-' . $month));
    //             return view('finance.statementofaccount.table_xai')
    //                 ->with('studinfo', $studinfo)
    //                 ->with('monthname', $monthname)
    //                 ->with('ledger', $ledger)
    //                 ->with('getPaySched', $getPaySched);
    //         }

    //     } else {

    //         $output = '<table class="table table-bordered" style="font-size: 12px;">
    //             <thead>
    //                 <tr>
    //                     <th colspan="5">LEDGER</th>
    //                 </tr>
    //                 <tr>
    //                     <th>Date</th>
    //                     <th>Description</th>
    //                     <th>Billing</th>
    //                     <th>Balance</th>
    //                 </tr>
    //             </thead>
    //             <tbody>';

    //         foreach ($ledger as $led) {
    //             $debit += $led->amount;

    //             if ($led->void == 0) {
    //                 $credit += $led->payment;
    //             }

    //             $lDate = date_create($led->createddatetime);
    //             $lDate = date_format($lDate, 'm-d-Y');

    //             if ($led->amount > 0) {
    //                 $amount = number_format($led->amount, 2);
    //             } else {
    //                 $amount = '';
    //             }

    //             if ($led->payment > 0) {
    //                 $payment = number_format($led->payment, 2);
    //             } else {
    //                 $payment = '';
    //             }

    //             if ($led->void == 0) {
    //                 $bal += $led->amount - $led->payment;
    //             }

    //             if ($led->void == 0) {
    //                 $output .= '
    //                 <tr>
    //                     <td>' . $lDate . ' </td>
    //                     <td>' . $led->particulars . '</td>
    //                     <td class="text-right">' . $amount . '</td>
    //                     <td class="text-right">' . number_format($bal, 2) . '</td>
    //                 </tr>
    //                 ';
    //             } else {
    //                 $output .= '
    //                 <tr>
    //                     <td class="text-danger"><del>' . $lDate . ' </del></td>
    //                     <td class="text-danger"><del>' . $led->particulars . '</del></td>
    //                     <td class="text-right text-danger"><del>' . $amount . '</del></td>
    //                     <td class="text-right text-danger"><del>' . number_format($bal, 2) . '</del></td>
    //                 </tr>
    //                 ';
    //             }

    //         }

    //         $output .= '
    //         <tr style="background-color:#007bff91">
    //             <th></th>
    //             <th style="text-align:right">
    //                 <strong>TOTAL:<strong>
    //             </th>
    //             <th class="text-right">
    //                 <strong><u>' . number_format($debit, 2) . '</u></strong>
    //             </th>
    //             <th class="text-right">
    //                 <strong><u>' . number_format($bal, 2) . '</u></strong>
    //             </th>
    //         </tr>
    //         </tbody>
    //         <thead>
    //             <tr>
    //                 <th colspan="5">PAYMENT</th>
    //             </tr>
    //         </thead>
    //         <tbody>';
    //         $output .= '
    //             <tr>
    //                 <th>Date</th>
    //                 <th>Description</th>
    //                 <th>Payment</th>
    //                 <th>Balance</th>
    //             </tr>
    //         ';

    //         // $assessment = FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $_monthid);
    //         $a_totalbill = 0;
    //         $a_totalpay = 0;
    //         $a_totalbalance = 0;
    //         $paybal = $bal;

    //         $payments = db::table('studledger')
    //             ->select('studledger.*', 'chrngsetup.groupname')
    //             ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
    //             ->where('studledger.studid', $studid)
    //             ->where('studledger.syid', $syid)
    //             ->where(function ($q) use ($semid, $levelid) {
    //                 if ($levelid == 14 || $levelid == 15) {
    //                     if ($semid == 3) {
    //                         $q->where('semid', 3);
    //                     } else {
    //                         if (DB::table('schoolinfo')->first()->shssetup == 0) {
    //                             $q->where('semid', $semid);
    //                         } else {
    //                             $q->where('semid', '!=', 3);
    //                         }
    //                     }
    //                 } elseif ($levelid >= 17 && $levelid <= 25) {
    //                     $q->where('semid', $semid);
    //                 }
    //             })
    //             ->where('payment', '>', 0)
    //             ->where('studledger.deleted', 0)
    //             ->where('studledger.void', 0)
    //             ->orderBy('studledger.createddatetime', 'asc')
    //             ->get();

    //         foreach ($payments as $pay) {
    //             // $_particulars = strtoupper($sched->particulars . ' PAYABLES');
    //             $paybal -= $pay->payment;
    //             $a_totalpay += $pay->payment;
    //             $a_totalbalance += $pay->payment;

    //             $lDate = date_create($pay->createddatetime);
    //             $lDate = date_format($lDate, 'm-d-Y');

    //             $output .= '
    //                 <tr>
    //                     <td>' . $lDate . ' </td>
    //                     <td>' . $pay->particulars . '</td>
    //                     <td class="text-right">' . number_format($pay->payment, 2) . '</td>
    //                     <td class="text-right">' . number_format($paybal, 2) . '</td>
    //                 </tr>
    //             ';
    //         }


    //         $output .= '
    //             <tr style="background-color:#007bff91">
    //                 <th></th>
    //                 <th style="text-align:right">
    //                     <strong>TOTAL:<strong>
    //                 </th>
    //                 <th class="text-right">
    //                     <strong><u>' . number_format($a_totalpay, 2) . '</u></strong>
    //                 </th>
    //                 <th class="text-right">
    //                     <strong><u>' . number_format($paybal, 2) . '</u></strong>
    //                 </th>
    //             </tr>
    //             </tbody>
    //         ';
    //         //DUE FOR THE MONTH//
    //         $monthinword = '';
    //         $monthsetup = db::table('monthsetup')->where('id', $monthsetupid)->first();

    //         if ($monthsetup) {
    //             $monthinword = $monthsetup->description;
    //         } else {
    //             $monthinword = '';
    //         }


    //         $monthdue = UtilityController::assessment_gen($request);

    //         // $monthdue =FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $monthsetup);
    //         $totaldue = 0;
    //         // return $monthdue;
    //         if ($monthdue) {
    //             foreach ($monthdue as $_due) {
    //                 // return $_due->amount;
    //                 $totaldue += str_replace(',', '', $_due->amount);
    //             }
    //         }

    //         $output .= '
    //             <thead>
    //                 <tr style="background-color:#007bff91">
    //                     <th colspan="3" class="text-right text-lg">DUE FOR THE MONTH OF ' . strtoupper($monthinword) . ' </th>
    //                     <th class="text-right text-lg">' . number_format($totaldue, 2) . '</th>
    //                 </tr>
    //             </thead>
    //         ';








    //     }

    //     if ($action == 'generate') {
    //         return $output;
    //     } elseif ($action == 'ep') {
    //         return $totaldue;
    //     } else {
    //         $pdf = new MYPDFStatementOfAccount(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    //         // $pdf->SetCreator('CK');
    //         // $pdf->SetAuthor('CK Children\'s Publishing');
    //         // $pdf->SetTitle(DB::table('schoolinfo')->first()->schoolname.' - Statement of Account');
    //         // $pdf->SetSubject('Statement of Account');

    //         // set header and footer fonts
    //         // $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    //         // $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    //         // // set default monospaced font
    //         // $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    //         // set margins
    //         $pdf->SetMargins(3, 10, 5);
    //         $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    //         $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    //         $pdf->setPrintFooter(false);


    //         // $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 4, 'color' => array(255, 0, 0)));
    //         // set auto page breaks
    //         $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    //         // set image scale factor
    //         $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    //         // set some language-dependent strings (optional)
    //         if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
    //             require_once(dirname(__FILE__) . '/lang/eng.php');
    //             $pdf->setLanguageArray($l);
    //         }

    //         // if(strtolower($schoolinfo->abbreviation) == 'apmc')
    //         // {
    //         //     $pdf->setPrintHeader(false);
    //         // }


    //         // ---------------------------------------------------------

    //         // set font
    //         // $pdf->SetFont('dejavusans', '', 10);


    //         // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
    //         // Print a table

    //         $pdf->AddPage('8.5x11');



    //         $pdf = PDF::loadView('finance/reports/pdf/pdf_statementofacct_default_v2', compact('ledger', 'payments', 'monthsetup', 'monthdue', 'selectedschoolyear', 'selectedsemester', 'monthinword', 'studinfo', 'levelid', 'courseabrv', 'sectionname'))
    //             ->setPaper('letter');
    //         return $pdf->stream('Statement Of Account.pdf');
    //     }
    // }

    // protected function processAllStudents(Request $request)
    // {
    //     $semid = $request->get('selectedsemester');
    //     $syid = $request->get('selectedschoolyear');
    //     $monthsetupid = $request->get('selectedmonth');

    //     // Get all active students based on filters (reuse your existing query logic)
    //     $students = DB::table('enrolledstud')
    //         ->select('studinfo.id', 'studinfo.sid', 'studinfo.lastname', 'studinfo.firstname')
    //         ->join('studinfo', 'enrolledstud.studid', '=', 'studinfo.id')
    //         ->where('enrolledstud.syid', $syid)
    //         ->where('enrolledstud.deleted', 0)
    //         ->when($semid, function($query) use ($semid) {
    //             if (DB::table('schoolinfo')->first()->shssetup == 0) {
    //                 $query->where('enrolledstud.semid', $semid);
    //             }
    //         })
    //         ->orderBy('studinfo.lastname')
    //         ->get();

    //     // Create PDF with all students
    //     $pdf = new MYPDFStatementOfAccount(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    //     $pdf->SetMargins(3, 10, 5);
    //     $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    //     $pdf->setPrintFooter(false);

    //     foreach ($students as $student) {
    //         // Reuse your existing PDF generation logic per student
    //         $pdf->AddPage();

    //         // Get student data (reuse your existing query logic)
    //         $studinfo = DB::table('studinfo')->where('id', $student->id)->first();
    //         $ledger = DB::table('studledger')->where('studid', $student->id)->get();
    //         // ... other queries

    //         // Reuse your existing view
    //         $html = view('finance.statementofaccount.table_xai', [
    //             'studinfo' => $studinfo,
    //             'ledger' => $ledger,
    //             // ... other variables
    //         ])->render();

    //         $pdf->writeHTML($html, true, false, true, false, '');

    //         // Add page break if not last student
    //         if (!$students->last() == $student) {
    //             $pdf->AddPage();
    //         }
    //     }

    //     return $pdf->stream('All_Statements_of_Account.pdf');
    // }

    public function export(Request $request)
    {
        $studid = $request->get('studid');
        $semid = $request->get('selectedsemester');
        $syid = $request->get('selectedschoolyear');
        $strand = '';
        $selectedschoolyear = DB::table('sy')
            ->where('id', $request->get('selectedschoolyear'))
            ->first()
            ->sydesc;
        $monthsetupid = $request->get('selectedmonth');
        $selectedmonth = 0;
        if ($request->get('selectedmonth') > 0) {
            if (date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month']) {
                if (strlen(date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month']) == 1) {
                    $selectedmonth = '0' . date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month'];
                } else {
                    $selectedmonth = date_parse(DB::table('monthsetup')->where('id', $monthsetupid)->first()->description)['month'];
                }
            }
        }

        if ($request->get('selectedschoolyear') == null) {
            $selectedsemester = "";
        } else {
            $semester = DB::table('semester')
                ->where('id', $request->get('selectedsemester'))
                ->first()
                ->semester;

            $selectedsemester = $semester;
        }

        $studinfo = db::table('studinfo')
            ->select('studinfo.id', 'studinfo.sid', 'lastname', 'firstname', 'middlename', 'suffix', 'gender', 'dob', 'street', 'barangay', 'city', 'province', 'levelname', 'sectionname', 'levelid', 'courseid', 'feesid', 'studtype', 'sectionname', 'courseabrv')
            ->join('gradelevel', 'studinfo.levelid', '=', 'gradelevel.id')
            ->leftJoin('college_courses', 'studinfo.courseid', '=', 'college_courses.id')
            ->where('studinfo.id', $studid)
            ->first();

        $checksection = DB::table('enrolledstud')
            ->select('sectionname', 'levelname')
            ->join('sections', 'enrolledstud.sectionid', '=', 'sections.id')
            ->join('gradelevel', 'enrolledstud.levelid', '=', 'gradelevel.id')
            ->where('enrolledstud.studid', $studinfo->id)
            ->where('enrolledstud.syid', $syid)
            ->where('enrolledstud.deleted', '0')
            ->first();

        if ($checksection) {
            $studinfo->sectionname = $checksection->sectionname;
            $studinfo->levelname = $checksection->levelname;
        } else {
            $checksection = DB::table('sh_enrolledstud')
                ->select('sectionname', 'levelname', 'strandcode')
                ->join('sections', 'sh_enrolledstud.sectionid', '=', 'sections.id')
                ->join('gradelevel', 'sh_enrolledstud.levelid', '=', 'gradelevel.id')
                ->join('sh_strand', 'sh_enrolledstud.strandid', '=', 'sh_strand.id')
                ->where('sh_enrolledstud.studid', $studinfo->id)
                ->where('sh_enrolledstud.syid', $syid)
                ->where('sh_enrolledstud.deleted', '0')
                ->first();
            if ($checksection) {
                $studinfo->sectionname = $checksection->sectionname;
                $studinfo->levelname = $checksection->levelname;
                $strand = $checksection->strandcode;
            } else {
                $checksection = DB::table('college_enrolledstud')
                    ->select('sectionDesc as sectionname', 'levelname')
                    ->join('college_sections', 'college_enrolledstud.sectionid', '=', 'college_sections.id')
                    ->join('gradelevel', 'college_enrolledstud.yearLevel', '=', 'gradelevel.id')
                    ->where('college_enrolledstud.studid', $studinfo->id)
                    ->where('college_enrolledstud.syid', $syid)
                    ->where('college_enrolledstud.deleted', '0')
                    ->first();

                if ($checksection) {
                    $studinfo->sectionname = $checksection->sectionname;
                    $studinfo->levelname = $checksection->levelname;
                }
            }

        }

        $studaddress = '';

        if ($studinfo->dob != null) {
            $studinfo->dob = date('d-F-y', strtotime($studinfo->dob));
        }
        if ($studinfo->street != null) {
            $studaddress .= $studinfo->street . ', ';
        }
        if ($studinfo->barangay != null) {
            $studaddress .= $studinfo->barangay . ', ';
        }
        if ($studinfo->city != null) {
            $studaddress .= $studinfo->city . ', ';
        }
        if ($studinfo->province != null) {
            $studaddress .= $studinfo->province . ', ';
        }

        if ($studaddress != '') {
            $studaddress = substr($studaddress, 0, -2);
        }

        $studinfo->address = $studaddress;

        $notes = DB::table('schoolreportsnote')
            ->where('deleted', '0')
            ->where('type', '1')
            ->get();

        $notestatus = 0;
        if (count($notes) > 0) {
            foreach ($notes as $note) {
                if ($note->status) {
                    $notestatus += 1;
                }
            }
        }
        if ($studinfo->middlename == null) {
            $studinfo->middlename = '';
        } else {
            $studinfo->middlename = $studinfo->middlename[0] . '.';
        }

        $preparedby = DB::table('teacher')
            ->where('userid', auth()->user()->id)
            ->first();

        if ($request->get('selectedmonth') == null || $request->get('selectedmonth') == 0) {
            $month = 0;
        } else {
            $month = date('m', strtotime($request->get('selectedmonth')));
        }


        if ($studinfo->levelid == 14 || $studinfo->levelid == 15) {
            $ledger = db::table('studledger')
                ->select('studledger.*', 'chrngsetup.groupname')
                ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                ->where('studledger.studid', $studid)
                ->where('studledger.syid', $syid)
                ->where(function ($q) use ($semid) {
                    if (DB::table('schoolinfo')->first()->shssetup == 0) {
                        $q->where('semid', $semid);
                    }
                })
                ->where('studledger.void', 0)
                ->where('studledger.deleted', 0)
                ->orderBy('studledger.id', 'asc')
                ->get();
        } elseif ($studinfo->levelid >= 17 && $studinfo->levelid <= 25) {
            $ledger = db::table('studledger')
                ->select('studledger.*', 'chrngsetup.groupname')
                ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                ->where('studledger.studid', $studid)
                ->where('studledger.syid', $syid)
                ->where('studledger.semid', $semid)
                ->where('studledger.deleted', 0)
                ->orderBy('studledger.id', 'asc')
                ->get();
        } else {
            $ledger = db::table('studledger')
                ->select('studledger.*', 'chrngsetup.groupname')
                ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                ->where('studledger.studid', $studid)
                ->where('studledger.syid', $syid)
                ->where('studledger.deleted', 0)
                ->orderBy('studledger.id', 'asc')
                ->get();
        }
        // return collect($ledger)->sum('payment');



        $schoolinfo = Db::table('schoolinfo')
            ->select(
                'schoolinfo.schoolid',
                'schoolinfo.abbreviation',
                'schoolinfo.schoolname',
                'schoolinfo.authorized',
                'schoolinfo.picurl',
                'refcitymun.citymunDesc as division',
                'schoolinfo.district',
                'schoolinfo.address',
                'refregion.regDesc as region'
            )
            ->leftJoin('refregion', 'schoolinfo.region', '=', 'refregion.regCode')
            ->leftJoin('refcitymun', 'schoolinfo.division', '=', 'refcitymun.citymunCode')
            ->first();
        // return $studinfo->id;
        $itemized = DB::table('studledgeritemized')
            ->select('itemamount', 'items.description', 'classid')
            ->join('items', 'studledgeritemized.itemid', '=', 'items.id')
            ->where('studid', $studinfo->id)
            ->where('studledgeritemized.syid', $syid)
            ->where('studledgeritemized.semid', $semid)
            ->where('studledgeritemized.deleted', 0)
            ->where('classid', '!=', 7)
            ->get();
        // $itemized = DB::select('itemamount, items.`description`, classid FROM studledgeritemized
        // INNER JOIN items ON  studledgeritemized.itemid = items.`id`
        // WHERE studid = '.$studinfo->id.'
        // AND studledgeritemized.syid = '.$syid.'
        // AND studledgeritemized.semid = '.$semid.'
        // AND studledgeritemized.deleted = 0 and classid != 7');
        // return collect($itemized)->sum('itemamount');

        if ($request->get('exporttype') == 'pdf') {

            $bal = 0;
            $debit = 0;
            $credit = 0;

            // if($studinfo->levelid == 14 || $studinfo->levelid == 15)
            // {

            //     $getPaySched = db::table('studpayscheddetail')
            //         ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
            //         ->join('itemclassification','studpayscheddetail.classid','=','itemclassification.id')
            //         ->where('studid', $studid)
            //         ->where('syid', $syid)
            //         ->where(function($q) use($semid){
            //             if(db::table('schoolinfo')->first()->shssetup == 0)
            //             {
            //                 $q->where('semid', $semid);
            //             }
            //         })
            //         ->where('studpayscheddetail.deleted', 0)
            //         ->groupBy(db::raw('MONTH(duedate)'))
            //         ->get();
            //         // return $getPaySched;

            // }
            // else if($studinfo->levelid >= 17 && $studinfo->levelid <= 20)
            // {

            //     $getPaySched = db::table('studpayscheddetail')
            //     ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
            //         ->join('itemclassification','studpayscheddetail.classid','=','itemclassification.id')
            //         ->where('studid', $studid)
            //         ->where('syid', $syid)
            //         ->where('semid', $semid)
            //         ->where('studpayscheddetail.deleted', 0)
            //         ->groupBy(db::raw('MONTH(duedate)'))
            //         ->get();
            // }
            // else
            // {

            //     $getPaySched = db::table('studpayscheddetail')
            //     ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate, itemclassification.description'))
            //         ->join('itemclassification','studpayscheddetail.classid','=','itemclassification.id')
            //         ->where('studid', $studid)
            //         ->where('syid', $syid)
            //         ->where('studpayscheddetail.deleted', 0)
            //         ->groupBy(db::raw('MONTH(duedate)'))
            //         ->get();
            // }
            // $monthsetup = DB::table('monthsetup')
            //     ->get();

            // if(count($getPaySched)>0)
            // {
            //     foreach($getPaySched as $eachpaysched)
            //     {
            //         if($eachpaysched->duedate == null)
            //         {
            //             $eachpaysched->monthid = 0;
            //             $eachpaysched->monthnumber = 0;
            //         }else{
            //             if(collect($monthsetup)->where('description', strtoupper(strtolower(date('F', strtotime($eachpaysched->duedate)))))->count()>0)
            //             {
            //                 $eachpaysched->monthid = collect($monthsetup)->where('description', strtoupper(strtolower(date('F', strtotime($eachpaysched->duedate)))))->first()->id;
            //             }else{
            //                 $eachpaysched->monthid = 0;
            //             }
            //             if(strlen(date_parse(date('F', strtotime($eachpaysched->duedate)))['month']) == 1)
            //             {
            //                 $eachpaysched->monthnumber = '0'.date_parse(date('F', strtotime($eachpaysched->duedate)))['month'];
            //             }else{
            //                 $eachpaysched->monthnumber = date_parse(date('F', strtotime($eachpaysched->duedate)))['month'];
            //             }
            //         }
            //     }
            // }
            // $getPaySched = collect($getPaySched)->sortBy('monthid')->sortBy('monthnum')->values();
            $_monthid = db::table('monthsetup')->where('id', $monthsetupid)->first()->monthid;

            $getPaySched = FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $_monthid);
            // return $getPaySched;
            $assessbilling = 0;
            $assesspayment = 0;
            $assessbalance = 0;
            $totalBal = 0;

            $units = 0;
            // $totalunits = db::table('college_studsched')
            // ->select(db::raw('SUM(lecunits) + SUM(labunits) AS totalunits'))
            // ->join('college_classsched', 'college_studsched.schedid', '=', 'college_classsched.id')
            // ->join('college_prospectus', 'college_classsched.subjectID', '=', 'college_prospectus.id')
            // ->where('college_studsched.studid', $studinfo->id)
            // ->where('college_studsched.deleted', 0)
            // ->where('college_classsched.deleted', 0)
            // ->where('college_classsched.syID', $syid)
            // ->where('college_classsched.semesterID', $semid)
            // ->first();
            // return collect($totalunits);
            try {
                $courseid = DB::table('college_enrolledstud')
                    ->where('college_enrolledstud.studid', $studinfo->id)
                    ->where('college_enrolledstud.syid', $syid)
                    ->where('college_enrolledstud.semid', $semid)
                    ->where('college_enrolledstud.yearLevel', $studinfo->levelid)
                    ->whereIn('college_enrolledstud.studstatus', [1, 2, 4])
                    ->select('courseid')
                    ->first()->courseid;
            } catch (\Exception $e) {
                $courseid = $studinfo->courseid;
            }

            $units = db::table('college_studsched')
                ->select(db::raw('SUM(lecunits) + SUM(labunits) AS totalunits'))
                ->join('college_classsched', 'college_studsched.schedid', '=', 'college_classsched.id')
                ->join('college_prospectus', 'college_classsched.subjectID', '=', 'college_prospectus.id')
                ->where('college_studsched.studid', $studid)
                ->where('college_studsched.deleted', 0)
                ->where('college_classsched.deleted', 0)
                ->where('college_classsched.syID', $syid)
                ->where('college_classsched.semesterID', $semid)
                ->first()->totalunits;

            $unitprice = 0;

            if ($studinfo->feesid != null) {
                $tuitionheader = DB::table('tuitionheader')
                    ->where('id', $studinfo->feesid)
                    ->where('syid', $syid)
                    ->where('semid', $semid)
                    ->where('levelid', $studinfo->levelid)
                    ->where('deleted', '0')
                    ->first();

                if ($tuitionheader) {
                    $tuitiondetail = DB::table('tuitiondetail')
                        ->select('tuitiondetail.amount')
                        ->join('chrngsetup', 'tuitiondetail.classificationid', '=', 'chrngsetup.classid')
                        ->where('tuitiondetail.headerid', $studinfo->feesid)
                        ->where('chrngsetup.groupname', 'TUI')
                        ->where('tuitiondetail.deleted', '0')
                        ->where('chrngsetup.deleted', '0')
                        ->first();

                    if ($tuitiondetail) {
                        $unitprice = $tuitiondetail->amount;
                    }
                    // return $tuitiondetail;

                }
            }
            $courseandyear = '';
            $course = DB::table('college_courses')
                ->where('id', $courseid)
                ->first();

            if ($course) {
                $courseandyear .= $course->courseabrv . ' - ';
            }
            $levelname = DB::table('gradelevel')
                ->where('id', $studinfo->levelid)
                ->first();
            if ($levelname) {
                $courseandyear .= filter_var($levelname->levelname, FILTER_SANITIZE_NUMBER_INT);
            }

            if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'xai') {
                $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            } elseif (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'apmc') {
                $monthname = date('M', strtotime(date('Y') . '-' . $selectedmonth));

                // if($studinfo->levelid > 16)
                // {
                $pdf = PDF::loadView('finance/reports/pdf/pdf_statementofacct_apmc_college', compact('selectedschoolyear', 'selectedmonth', 'selectedsemester', 'studinfo', 'ledger', 'lDate', 'getPaySched', 'month', 'notestatus', 'notes', 'preparedby', 'schoolinfo', 'monthname', 'units', 'unitprice', 'itemized', 'courseandyear'))->setPaper('legal');
                return $pdf->stream('Statement Of Account.pdf');
                // }else{
                //     $pdf = PDF::loadView('finance/reports/pdf/pdf_statementofacct_apmc', compact('selectedschoolyear','selectedmonth','selectedsemester','studinfo','ledger','lDate','getPaySched','month','notestatus','notes','preparedby','schoolinfo','monthname','units','unitprice','courseandyear'))->setPaper('legal');
                //     return $pdf->stream('dailycashcollection.pdf');
                // }
                // $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            } elseif (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'ccsa') {

                $pdf = new MYPDFStatementOfAccountccsa(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            } elseif (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'xkhs') {
                $monthname = date('M', strtotime(date('Y') . '-' . $selectedmonth));

                // if($studinfo->levelid > 16)
                // {
                $pdf = PDF::loadView('finance/reports/pdf/pdf_statementofacct_xdkhs', compact('selectedschoolyear', 'selectedmonth', 'selectedsemester', 'studinfo', 'ledger', 'lDate', 'getPaySched', 'month', 'notestatus', 'notes', 'preparedby', 'schoolinfo', 'monthname', 'units', 'unitprice', 'itemized', 'courseandyear'))->setPaper('legal');
                return $pdf->stream('Statement Of Account.pdf');
                // }else{
                //     $pdf = PDF::loadView('finance/reports/pdf/pdf_statementofacct_apmc', compact('selectedschoolyear','selectedmonth','selectedsemester','studinfo','ledger','lDate','getPaySched','month','notestatus','notes','preparedby','schoolinfo','monthname','units','unitprice','courseandyear'))->setPaper('legal');
                //     return $pdf->stream('dailycashcollection.pdf');
                // }
                // $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            } elseif (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'ndsc') {
                $monthname = '';
                if ($selectedmonth != '0') {
                    $monthname = date('F', strtotime(date('Y') . '-' . $selectedmonth));
                }
                $previousbalance = DB::table('balforwardsetup')
                    ->join('studledger', 'balforwardsetup.classid', '=', 'studledger.classid')
                    ->where('studledger.studid', $studid)
                    ->where('balforwardsetup.syid', $syid)
                    ->where('balforwardsetup.semid', $semid)
                    ->where('studledger.syid', $syid)
                    ->where('studledger.semid', $semid)
                    // ->where('balforwardsetup.deleted')
                    ->get();

                // return collect($studinfo);
                // if($studinfo->levelid > 16)
                // {
                // return $getPaySched;
                $pdf = PDF::loadView('finance/reports/pdf/pdf_statementofacct_default', compact('previousbalance', 'monthsetupid', 'monthsetup', 'selectedschoolyear', 'selectedmonth', 'selectedsemester', 'studinfo', 'ledger', 'lDate', 'getPaySched', 'month', 'notestatus', 'notes', 'preparedby', 'schoolinfo', 'monthname', 'units', 'unitprice', 'itemized', 'courseandyear'))->setPaper('legal');
                return $pdf->stream('Statement Of Account.pdf');
                // }else{
                //     $pdf = PDF::loadView('finance/reports/pdf/pdf_statementofacct_apmc', compact('selectedschoolyear','selectedmonth','selectedsemester','studinfo','ledger','lDate','getPaySched','month','notestatus','notes','preparedby','schoolinfo','monthname','units','unitprice','courseandyear'))->setPaper('legal');
                //     return $pdf->stream('dailycashcollection.pdf');
                // }
                // $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            }
            // elseif(strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'hccsi')
            // {
            //     $pdf = new MYPDFStatementOfAccountHccsi(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            // }
            else {
                $pdf = new MYPDFStatementOfAccount(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            }

            // set document information
            $pdf->SetCreator('CK');
            $pdf->SetAuthor('CK Children\'s Publishing');
            $pdf->SetTitle($schoolinfo->schoolname . ' - Statement of Account');
            $pdf->SetSubject('Statement of Account');

            // set header and footer fonts
            $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

            // set default monospaced font
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

            // set margins
            if (strtolower($schoolinfo->abbreviation) == 'xai') {
                $pdf->SetMargins(11, 5, 11);
                $pdf->SetPrintHeader(false);
                $pdf->SetPrintFooter(false);
            } elseif (strtolower($schoolinfo->abbreviation) == 'apmc') {
                $pdf->SetMargins(5, 2, 5);
                $pdf->SetPrintHeader(false);
                $pdf->SetPrintFooter(false);
            } elseif (strtolower($schoolinfo->abbreviation) == 'xkhs') {
                $pdf->SetMargins(5, 2, 5);
                $pdf->SetPrintHeader(false);
                $pdf->SetPrintFooter(false);
            } elseif (strtolower($schoolinfo->abbreviation) == 'ndsc') {
                $pdf->SetMargins(5, 2, 5);
                $pdf->SetPrintHeader(false);
                $pdf->SetPrintFooter(false);
            }
            // elseif(strtolower($schoolinfo->abbreviation) == 'hccsi')
            // {
            //     $pdf->SetMargins(3, 10, 5);
            // }
            else {
                $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
                $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
                $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
            }

            // $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 4, 'color' => array(255, 0, 0)));
            // set auto page breaks
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

            // set image scale factor
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

            // set some language-dependent strings (optional)
            if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
                require_once(dirname(__FILE__) . '/lang/eng.php');
                $pdf->setLanguageArray($l);
            }

            // if(strtolower($schoolinfo->abbreviation) == 'apmc')
            // {
            //     $pdf->setPrintHeader(false);
            // }
            // ---------------------------------------------------------

            // set font


            // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
            // Print a table

            // add a page
            if (strtolower($schoolinfo->abbreviation) == 'xai') {
                $pdf->AddPage('P', 'A4');
            } else {
                $pdf->AddPage();
            }


            $month = $selectedmonth;
            if ($selectedmonth != 0) {
                $selectedmonth = date('F', strtotime(date('Y') . '-' . $selectedmonth));
                $monthname = date('F', strtotime(date('Y') . '-' . $selectedmonth));
            }
            if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'xai') {
                // return $ledger;
                $view = \View::make('finance/reports/pdf/pdf_statementofacct_xai', compact('selectedschoolyear', 'selectedmonth', 'selectedsemester', 'studinfo', 'ledger', 'lDate', 'getPaySched', 'month', 'notestatus', 'notes', 'preparedby', 'schoolinfo', 'monthname'));
            } elseif (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'apmc') {

                if ($studinfo->levelid > 16) {
                    $view = \View::make('finance/reports/pdf/pdf_statementofacct_apmc_college', compact('selectedschoolyear', 'selectedmonth', 'selectedsemester', 'studinfo', 'ledger', 'lDate', 'getPaySched', 'month', 'notestatus', 'notes', 'preparedby', 'schoolinfo', 'monthname', 'units', 'unitprice'));
                } else {
                    $view = \View::make('finance/reports/pdf/pdf_statementofacct_apmc', compact('selectedschoolyear', 'selectedmonth', 'selectedsemester', 'studinfo', 'ledger', 'lDate', 'getPaySched', 'month', 'notestatus', 'notes', 'preparedby', 'schoolinfo', 'monthname', 'units', 'unitprice'));
                }
            } elseif (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'xkhs') {

                // if($studinfo->levelid > 16)
                // {
                //     $view = \View::make('finance/reports/pdf/pdf_statementofacct_apmc_college', compact('selectedschoolyear','selectedmonth','selectedsemester','studinfo','ledger','lDate','getPaySched','month','notestatus','notes','preparedby','schoolinfo','monthname','units','unitprice'));
                // }else{
                $view = \View::make('finance/reports/pdf/pdf_statementofacct_apmc', compact('selectedschoolyear', 'selectedmonth', 'selectedsemester', 'studinfo', 'ledger', 'lDate', 'getPaySched', 'month', 'notestatus', 'notes', 'preparedby', 'schoolinfo', 'monthname', 'units', 'unitprice'));
                // }
            } elseif (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'ndsc') {

                // if($studinfo->levelid > 16)
                // {
                //     $view = \View::make('finance/reports/pdf/pdf_statementofacct_apmc_college', compact('selectedschoolyear','selectedmonth','selectedsemester','studinfo','ledger','lDate','getPaySched','month','notestatus','notes','preparedby','schoolinfo','monthname','units','unitprice'));
                // }else{
                $view = \View::make('finance/reports/pdf/pdf_statementofacct_default', compact('selectedschoolyear', 'selectedmonth', 'selectedsemester', 'studinfo', 'ledger', 'lDate', 'getPaySched', 'month', 'notestatus', 'notes', 'preparedby', 'schoolinfo', 'monthname', 'units', 'unitprice'));
                // }
            } elseif (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'ccsa') {

                // if($studinfo->levelid > 16)
                // {
                //     $view = \View::make('finance/reports/pdf/pdf_statementofacct_apmc_college', compact('selectedschoolyear','selectedmonth','selectedsemester','studinfo','ledger','lDate','getPaySched','month','notestatus','notes','preparedby','schoolinfo','monthname','units','unitprice'));
                // }else{
                $view = \View::make('finance/reports/pdf/pdf_statementofacct_ccsa_college', compact('selectedschoolyear', 'selectedmonth', 'selectedsemester', 'studinfo', 'ledger', 'lDate', 'getPaySched', 'month', 'notestatus', 'notes', 'preparedby', 'schoolinfo', 'monthname', 'units', 'unitprice'));
                // }
            }
            // elseif(strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'hccsi')
            // {
            //     $monthname = date('M', strtotime(date('Y').'-'.$selectedmonth));
            //     // return $ledger;
            //     $view = \View::make('finance/reports/pdf/pdf_statementofacct_hccsi', compact('selectedschoolyear','selectedmonth','selectedsemester','studinfo','ledger','lDate','getPaySched','month','notestatus','notes','preparedby','schoolinfo','monthname'));
            // }
            else {
                // return $getPaySched;
                $view = \View::make('finance/reports/pdf/pdf_statementofacct_default', compact('selectedschoolyear', 'selectedmonth', 'selectedsemester', 'studinfo', 'ledger', 'getPaySched', 'month', 'notestatus', 'notes', 'preparedby', 'monthsetupid', 'monthsetupid', 'strand'));
            }


            $html = $view->render();
            $pdf->writeHTML($html, true, false, true, false, '');
            // ---------------------------------------------------------
            //Close and output PDF document
            $pdf->Output('Statement of Account.pdf', 'I');
        } elseif ($request->get('exporttype') == 'excel') {
            $studinfo = db::table('studinfo')
                ->select('studinfo.id', 'lastname', 'firstname', 'middlename', 'suffix', 'gender', 'dob', 'street', 'barangay', 'city', 'province', 'studtype', 'levelname', 'sectionname', 'levelid', 'courseid', 'feesid')
                ->join('gradelevel', 'studinfo.levelid', '=', 'gradelevel.id')
                ->where('studinfo.id', $studid)
                ->first();

            if ($studinfo->levelid >= 17 && strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'apmc') {
                $units = 0;

                try {
                    $courseid = DB::table('college_enrolledstud')
                        ->where('college_enrolledstud.studid', $studinfo->id)
                        ->where('college_enrolledstud.syid', $syid)
                        ->where('college_enrolledstud.semid', $semid)
                        ->where('college_enrolledstud.yearLevel', $studinfo->levelid)
                        ->whereIn('college_enrolledstud.studstatus', [1, 2, 4])
                        ->select('courseid')
                        ->first()->courseid;
                } catch (\Exception $e) {
                    $courseid = $studinfo->courseid;
                }

                $getsubjects = DB::table('college_studsched')
                    ->join('college_classsched', 'college_studsched.schedid', '=', 'college_classsched.id')
                    ->where('college_studsched.studid', $studinfo->id)
                    ->where('college_studsched.deleted', '0')
                    ->where('college_classsched.syID', $syid)
                    ->where('college_classsched.semesterID', $semid)
                    ->where('college_classsched.deleted', '0')
                    ->get();

                if (count($getsubjects) > 0) {
                    foreach ($getsubjects as $subject) {
                        $allunits = DB::table('college_prospectus')
                            ->select('lecunits', 'labunits')
                            // ->where('courseID', $courseid->courseid)
                            ->where('courseID', $courseid)
                            ->where('id', $subject->subjectID)
                            ->where('semesterID', $semid)
                            ->where('yearID', $studinfo->levelid)
                            ->where('deleted', '0')
                            ->get();

                        // return $allunits;
                        if (count($allunits) > 0) {
                            foreach ($allunits as $unit) {
                                $units += ($unit->lecunits + $unit->labunits);
                            }
                        }
                    }
                }

                // return $units;

                $unitprice = 0;

                if ($studinfo->feesid != null) {
                    $tuitionheader = DB::table('tuitionheader')
                        ->where('id', $studinfo->feesid)
                        ->where('syid', $syid)
                        ->where('semid', $semid)
                        ->where('levelid', $studinfo->levelid)
                        ->where('deleted', '0')
                        ->first();

                    if ($tuitionheader) {
                        $tuitiondetail = DB::table('tuitiondetail')
                            ->select('tuitiondetail.amount')
                            ->join('chrngsetup', 'tuitiondetail.classificationid', '=', 'chrngsetup.classid')
                            ->where('tuitiondetail.headerid', $studinfo->feesid)
                            ->where('chrngsetup.groupname', 'TUI')
                            ->where('tuitiondetail.deleted', '0')
                            ->where('chrngsetup.deleted', '0')
                            ->first();

                        if ($tuitionheader) {
                            $unitprice = $tuitiondetail->amount;
                        }
                        // return $tuitiondetail;

                    }
                }

                // return $unitprice;
                $headerstyle = array(
                    'font' => array(
                        'bold' => true,
                        'color' => array('rgb' => '25751d'),
                        'size' => 20,
                        'name' => 'Verdana'
                    )
                );

                $greentext = array(
                    'font' => array(
                        'color' => array('rgb' => '25751d'),
                        'size' => 8,
                        'name' => 'Verdana'
                    )
                );

                $greentext2 = array(
                    'font' => array(
                        'color' => array('rgb' => '25751d'),
                        'size' => 7,
                        'name' => 'Verdana'
                    )
                );

                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(base_path() . '/public/excelformats/dcc_statementofaccount.xlsx');
                $sheet = $spreadsheet->getActiveSheet();

                try {
                    $coursename = DB::table('college_enrolledstud')
                        ->where('studid', $studinfo->id)
                        ->where('yearLevel', $studinfo->levelid)
                        ->join('college_courses', 'college_enrolledstud.courseid', '=', 'college_courses.id')
                        ->first()->courseabrv;
                } catch (\Exception $e) {
                    $coursename = DB::table('college_courses')
                        ->where('id', $courseid)
                        ->first()->courseabrv;
                }

                if ($studinfo->middlename != null) {

                    $studinfo->middlename == $studinfo->middlename[0] . '.';

                }
                $sheet->setCellValue('A13', $selectedsemester . ' for School Year ' . $selectedschoolyear);
                $sheet->setCellValue('C19', $studinfo->lastname . ',' . $studinfo->firstname . ', ' . $studinfo->middlename . ', ' . $coursename . ' - ' . $studinfo->levelname);

                $startcellno = 21;

                $bal = 0;
                $debit = 0;
                $credit = 0;
                // return $ledger;
                foreach ($ledger as $led) {
                    $debit += $led->amount;

                    if ($led->void == 0) {
                        $credit += $led->payment;
                    }

                    $lDate = date_create($led->createddatetime);
                    $lDate = date_format($lDate, 'm-d-Y');

                    if ($led->amount > 0) {
                        $amount = $led->amount;
                    } else {
                        $amount = null;
                    }

                    if ($led->payment > 0) {
                        $payment = $led->payment;
                    } else {
                        $payment = null;
                    }

                    if ($led->void == 0) {
                        $bal += $led->amount - $led->payment;
                    }
                    if ($amount > 0) {
                        if ($led->void == 0) {
                            $sheet->setCellValue('C' . $startcellno, $led->particulars);
                            if (strpos(strtolower($led->particulars), 'tuition') !== false) {
                                $sheet->setCellValue('E' . $startcellno, '(');
                                $sheet->setCellValue('F' . $startcellno, $units);
                                $sheet->setCellValue('G' . $startcellno, 'Units x');
                                $sheet->setCellValue('H' . $startcellno, $unitprice);
                                $sheet->setCellValue('I' . $startcellno, ')');
                                $sheet->setCellValue('J' . $startcellno, $amount);
                            } else {
                                $sheet->setCellValue('J' . $startcellno, $amount);
                            }
                        } else {
                            $sheet->setCellValue('C' . $startcellno, $led->particulars);
                            if (strpos(strtolower($led->particulars), 'tuition') !== false) {
                                $sheet->setCellValue('E' . $startcellno, '(');
                                $sheet->setCellValue('F' . $startcellno, $units);
                                $sheet->setCellValue('G' . $startcellno, 'Units x');
                                $sheet->setCellValue('H' . $startcellno, $unitprice);
                                $sheet->setCellValue('I' . $startcellno, ')');
                                $sheet->setCellValue('J' . $startcellno, $amount);
                            } else {
                                $sheet->setCellValue('J' . $startcellno, $amount);
                            }

                        }

                        $sheet->getStyle('J' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');

                        $startcellno += 1;
                        $sheet->insertNewRowBefore($startcellno, 1);
                    }

                }

                $startcellno += 1;

                $sheet->insertNewRowBefore($startcellno, 2);
                $sheet->setCellValue('C' . $startcellno, 'TOTAL ACCOUNTS PAYABLE');
                $sheet->getStyle('C' . $startcellno)->getFont()->setBold(true);
                $startcellno += 1;
                $sheet->setCellValue('I' . $startcellno, 'P');
                $sheet->setCellValue('J' . $startcellno, "=SUM(J21:J" . $startcellno . ")");
                $sheet->getStyle('J' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('J' . $startcellno)->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('J' . $startcellno)->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('I' . $startcellno . ':J' . $startcellno)->getFont()->setBold(true);
                $startcellno += 2;

                $sheet->insertNewRowBefore($startcellno, 1);


                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment; filename="Statement Of Account.xlsx"');
                $writer->save("php://output");
            } elseif ($studinfo->levelid >= 17 && DB::table('schoolinfo')->first()->abbreviation == 'dcc') {
                $units = 0;

                $courseid = DB::table('college_enrolledstud')
                    ->where('college_enrolledstud.studid', $studinfo->id)
                    ->where('college_enrolledstud.syid', $syid)
                    ->where('college_enrolledstud.semid', $semid)
                    ->where('college_enrolledstud.yearLevel', $studinfo->levelid)
                    ->whereIn('college_enrolledstud.studstatus', [1, 2, 4])
                    ->select('courseid')
                    ->first();

                $getsubjects = DB::table('college_studsched')
                    ->join('college_classsched', 'college_studsched.schedid', '=', 'college_classsched.id')
                    ->where('college_studsched.studid', $studinfo->id)
                    ->where('college_studsched.deleted', '0')
                    ->where('college_classsched.syID', $syid)
                    ->where('college_classsched.semesterID', $semid)
                    ->where('college_classsched.deleted', '0')
                    ->get();

                if (count($getsubjects) > 0) {
                    foreach ($getsubjects as $subject) {
                        $allunits = DB::table('college_prospectus')
                            ->select('lecunits', 'labunits')
                            ->where('courseID', $courseid->courseid)
                            ->where('id', $subject->subjectID)
                            ->where('semesterID', $semid)
                            ->where('yearID', $studinfo->levelid)
                            ->where('deleted', '0')
                            ->get();

                        // return $allunits;
                        if (count($allunits) > 0) {
                            foreach ($allunits as $unit) {
                                $units += ($unit->lecunits + $unit->labunits);
                            }
                        }
                    }
                }

                // return $units;

                $unitprice = 0;

                if ($studinfo->feesid != null) {
                    $tuitionheader = DB::table('tuitionheader')
                        ->where('id', $studinfo->feesid)
                        ->where('syid', $syid)
                        ->where('semid', $semid)
                        ->where('levelid', $studinfo->levelid)
                        ->where('deleted', '0')
                        ->first();

                    if ($tuitionheader) {
                        $tuitiondetail = DB::table('tuitiondetail')
                            ->select('tuitiondetail.amount')
                            ->join('chrngsetup', 'tuitiondetail.classificationid', '=', 'chrngsetup.classid')
                            ->where('tuitiondetail.headerid', $studinfo->feesid)
                            ->where('chrngsetup.groupname', 'TUI')
                            ->where('tuitiondetail.deleted', '0')
                            ->where('chrngsetup.deleted', '0')
                            ->first();

                        if ($tuitionheader) {
                            $unitprice = $tuitiondetail->amount;
                        }
                        // return $tuitiondetail;

                    }
                }

                // return $unitprice;
                $headerstyle = array(
                    'font' => array(
                        'bold' => true,
                        'color' => array('rgb' => '25751d'),
                        'size' => 20,
                        'name' => 'Verdana'
                    )
                );

                $greentext = array(
                    'font' => array(
                        'color' => array('rgb' => '25751d'),
                        'size' => 8,
                        'name' => 'Verdana'
                    )
                );

                $greentext2 = array(
                    'font' => array(
                        'color' => array('rgb' => '25751d'),
                        'size' => 7,
                        'name' => 'Verdana'
                    )
                );

                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(base_path() . '/public/excelformats/dcc_statementofaccount.xlsx');
                $sheet = $spreadsheet->getActiveSheet();

                $coursename = DB::table('college_enrolledstud')
                    ->where('studid', $studinfo->id)
                    ->where('yearLevel', $studinfo->levelid)
                    ->join('college_courses', 'college_enrolledstud.courseid', '=', 'college_courses.id')
                    ->first()->courseabrv;

                if ($studinfo->middlename != null) {

                    $studinfo->middlename == $studinfo->middlename[0] . '.';

                }
                $sheet->setCellValue('A13', $selectedsemester . ' for School Year ' . $selectedschoolyear);
                $sheet->setCellValue('C19', $studinfo->lastname . ',' . $studinfo->firstname . ', ' . $studinfo->middlename . ', ' . $coursename . ' - ' . $studinfo->levelname);

                $startcellno = 21;

                $bal = 0;
                $debit = 0;
                $credit = 0;
                // return $ledger;
                foreach ($ledger as $led) {
                    $debit += $led->amount;

                    if ($led->void == 0) {
                        $credit += $led->payment;
                    }

                    $lDate = date_create($led->createddatetime);
                    $lDate = date_format($lDate, 'm-d-Y');

                    if ($led->amount > 0) {
                        $amount = $led->amount;
                    } else {
                        $amount = null;
                    }

                    if ($led->payment > 0) {
                        $payment = $led->payment;
                    } else {
                        $payment = null;
                    }

                    if ($led->void == 0) {
                        $bal += $led->amount - $led->payment;
                    }
                    if ($amount > 0) {
                        if ($led->void == 0) {
                            $sheet->setCellValue('C' . $startcellno, $led->particulars);
                            if (strpos(strtolower($led->particulars), 'tuition') !== false) {
                                $sheet->setCellValue('E' . $startcellno, '(');
                                $sheet->setCellValue('F' . $startcellno, $units);
                                $sheet->setCellValue('G' . $startcellno, 'Units x');
                                $sheet->setCellValue('H' . $startcellno, $unitprice);
                                $sheet->setCellValue('I' . $startcellno, ')');
                                $sheet->setCellValue('J' . $startcellno, $amount);
                            } else {
                                $sheet->setCellValue('J' . $startcellno, $amount);
                            }
                        } else {
                            $sheet->setCellValue('C' . $startcellno, $led->particulars);
                            if (strpos(strtolower($led->particulars), 'tuition') !== false) {
                                $sheet->setCellValue('E' . $startcellno, '(');
                                $sheet->setCellValue('F' . $startcellno, $units);
                                $sheet->setCellValue('G' . $startcellno, 'Units x');
                                $sheet->setCellValue('H' . $startcellno, $unitprice);
                                $sheet->setCellValue('I' . $startcellno, ')');
                                $sheet->setCellValue('J' . $startcellno, $amount);
                            } else {
                                $sheet->setCellValue('J' . $startcellno, $amount);
                            }

                        }

                        $sheet->getStyle('J' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');

                        $startcellno += 1;
                        $sheet->insertNewRowBefore($startcellno, 1);
                    }

                }

                $startcellno += 1;

                $sheet->insertNewRowBefore($startcellno, 2);
                $sheet->setCellValue('C' . $startcellno, 'TOTAL ACCOUNTS PAYABLE');
                $sheet->getStyle('C' . $startcellno)->getFont()->setBold(true);
                $startcellno += 1;
                $sheet->setCellValue('I' . $startcellno, 'P');
                $sheet->setCellValue('J' . $startcellno, "=SUM(J21:J" . $startcellno . ")");
                $sheet->getStyle('J' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('J' . $startcellno)->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('J' . $startcellno)->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('I' . $startcellno . ':J' . $startcellno)->getFont()->setBold(true);
                $startcellno += 2;

                $sheet->insertNewRowBefore($startcellno, 1);

                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment; filename="Statement Of Account.xlsx"');
                $writer->save("php://output");
            } else {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                ;
                $sheet = $spreadsheet->getActiveSheet();
                $borderstyle = array(
                    'borders' => array(
                        'outline' => array(
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => array('argb' => 'black'),
                        ),
                    ),
                );
                $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(5);

                $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                $drawing->setName('Logo');
                $drawing->setDescription('Logo');
                $drawing->setPath(base_path() . '/public/' . $schoolinfo->picurl);
                $drawing->setHeight(70);
                $drawing->setWorksheet($sheet);
                $drawing->setCoordinates('A1');
                $drawing->setOffsetX(0);
                $drawing->setOffsetY(20);

                $drawing->getShadow()->setVisible(true);
                $drawing->getShadow()->setDirection(45);

                $sheet->mergeCells('C2:G2');
                $sheet->setCellValue('C2', $schoolinfo->schoolname);
                $sheet->mergeCells('C3:G3');
                $sheet->setCellValue('C3', $schoolinfo->address);
                $sheet->mergeCells('C4:G4');
                $sheet->setCellValue('C4', 'Statement of Account');
                $sheet->mergeCells('C5:E5');
                $sheet->setCellValue('C5', 'S.Y ' . $selectedschoolyear);
                $sheet->mergeCells('C6:E6');
                $sheet->setCellValue('C6', $selectedsemester);

                if ($request->get('selectedmonth') != null) {
                    $sheet->mergeCells('F6:G6');
                    $sheet->setCellValue('F6', 'AS OF MONTH OF: ' . strtoupper($request->get('selectedmonth')));
                }

                foreach (array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H') as $columnID) {
                    $sheet->getColumnDimension($columnID)
                        ->setAutoSize(true);
                }
                if ($request->get('selectedmonth') == null) {
                    $month = null;
                } else {
                    $month = date('m', strtotime($request->get('selectedmonth')));
                }

                $sheet->mergeCells('A8:E8');
                $sheet->setCellValue('A8', 'STUDENT: ' . $studinfo->lastname . ',' . $studinfo->firstname . ' ' . $studinfo->middlename . ' ' . $studinfo->suffix);
                $sheet->getStyle('A8:E8')->getFont()->setBold(true);
                $sheet->mergeCells('A9:H9');
                $sheet->setCellValue('A9', 'LEDGER');
                $sheet->getStyle('A9:H9')->getFont()->setBold(true);
                $sheet->getStyle('A9:H9')->applyFromArray($borderstyle);

                $sheet->mergeCells('A10:B10');
                $sheet->setCellValue('A10', 'Date');
                $sheet->getStyle('A10:B10')->applyFromArray($borderstyle);
                $sheet->mergeCells('C10:E10');
                $sheet->setCellValue('C10', 'Description');
                $sheet->getStyle('C10:E10')->applyFromArray($borderstyle);
                $sheet->setCellValue('F10', 'Billing');
                $sheet->getStyle('F10')->applyFromArray($borderstyle);
                $sheet->setCellValue('G10', 'Payment');
                $sheet->getStyle('G10')->applyFromArray($borderstyle);
                $sheet->setCellValue('H10', 'Balance');
                $sheet->getStyle('H10')->applyFromArray($borderstyle);

                $startcellno = 11;

                $bal = 0;
                $debit = 0;
                $credit = 0;

                foreach ($ledger as $led) {
                    $debit += $led->amount;

                    if ($led->void == 0) {
                        $credit += $led->payment;
                    }

                    $lDate = date_create($led->createddatetime);
                    $lDate = date_format($lDate, 'm-d-Y');

                    if ($led->amount > 0) {
                        $amount = $led->amount;
                    } else {
                        $amount = null;
                    }

                    if ($led->payment > 0) {
                        $payment = $led->payment;
                    } else {
                        $payment = null;
                    }

                    if ($led->void == 0) {
                        $bal += $led->amount - $led->payment;
                    }

                    if ($led->void == 0) {
                        $sheet->mergeCells('A' . $startcellno . ':B' . $startcellno);
                        $sheet->setCellValue('A' . $startcellno, $lDate);
                        $sheet->getStyle('A' . $startcellno . ':B' . $startcellno)->applyFromArray($borderstyle);
                        $sheet->mergeCells('C' . $startcellno . ':E' . $startcellno);
                        $sheet->setCellValue('C' . $startcellno, $led->particulars);
                        $sheet->getStyle('C' . $startcellno . ':E' . $startcellno)->applyFromArray($borderstyle);
                        $sheet->setCellValue('F' . $startcellno, $amount);
                        $sheet->getStyle('F' . $startcellno)->applyFromArray($borderstyle);
                        $sheet->setCellValue('G' . $startcellno, $payment);
                        $sheet->getStyle('G' . $startcellno)->applyFromArray($borderstyle);
                        $sheet->setCellValue('H' . $startcellno, $bal);
                        $sheet->getStyle('H' . $startcellno)->applyFromArray($borderstyle);
                    } else {
                        $sheet->mergeCells('A' . $startcellno . ':B' . $startcellno);
                        $sheet->setCellValue('A' . $startcellno, $lDate);
                        $sheet->getStyle('A' . $startcellno . ':B' . $startcellno)->applyFromArray($borderstyle);
                        $sheet->mergeCells('C' . $startcellno . ':E' . $startcellno);
                        $sheet->setCellValue('C' . $startcellno, $led->particulars);
                        $sheet->getStyle('C' . $startcellno . ':E' . $startcellno)->applyFromArray($borderstyle);
                        $sheet->setCellValue('F' . $startcellno, $amount);
                        $sheet->getStyle('F' . $startcellno)->applyFromArray($borderstyle);
                        $sheet->setCellValue('G' . $startcellno, $payment);
                        $sheet->getStyle('G' . $startcellno)->applyFromArray($borderstyle);
                        $sheet->setCellValue('H' . $startcellno, $bal);
                        $sheet->getStyle('H' . $startcellno)->applyFromArray($borderstyle);
                        $strikethrough = $sheet->getStyle('A' . $startcellno . ':H' . $startcellno)->getFont()->getStrikethrough();
                        $sheet->getStyle('A' . $startcellno . ':H' . $startcellno)->setStrikethrough($strikethrough);

                    }
                    $sheet->getStyle('F' . $startcellno . ':H' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');

                    $startcellno += 1;

                }

                $sheet->mergeCells('A' . $startcellno . ':B' . $startcellno);
                $sheet->setCellValue('A' . $startcellno, '');
                $sheet->getStyle('A' . $startcellno . ':B' . $startcellno)->applyFromArray($borderstyle);
                $sheet->mergeCells('C' . $startcellno . ':E' . $startcellno);
                $sheet->setCellValue('C' . $startcellno, 'TOTAL');
                $sheet->getStyle('C' . $startcellno . ':E' . $startcellno)->applyFromArray($borderstyle);
                $sheet->getStyle('C' . $startcellno)->getAlignment()->setHorizontal('right');
                $sheet->setCellValue('F' . $startcellno, $debit);
                $sheet->getStyle('F' . $startcellno)->applyFromArray($borderstyle);
                $sheet->setCellValue('G' . $startcellno, $credit);
                $sheet->getStyle('G' . $startcellno)->applyFromArray($borderstyle);
                $sheet->setCellValue('H' . $startcellno, $bal);
                $sheet->getStyle('H' . $startcellno)->applyFromArray($borderstyle);
                $sheet->getStyle('F' . $startcellno . ':H' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('A' . $startcellno . ':H' . $startcellno)->getFont()->setBold(true);

                $startcellno += 1;

                $sheet->mergeCells('A' . $startcellno . ':H' . $startcellno);
                $sheet->setCellValue('A' . $startcellno, 'ASSESSMENT');
                $sheet->getStyle('A' . $startcellno . ':H' . $startcellno)->applyFromArray($borderstyle);

                $startcellno += 1;

                if ($studinfo->levelid == 14 || $studinfo->levelid == 15) {
                    $getPaySched = db::select('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate
                    from studpayscheddetail
                    where studid = ? and syid = ? and semid = ? and deleted = 0
                    group by MONTH(duedate)
                    order by duedate', [$studid, $syid, $semid]);

                    $getPaySched = db::table('studpayscheddetail')
                        ->select(db::raw('select sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate'))
                        ->where('studid', $studid)
                        ->where('syid', $syid)
                        ->where(function ($q) use ($semid) {
                            if (db::table('schoolinfo')->first()->shssetup == 0) {
                                $q->where('semid', $semid);
                            }
                        })
                        ->where('deleted', 0)
                        ->groupBy(db::raw('MONTH(duedate)'))
                        ->get();

                } else if ($studinfo->levelid >= 17 && $studinfo->levelid <= 20) {
                    $getPaySched = db::select('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate
                    from studpayscheddetail
                    where studid = ? and syid = ? and semid = ? and deleted = 0
                    group by MONTH(duedate)
                    order by duedate', [$studid, $syid, $semid]);

                    $getPaySched = db::table('studpayscheddetail')
                        ->select(db::raw('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate'))
                        ->where('studid', $studid)
                        ->where('syid', $syid)
                        ->where('semid', $semid)
                        ->where('deleted', 0)
                        ->groupBy(db::raw('MONTH(duedate)'))
                        ->get();
                } else {
                    $getPaySched = db::select('sum(amount) as amountdue, sum(amountpay) as amountpay, sum(balance) as balance, duedate
                    from studpayscheddetail
                    where studid = ? and syid = ? and deleted = 0
                    group by MONTH(duedate)
                    order by duedate', [$studid, $syid]);
                }

                $assessbilling = 0;
                $assesspayment = 0;
                $assessbalance = 0;
                $totalBal = collect($getPaySched)->sum('balance');
                if (count($getPaySched) > 0) {
                    foreach ($getPaySched as $psched) {

                        // return $getPaySched;
                        // $totalBal += $psched->balance;
                        $assessbilling += $psched->amountdue;
                        $assesspayment += $psched->amountpay;
                        $assessbalance += $psched->balance;

                        $m = date_create($psched->duedate);
                        $f = date_format($m, 'F');
                        $m = date_format($m, 'm');

                        if ($psched->duedate != '') {
                            $particulars = 'TUITION/BOOKS/OTH FEE - ' . strtoupper($f);
                        } else {
                            if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'faai') {
                                $particulars = 'REGISTRATION/MISCELLANEOUS/BOOKS/GENYO';
                            } else {
                                $particulars = 'TUITION/BOOKS/OTH FEE';
                            }
                            $m = 0;
                        }


                        // return $showall;
                        if ($month == null || $month == "") {
                            // return $m . ' != ' . $month;
                            if ($m != $month) {
                                if ($psched->balance > 0) {
                                    $sheet->mergeCells('A' . $startcellno . ':B' . $startcellno);
                                    $sheet->setCellValue('A' . $startcellno, '');
                                    $sheet->getStyle('A' . $startcellno . ':B' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->mergeCells('C' . $startcellno . ':E' . $startcellno);
                                    $sheet->setCellValue('C' . $startcellno, $particulars);
                                    $sheet->getStyle('C' . $startcellno . ':E' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->setCellValue('F' . $startcellno, $psched->amountdue);
                                    $sheet->getStyle('F' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->setCellValue('G' . $startcellno, $psched->amountpay);
                                    $sheet->getStyle('G' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->setCellValue('H' . $startcellno, $psched->balance);
                                    $sheet->getStyle('H' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->getStyle('F' . $startcellno . ':H' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');

                                    $startcellno += 1;
                                }
                            } else {
                                if ($psched->balance > 0) {
                                    $sheet->mergeCells('A' . $startcellno . ':B' . $startcellno);
                                    $sheet->setCellValue('A' . $startcellno, '');
                                    $sheet->getStyle('A' . $startcellno . ':B' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->mergeCells('C' . $startcellno . ':E' . $startcellno);
                                    $sheet->setCellValue('C' . $startcellno, $particulars);
                                    $sheet->getStyle('C' . $startcellno . ':E' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->setCellValue('F' . $startcellno, $psched->amountdue);
                                    $sheet->getStyle('F' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->setCellValue('G' . $startcellno, $psched->amountpay);
                                    $sheet->getStyle('G' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->setCellValue('H' . $startcellno, $psched->balance);
                                    $sheet->getStyle('H' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->getStyle('F' . $startcellno . ':H' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');
                                } else {
                                    $sheet->mergeCells('A' . $startcellno . ':B' . $startcellno);
                                    $sheet->setCellValue('A' . $startcellno, '');
                                    $sheet->getStyle('A' . $startcellno . ':B' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->mergeCells('C' . $startcellno . ':E' . $startcellno);
                                    $sheet->setCellValue('C' . $startcellno, $particulars);
                                    $sheet->getStyle('C' . $startcellno . ':E' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->setCellValue('F' . $startcellno, $psched->amountdue);
                                    $sheet->getStyle('F' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->setCellValue('G' . $startcellno, $psched->amountpay);
                                    $sheet->getStyle('G' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->setCellValue('H' . $startcellno, $psched->balance);
                                    $sheet->getStyle('H' . $startcellno)->applyFromArray($borderstyle);
                                    $sheet->getStyle('F' . $startcellno . ':H' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');
                                }
                                $startcellno += 1;
                                break;
                            }
                        } else {
                            // return $m . ' != ' . $month;
                            if ($m != $month) {

                                $sheet->mergeCells('A' . $startcellno . ':B' . $startcellno);
                                $sheet->setCellValue('A' . $startcellno, '');
                                $sheet->getStyle('A' . $startcellno . ':B' . $startcellno)->applyFromArray($borderstyle);
                                $sheet->mergeCells('C' . $startcellno . ':E' . $startcellno);
                                $sheet->setCellValue('C' . $startcellno, $particulars);
                                $sheet->getStyle('C' . $startcellno . ':E' . $startcellno)->applyFromArray($borderstyle);
                                $sheet->setCellValue('F' . $startcellno, $psched->amountdue);
                                $sheet->getStyle('F' . $startcellno)->applyFromArray($borderstyle);
                                $sheet->setCellValue('G' . $startcellno, $psched->amountpay);
                                $sheet->getStyle('G' . $startcellno)->applyFromArray($borderstyle);
                                $sheet->setCellValue('H' . $startcellno, $psched->balance);
                                $sheet->getStyle('H' . $startcellno)->applyFromArray($borderstyle);
                                $sheet->getStyle('F' . $startcellno . ':H' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');
                                $startcellno += 1;

                            } else {

                                $sheet->mergeCells('A' . $startcellno . ':B' . $startcellno);
                                $sheet->setCellValue('A' . $startcellno, '');
                                $sheet->getStyle('A' . $startcellno . ':B' . $startcellno)->applyFromArray($borderstyle);
                                $sheet->mergeCells('C' . $startcellno . ':E' . $startcellno);
                                $sheet->setCellValue('C' . $startcellno, $particulars);
                                $sheet->getStyle('C' . $startcellno . ':E' . $startcellno)->applyFromArray($borderstyle);
                                $sheet->setCellValue('F' . $startcellno, $psched->amountdue);
                                $sheet->getStyle('F' . $startcellno)->applyFromArray($borderstyle);
                                $sheet->setCellValue('G' . $startcellno, $psched->amountpay);
                                $sheet->getStyle('G' . $startcellno)->applyFromArray($borderstyle);
                                $sheet->setCellValue('H' . $startcellno, $psched->balance);
                                $sheet->getStyle('H' . $startcellno)->applyFromArray($borderstyle);
                                $sheet->getStyle('F' . $startcellno . ':H' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');
                                $startcellno += 1;

                                break;
                            }
                        }
                    }

                    $sheet->mergeCells('A' . $startcellno . ':B' . $startcellno);
                    $sheet->setCellValue('A' . $startcellno, '');
                    $sheet->getStyle('A' . $startcellno . ':B' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->mergeCells('C' . $startcellno . ':E' . $startcellno);
                    $sheet->setCellValue('C' . $startcellno, 'TOTAL');
                    $sheet->getStyle('C' . $startcellno)->getAlignment()->setHorizontal('right');
                    $sheet->getStyle('C' . $startcellno . ':E' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('F' . $startcellno, $assessbilling);
                    $sheet->getStyle('F' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('G' . $startcellno, $assesspayment);
                    $sheet->getStyle('G' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('H' . $startcellno, $assessbalance);
                    $sheet->getStyle('H' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->getStyle('F' . $startcellno . ':H' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle('A' . $startcellno . ':H' . $startcellno)->getFont()->setBold(true);

                    $startcellno += 1;

                    $sheet->mergeCells('A' . $startcellno . ':B' . $startcellno);
                    $sheet->setCellValue('A' . $startcellno, '');
                    $sheet->getStyle('A' . $startcellno . ':B' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->mergeCells('C' . $startcellno . ':E' . $startcellno);
                    $sheet->setCellValue('C' . $startcellno, 'TOTAL BALANCE');
                    $sheet->getStyle('C' . $startcellno)->getAlignment()->setHorizontal('right');
                    $sheet->getStyle('C' . $startcellno . ':E' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('F' . $startcellno, $assessbilling);
                    $sheet->getStyle('F' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('G' . $startcellno, $assesspayment);
                    $sheet->getStyle('G' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('H' . $startcellno, ($totalBal - $assesspayment));
                    $sheet->getStyle('H' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->getStyle('F' . $startcellno . ':H' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle('A' . $startcellno . ':H' . $startcellno)->getFont()->setBold(true);

                    $startcellno += 1;

                    $sheet->mergeCells('A' . $startcellno . ':B' . $startcellno);
                    $sheet->setCellValue('A' . $startcellno, '');
                    $sheet->getStyle('A' . $startcellno . ':B' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->mergeCells('C' . $startcellno . ':E' . $startcellno);
                    $sheet->setCellValue('C' . $startcellno, 'TOTAL AMOUNT DUE');
                    $sheet->getStyle('C' . $startcellno)->getAlignment()->setHorizontal('right');
                    $sheet->getStyle('C' . $startcellno . ':E' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('F' . $startcellno, '');
                    $sheet->getStyle('F' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('G' . $startcellno, '');
                    $sheet->getStyle('G' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('H' . $startcellno, ($totalBal - $assesspayment));
                    $sheet->getStyle('H' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->getStyle('F' . $startcellno . ':H' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle('A' . $startcellno . ':H' . $startcellno)->getFont()->setBold(true);

                } else {
                    $sheet->mergeCells('A' . $startcellno . ':B' . $startcellno);
                    $sheet->setCellValue('A' . $startcellno, '');
                    $sheet->getStyle('A' . $startcellno . ':B' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->mergeCells('C' . $startcellno . ':E' . $startcellno);
                    $sheet->setCellValue('C' . $startcellno, 'TOTAL BALANCE');
                    $sheet->getStyle('C' . $startcellno)->getAlignment()->setHorizontal('right');
                    $sheet->getStyle('C' . $startcellno . ':E' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('F' . $startcellno, $debit);
                    $sheet->getStyle('F' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('G' . $startcellno, $credit);
                    $sheet->getStyle('G' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('H' . $startcellno, $bal);
                    $sheet->getStyle('H' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->getStyle('F' . $startcellno . ':H' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle('A' . $startcellno . ':H' . $startcellno)->getFont()->setBold(true);

                    $startcellno += 1;

                    $sheet->mergeCells('A' . $startcellno . ':B' . $startcellno);
                    $sheet->setCellValue('A' . $startcellno, '');
                    $sheet->getStyle('A' . $startcellno . ':B' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->mergeCells('C' . $startcellno . ':E' . $startcellno);
                    $sheet->setCellValue('C' . $startcellno, 'TOTAL AMOUNT DUE');
                    $sheet->getStyle('C' . $startcellno)->getAlignment()->setHorizontal('right');
                    $sheet->getStyle('C' . $startcellno . ':E' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('F' . $startcellno, '');
                    $sheet->getStyle('F' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('G' . $startcellno, '');
                    $sheet->getStyle('G' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->setCellValue('H' . $startcellno, $bal);
                    $sheet->getStyle('H' . $startcellno)->applyFromArray($borderstyle);
                    $sheet->getStyle('F' . $startcellno . ':H' . $startcellno)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle('A' . $startcellno . ':H' . $startcellno)->getFont()->setBold(true);

                }
                $startcellno += 2;
                if ($notestatus > 0) {
                    $sheet->setCellValue('A' . $startcellno, 'NOTES:');
                    $startcellno += 1;
                    // $signatories.='<span style="font-size: 9px;font-weight: bold">NOTES:</span><br/>';
                    foreach ($notes as $note) {
                        $sheet->mergeCells('B' . $startcellno . ':G' . $startcellno);
                        $sheet->setCellValue('B' . $startcellno, $note->description);
                        $startcellno += 1;
                    }
                    $startcellno += 1;
                }
                $sheet->mergeCells('A' . $startcellno . ':B' . $startcellno);
                $sheet->setCellValue('A' . $startcellno, 'Prepared By:');
                $sheet->mergeCells('F' . $startcellno . ':H' . $startcellno);
                $sheet->setCellValue('F' . $startcellno, 'Received By:');

                $startcellno += 2;

                $sheet->mergeCells('A' . $startcellno . ':D' . $startcellno);
                $sheet->getStyle('A' . $startcellno . ':D' . $startcellno)->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->mergeCells('F' . $startcellno . ':H' . $startcellno);
                $sheet->getStyle('F' . $startcellno . ':H' . $startcellno)->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                $startcellno += 1;

                $sheet->mergeCells('A' . $startcellno . ':D' . $startcellno);
                // $sheet->setCellValue('A'.$startcellno,$preparedby->firstname.' '.$preparedby->middlename.' '.$preparedby->lastname.' '.$preparedby->suffix);
                $sheet->getStyle('A' . $startcellno)->getAlignment()->setHorizontal('center');
                $sheet->setCellValue('F' . $startcellno, 'Date:');
                $sheet->getStyle('F' . $startcellno . ':H' . $startcellno)->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment; filename="Statement of Account.xlsx"');
                $writer->save("php://output");
            }
        }
    }
    public function getnote(Request $request)
    {
        // return $request->all();
        $schoolreportsnote = DB::table('schoolreportsnote')
            ->where('type', $request->get('type'))
            ->where('deleted', '0')
            ->get();

        $html = '<div class="col-md-12">';
        if (count($schoolreportsnote) == 0) {
            $html .= '<label><em>Separated by paragraphs</em></label><br/>
                    <textarea class="form-control" rows="1" name="notes[]" id="0"></textarea><br/>';
            $html .= '<textarea class="form-control" rows="1" name="notes[]" id="0"></textarea><br/>';
            $html .= '<textarea class="form-control" rows="1" name="notes[]" id="0"></textarea><br/>';
            $html .= '<textarea class="form-control" rows="1" name="notes[]" id="0"></textarea><br/>';
        } else {
            $status = 0;
            for ($x = 0; $x < 4; $x++) {

                try {
                    if ($schoolreportsnote[$x]->status == 1) {
                        $status += 1;
                    }
                    $html .= '<textarea class="form-control" rows="1" name="notes[]" id="' . $schoolreportsnote[$x]->id . '">' . $schoolreportsnote[$x]->description . '</textarea><br/>';

                } catch (\Exception $e) {

                    $html .= '<textarea class="form-control" rows="1" name="notes[]"id="0"></textarea><br/>';

                }

            }
            if ($status == 0) {
                $active = '';
                $inactive = 'checked';
            } else {
                $inactive = '';
                $active = 'checked';
            }
            $html .= '<div class="form-group clearfix">
                        <div class="icheck-primary d-inline">
                        <input type="radio" id="radiobutton1" value="1" name="notestatus" ' . $active . '>
                        <label for="radiobutton1">
                            Active
                        </label>
                        </div>
                        <div class="icheck-primary d-inline">
                        <input type="radio" id="radiobutton2" value="0" name="notestatus" ' . $inactive . '>
                        <label for="radiobutton2">
                        Inactive
                        </label>
                        </div>
                    </div>';
        }
        $html .= '</div>';
        return $html;
    }
    public function submitnotes(Request $request)
    {
        // return $request->all();
        $notes = json_decode($request->get('notes'));
        $schoolreportsnotedetail = DB::table('schoolreportsnote')
            ->where('type', $request->get('type'))
            ->where('deleted', '0')
            ->count();

        if (count($notes) > 0) {
            foreach ($notes as $note) {
                if ($note->id == 0) {
                    $noteid = DB::table('schoolreportsnote')
                        ->insertGetID([
                            'description' => $note->content,
                            'createdby' => auth()->user()->id,
                            'createddatetime' => date('Y-m-d H:i:s'),
                            'type' => 1,
                            'status' => 1
                        ]);
                    // DB::table('schoolreportsnotedetail')
                    // ->insert([
                    //     'type'              => 1,
                    //     'status'            => 1,
                    //     'noteid'            => $noteid
                    // ]);
                } else {
                    DB::table('schoolreportsnote')
                        ->where('id', $note->id)
                        ->update([
                            'description' => $note->content,
                            'updatedby' => auth()->user()->id,
                            'updateddatetime' => date('Y-m-d H:i:s'),
                            'status' => $request->get('notestatus')
                        ]);

                }
            }
        }

        return '1';
    }
    public function exportall(Request $request)
    {
        $semid = $request->get('selectedsemester');
        $syid = $request->get('selectedschoolyear');
        $levelid = $request->get('selectedgradelevel');
        $sectionid = $request->get('selectedsection');
        $courseid = $request->get('selectedcourse');
        $selectedmonth = $request->get('selectedmonth');
        $selectedschoolyear = DB::table('sy')->where('id', $syid)->first()->sydesc;

        if ($levelid == 00) {
            $students = collect(StatementofAccountModel::allstudents($levelid, $syid, $semid, $sectionid, $courseid, $selectedmonth))->values();
        } else {
            $students = collect(StatementofAccountModel::allstudents($levelid, $syid, $semid, $sectionid, $courseid, $selectedmonth))->values();
        }
        $students = collect($students)->unique('id')->all();

        // dd($students);
        if (count($students) > 0) {
            if (db::table('schoolinfo')->first()->snr == 'hcbi') {
                $pdf = PDF::loadview('finance/reports/pdf/pdf_statementofacct_all_hcbi', compact('syid', 'selectedmonth', 'semid', 'students', 'levelid'));
                return $pdf->stream('studledger.pdf');
            } else {
                foreach ($students as $student) {
                    // return collect($student);
                    $units = db::table('college_studsched')
                        ->select(db::raw('SUM(lecunits) + SUM(labunits) AS totalunits'))
                        ->join('college_classsched', 'college_studsched.schedid', '=', 'college_classsched.id')
                        ->join('college_prospectus', 'college_classsched.subjectID', '=', 'college_prospectus.id')
                        ->where('college_studsched.studid', $student->id)
                        ->where('college_studsched.deleted', 0)
                        ->where('college_classsched.deleted', 0)
                        ->where('college_classsched.syID', $request->get('selectedschoolyear'))
                        ->where('college_classsched.semesterID', $request->get('selectedsemester'))
                        ->first()->totalunits;

                    $unitprice = 0;

                    if ($student->feesid != null) {
                        $tuitionheader = DB::table('tuitionheader')
                            ->where('id', $student->feesid)
                            ->where('syid', $request->get('selectedschoolyear'))
                            ->where('semid', $request->get('selectedsemester'))
                            ->where('levelid', $student->levelid)
                            ->where('deleted', '0')
                            ->first();

                        if ($tuitionheader) {
                            $tuitiondetail = DB::table('tuitiondetail')
                                ->select('tuitiondetail.amount')
                                ->join('chrngsetup', 'tuitiondetail.classificationid', '=', 'chrngsetup.classid')
                                ->where('tuitiondetail.headerid', $student->feesid)
                                ->where('chrngsetup.groupname', 'TUI')
                                ->where('tuitiondetail.deleted', '0')
                                ->where('chrngsetup.deleted', '0')
                                ->first();

                            if ($tuitiondetail) {
                                $unitprice = $tuitiondetail->amount;
                            }
                            // return $tuitiondetail;

                        }
                    }
                    $student->units = $units;
                    $student->unitprice = $unitprice;
                    $miscs = DB::table('studpayscheddetail')
                        ->select('chrngsetup.classid', 'itemized', 'groupname')
                        ->join('chrngsetup', 'studpayscheddetail.classid', '=', 'chrngsetup.classid')
                        ->where('studpayscheddetail.deleted', '0')
                        ->where('studid', $student->id)
                        ->where('syid', $request->get('selectedschoolyear'))
                        ->where('groupname', 'like', '%misc%')
                        ->get();

                    if (count($miscs) > 0) {
                        foreach ($miscs as $misc) {
                            if ($student->levelid == 14 || $student->levelid == 15) {
                                $items = DB::table('studledgeritemized')
                                    ->select('studledgeritemized.*', 'items.itemcode', 'items.description')
                                    ->join('items', 'studledgeritemized.itemid', '=', 'items.id')
                                    ->where('studledgeritemized.studid', $student->id)
                                    ->where('studledgeritemized.syid', $request->get('selectedschoolyear'))
                                    ->where('studledgeritemized.deleted', 0)
                                    ->where(function ($q) use ($semid) {
                                        if (db::table('schoolinfo')->first()->shssetup == 0) {
                                            $q->where('semid', $semid);
                                        }
                                    })
                                    ->where('studledgeritemized.classificationid', $misc->classid)
                                    ->get();

                            } elseif ($student->levelid == 17 || $student->levelid == 18 || $student->levelid == 19 || $student->levelid == 20) {

                                $items = DB::table('studledgeritemized')
                                    ->select('studledgeritemized.*', 'items.itemcode', 'items.description')
                                    ->join('items', 'studledgeritemized.itemid', '=', 'items.id')
                                    ->where('studledgeritemized.studid', $student->id)
                                    ->where('studledgeritemized.syid', $request->get('selectedschoolyear'))
                                    ->where('studledgeritemized.deleted', 0)
                                    ->where('semid', $semid)
                                    ->where('studledgeritemized.classificationid', $misc->classid)
                                    ->get();

                            } else {
                                $items = DB::table('studledgeritemized')
                                    ->select('studledgeritemized.*', 'items.itemcode', 'items.description')
                                    ->join('items', 'studledgeritemized.itemid', '=', 'items.id')
                                    ->where('studledgeritemized.studid', $student->id)
                                    ->where('studledgeritemized.syid', $request->get('selectedschoolyear'))
                                    ->where('studledgeritemized.deleted', 0)
                                    ->where('studledgeritemized.classificationid', $misc->classid)
                                    ->get();
                            }
                            if (count($items) > 0) {
                                foreach ($items as $item) {
                                    $item->balance = 0;

                                    if ($item->totalamount > 0) {
                                        $item->balance = $item->itemamount - $item->totalamount;
                                    } else {
                                        $item->balance = $item->itemamount;
                                    }
                                }
                            }

                            $misc->items = $items;

                        }
                    }
                    $student->miscs = $miscs;

                    if ($student->levelid == 14 || $student->levelid == 15) {
                        $ledger = db::table('studledger')
                            ->select('studledger.*', 'chrngsetup.groupname')
                            ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                            ->where('studledger.studid', $student->id)
                            ->where('studledger.syid', $request->get('selectedschoolyear'))
                            ->where(function ($q) use ($semid) {
                                if (DB::table('schoolinfo')->first()->shssetup == 0) {
                                    $q->where('semid', $semid);
                                }
                            })
                            ->where('studledger.void', 0)
                            ->where('studledger.deleted', 0)
                            ->orderBy('studledger.id', 'asc')
                            ->get();
                    } elseif ($student->levelid >= 17 && $student->levelid <= 25) {
                        $ledger = db::table('studledger')
                            ->select('studledger.*', 'chrngsetup.groupname')
                            ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                            ->where('studledger.studid', $student->id)
                            ->where('studledger.syid', $request->get('selectedschoolyear'))
                            ->where('studledger.semid', $semid)
                            ->where('studledger.deleted', 0)
                            ->orderBy('studledger.id', 'asc')
                            ->get();
                    } else {
                        $ledger = db::table('studledger')
                            ->select('studledger.*', 'chrngsetup.groupname')
                            ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                            ->where('studledger.studid', $student->id)
                            ->where('studledger.syid', $request->get('selectedschoolyear'))
                            ->where('studledger.deleted', 0)
                            ->orderBy('studledger.id', 'asc')
                            ->get();
                    }
                    if ($request->get('selectedmonth') == null) {
                        $tuis = DB::table('studpayscheddetail')
                            // ->select('chrngsetup.classid','itemized','groupname')
                            ->join('chrngsetup', 'studpayscheddetail.classid', '=', 'chrngsetup.classid')
                            ->where('studpayscheddetail.deleted', '0')
                            ->where('studid', $student->id)
                            ->where('syid', $request->get('selectedschoolyear'))
                            ->where('groupname', 'like', '%tui%')
                            ->get();
                    } else {
                        $tuis = DB::table('studpayscheddetail')
                            // ->select('chrngsetup.classid','itemized','groupname')
                            ->join('chrngsetup', 'studpayscheddetail.classid', '=', 'chrngsetup.classid')
                            ->where('studpayscheddetail.deleted', '0')
                            ->where('studid', $student->id)
                            ->where('syid', $request->get('selectedschoolyear'))
                            ->where('duedate', '<=', date('Y') . '-' . date('m', strtotime($request->get('selectedmonth'))) . '-' . date('D'))
                            ->where('groupname', 'like', '%tui%')
                            ->get();
                    }
                    $student->tuis = $tuis;
                    $student->ledgers = $ledger;
                }
            }
        }


        if ($request->get('exporttype') == 'pdf') {
            $students = collect($students)->toArray();
            $students = array_chunk($students, 2);

            // return $students;

            // return $students;
            $pdf = new MYPDFStatementOfAccount(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('CK');
            $pdf->SetAuthor('CK Children\'s Publishing');
            $pdf->SetTitle(DB::table('schoolinfo')->first()->schoolname . ' - Statement of Account');
            $pdf->SetSubject('Statement of Account');

            // set header and footer fonts
            // $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            // $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

            // // set default monospaced font
            // $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

            // set margins
            $pdf->SetMargins(3, 10, 5);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

            $pdf->setPrintFooter(false);


            // $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 4, 'color' => array(255, 0, 0)));
            // set auto page breaks
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

            // set image scale factor
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

            // set some language-dependent strings (optional)
            if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
                require_once(dirname(__FILE__) . '/lang/eng.php');
                $pdf->setLanguageArray($l);
            }

            // if(strtolower($schoolinfo->abbreviation) == 'apmc')
            // {
            //     $pdf->setPrintHeader(false);
            // }


            // ---------------------------------------------------------

            // set font
            // $pdf->SetFont('dejavusans', '', 10);


            // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
            // Print a table

            $pdf->AddPage('8.5x11');

            // $monthname = date('M', strtotime(date('Y').'-'.));
            if (db::table('schoolinfo')->first()->snr == 'hcbi') {
                // return $students;
                $view = \View::make('finance/reports/pdf/pdf_statementofacct_all_hcbi', compact('selectedschoolyear', 'selectedmonth', 'selectedsemester', 'students'));
            } else {
                $view = \View::make('finance/reports/pdf/pdf_statementofacct_hccsi', compact('selectedschoolyear', 'selectedmonth', 'selectedsemester', 'students'));
            }

            $html = $view->render();
            $pdf->writeHTML($html, true, false, true, false, '');
            // ---------------------------------------------------------
            //Close and output PDF document
            $pdf->Output('Statement of Account.pdf', 'I');
        } else {
            $department = DB::table('gradelevel')
                ->where('id', $request->get('selectedgradelevel'))
                ->first();


            if ($department) {
                $acadprogid = $department->acadprogid;
                $department = $department->levelname;
            } else {
                $acadprogid = 6;
                $department = "College";
            }
            $semid = $request->get('selectedsemester');
            // return $students;
            // $data = $data->values();
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headerLabel = $department . ' STATEMENT OF ACCOUNTS S. Y. ' . $selectedschoolyear;

            if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'apmc') {
                $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                $drawing->setName('Logo');
                $drawing->setDescription('Logo');
                $drawing->setPath(base_path() . '/public/' . DB::table('schoolinfo')->first()->picurl);
                $drawing->setHeight(80);
                $drawing->setWorksheet($sheet);
                $drawing->setCoordinates('A1');
                $drawing->setOffsetX(20);
                $drawing->setOffsetY(10);

                $drawing->getShadow()->setVisible(true);
                $drawing->getShadow()->setDirection(45);

                $sheet->setCellValue('B2', '                      ' . DB::table('schoolinfo')->first()->schoolname);
                $sheet->setCellValue('B3', '                       S.Y ' . $selectedschoolyear);
                $sheet->setCellValue('B6', '                       DEPARTMENT : ' . $department);
                $sheet->setCellValue('B7', '                       SEMESTER : ' . DB::table('semester')->where('id', $request->get('selectedsemester'))->first()->semester);

                $sheet->getStyle('A9:L9')->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('A9:L9')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->setCellValue('A9', '#');
                $sheet->setCellValue('B9', 'Student Name');
                $sheet->setCellValue('C9', 'Grade level');
                $sheet->setCellValue('D9', 'Course & Section');
                $sheet->setCellValue('E9', 'UNITS');
                $sheet->setCellValue('F9', 'TF');
                $sheet->setCellValue('G9', 'EF');
                $sheet->setCellValue('H9', 'AVR/IF');
                $sheet->setCellValue('I9', 'OF');
                $sheet->setCellValue('J9', 'CS LAB');
                $sheet->setCellValue('K9', 'NSTP');
                $sheet->setCellValue('L9', 'Total Amount');

                $startcellno = 10;
            } else {
                $sheet->mergeCells('A1:L1');
                $sheet->setCellValue('A1',  $headerLabel ?? '');
                $sheet->getStyle('A1:L1')->getFont()->setBold(true);
                $sheet->getStyle('A1:L1')->getAlignment()->setHorizontal('center');
                $sheet->getStyle('A1:L1')->getFont()->setSize(20);

                $sheet->mergeCells('A2:L2');
                $sheet->setCellValue('A2', DB::table('semester')->where('id', $request->get('selectedsemester'))->first()->semester);
                $sheet->getStyle('A2:L2')->getFont()->setBold(true);
                $sheet->getStyle('A2:L2')->getAlignment()->setHorizontal('center');
                $sheet->getStyle('A2:L2')->getFont()->setSize(20);

                $sheet->getStyle('A5:L5')->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('A5:L5')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->setCellValue('A5', '#');
                $sheet->setCellValue('B5', 'Student Name');
                $sheet->setCellValue('C5', 'Grade level');
                $sheet->setCellValue('D5', 'Course & Section');
                $sheet->setCellValue('E5', 'UNITS');
                $sheet->setCellValue('F5', 'TF');
                $sheet->setCellValue('G5', 'EF');
                $sheet->setCellValue('H5', 'AVR/IF');
                $sheet->setCellValue('I5', 'OF');
                $sheet->setCellValue('J5', 'CS LAB');
                $sheet->setCellValue('K5', 'NSTP');
                $sheet->setCellValue('L5', 'Total Amount');

                $startcellno = 6;
            }


            foreach (array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L') as $columnID) {
                $sheet->getColumnDimension($columnID)
                    ->setAutoSize(true);
            }

            if (count($students) > 0) {
                foreach ($students as $key => $student) {
                    // return collect($student);

                    $itemized = DB::table('studledgeritemized')
                        ->select('itemamount', 'items.description', 'classid', 'studledgeritemized.semid')
                        ->join('items', 'studledgeritemized.itemid', '=', 'items.id')
                        ->where('studid', $student->id)
                        ->where('studledgeritemized.syid', $request->get('selectedschoolyear'))
                        ->where('studledgeritemized.semid', $semid)
                        ->where('studledgeritemized.deleted', 0)
                        ->where('classid', '!=', 7)
                        ->where('studledgeritemized.deleted', 0)
                        ->get();


                    $nstp = 0;
                    $comlabfee = 0;
                    $totalassessment = (collect($itemized)->where('classid', '!=', 7)->sum('itemamount') + ($student->unitprice * $student->units));

                    if (collect($student->ledgers)->where('classid', 20)->count() > 0) {
                        $totalassessment += collect($student->ledgers)->where('classid', 20)->sum('amount');
                        $checknstp = collect($student->ledgers)->where('classid', 20)->filter(function ($item) {
                            return false !== stristr($item->particulars, 'nstp');
                        })->values();

                        if (count($checknstp) > 0) {
                            $nstp += collect($checknstp)->sum('amount');
                        }
                        $checkcomlabfee = collect($student->ledgers)->where('classid', 20)->filter(function ($item) {
                            return true !== stristr($item->particulars, 'cs');
                        })->values();

                        if (count($checkcomlabfee) > 0) {
                            $comlabfee += collect($checkcomlabfee)->sum('amount');
                        }
                    }
                    // if($acadprogid == 5 || $acadprogid == 6)
                    // {
                    //     $itemized = collect($itemized)->where('semid', $semid)->values();
                    // }
                    // return $itemized;
                    $student->items = collect($itemized)->filter(function ($item) {
                        return false === stristr($item->description, 'avr') || false === stristr($item->description, 'internet') || false === stristr($item->description, 'lab');
                    });
                    // $student->items = collect($student->items)->filter(function ($item) {
                    //     return false ===  stristr($item->description, 'internet');
                    // });
                    // $student->items = collect($student->items)->filter(function ($item) {
                    //     return false ===  stristr($item->description, 'registration');
                    // });
                    // $student->items = collect($student->items)->filter(function ($item) {
                    //     return false ===  stristr($item->description, 'lab');
                    // });
                    $sheet->setCellValue('A' . $startcellno, $key + 1);
                    $sheet->setCellValue('B' . $startcellno, $student->lastname . ', ' . $student->firstname . ' ' . $student->middlename);
                    $sheet->setCellValue('C' . $startcellno, $student->levelname);
                    if (collect($student)->has('coursename')) {
                        $sheet->setCellValue('D' . $startcellno, $student->coursename . ' - ' . $student->sectionname);
                    }
                    $sheet->setCellValue('E' . $startcellno, $student->units);
                    $sheet->setCellValue('F' . $startcellno, $student->units * $student->unitprice);
                    $sheet->setCellValue('G' . $startcellno, collect($itemized)->filter(function ($item) {
                        return false !== stristr($item->description, 'registration');
                    })->sum('itemamount'));
                    $sheet->setCellValue('H' . $startcellno, collect($itemized)->filter(function ($item) {
                        return false !== stristr($item->description, 'avr');
                    })->sum('itemamount') + collect($itemized)->filter(function ($item) {
                        return false !== stristr($item->description, 'internet');
                    })->sum('itemamount'));

                    $sheet->setCellValue('I' . $startcellno, collect($student->items)->sum('itemamount'));
                    // $sheet->setCellValue('J'.$startcellno, collect($itemized)->filter(function ($item) { return false !==  stristr($item->description, 'lab'); })->sum('itemamount'));
                    if (strtolower(DB::table('schoolinfo')->first()->abbreviation) == 'apmc') {
                        $sheet->setCellValue('J' . $startcellno, $comlabfee);
                        $sheet->setCellValue('K' . $startcellno, $nstp);
                    } else {
                        $sheet->setCellValue('J' . $startcellno, collect($itemized)->where('classid', '19')->sum('itemamount'));
                        $sheet->setCellValue('K' . $startcellno, collect($student->ledgers)->where('classid', '20')->sum('amount'));
                    }
                    $sheet->setCellValue('L' . $startcellno, "=SUM(F" . $startcellno . ",G" . $startcellno . ",H" . $startcellno . ",I" . $startcellno . ",J" . $startcellno . ",K" . $startcellno . ")");
                    $startcellno += 1;
                }
            }
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="Statement of Account - ' . $selectedschoolyear . '.xlsx"');
            $writer->save("php://output");
        }
    }

    public function sendEmail(Request $request)
    {
        $studentIds = $request->input('student_ids', []);
        $syid = $request->input('selectedschoolyear');
        $semid = $request->input('selectedsemester');
        $monthsetupid = $request->input('selectedmonth');
        $monthdesc = '';
        if ($monthsetupid) {
            $monthData = DB::table('monthsetup')->where('id', $monthsetupid)->first();
            if ($monthData) {
                $monthdesc = $monthData->description;
            }
        }

        foreach ($studentIds as $studid) {
            $levelid = 0;
            $sectionid = 0;
            $courseid = 0;
            $sectionname = '';
            $courseabrv = '';
            $studinfo = DB::table('studinfo')->where('id', $studid)->first();
            if (!$studinfo || empty($studinfo->semail))
                continue;

            $estud = DB::table('enrolledstud')
                ->select('levelid', 'sectionid', 'studstatus')
                ->where('studid', $studid)
                ->where('syid', $syid)
                ->where('deleted', 0)
                ->first();

            if ($estud) {
                $levelid = $estud->levelid;
                $sectionid = $estud->sectionid;
                $section = DB::table('sections')->where('id', $sectionid)->first();
                if ($section)
                    $sectionname = $section->sectionname;
            } else {
                $estud = DB::table('sh_enrolledstud')
                    ->select('levelid', 'sectionid', 'studstatus')
                    ->where('studid', $studid)
                    ->where('deleted', 0)
                    ->first();
                if ($estud) {
                    $levelid = $estud->levelid;
                    $sectionid = $estud->sectionid;
                    $section = DB::table('sections')->where('id', $sectionid)->first();
                    if ($section)
                        $sectionname = $section->sectionname;
                } else {
                    $estud = DB::table('college_enrolledstud')
                        ->select('yearLevel as levelid', 'courseid', 'studstatus')
                        ->where('studid', $studid)
                        ->where('deleted', 0)
                        ->first();
                    if ($estud) {
                        $levelid = $estud->levelid;
                        $courseid = $estud->courseid;
                        $courses = DB::table('college_courses')->where('id', $courseid)->first();
                        if ($courses)
                            $courseabrv = $courses->courseabrv;
                    } else {
                        $estud = DB::table('studinfo')
                            ->select('levelid', 'studstatus')
                            ->where('id', $studid)
                            ->first();
                        if ($estud)
                            $levelid = $estud->levelid;
                    }
                }
            }

            // LEDGER
            if ($levelid == 14 || $levelid == 15) {
                $ledger = DB::table('studledger')
                    ->select('studledger.*', 'chrngsetup.groupname')
                    ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                    ->where('studledger.studid', $studid)
                    ->where('studledger.syid', $syid)
                    ->where('studledger.amount', '>', 0)
                    ->where(function ($q) use ($semid) {
                        if (DB::table('schoolinfo')->first()->shssetup == 0) {
                            $q->where('semid', $semid);
                        }
                    })
                    ->where('studledger.void', 0)
                    ->where('studledger.deleted', 0)
                    ->groupBy('studledger.id')
                    ->orderBy('studledger.createddatetime', 'asc')
                    ->get();
            } elseif ($levelid >= 17 && $levelid <= 25) {
                $ledger = DB::table('studledger')
                    ->select('studledger.*', 'chrngsetup.groupname')
                    ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                    ->where('studledger.studid', $studid)
                    ->where('studledger.syid', $syid)
                    ->where('studledger.semid', $semid)
                    ->where('studledger.classid', '!=', null)
                    ->where('studledger.amount', '>', 0)
                    ->where('studledger.deleted', 0)
                    ->where('studledger.void', 0)
                    ->groupBy('studledger.id')
                    ->orderBy('studledger.createddatetime', 'asc')
                    ->get();
            } else {
                $ledger = DB::table('studledger')
                    ->select('studledger.*', 'chrngsetup.groupname')
                    ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                    ->where('studledger.studid', $studid)
                    ->where('studledger.syid', $syid)
                    ->where('studledger.deleted', 0)
                    ->where('studledger.void', 0)
                    ->where('studledger.amount', '>', 0)
                    ->groupBy('studledger.id')
                    ->orderBy('studledger.createddatetime', 'asc')
                    ->get();
            }

            // PAYMENTS
            $payments = DB::table('studledger')
                ->select('studledger.*', 'chrngsetup.groupname')
                ->leftJoin('chrngsetup', 'studledger.classid', '=', 'chrngsetup.classid')
                ->where('studledger.studid', $studid)
                ->where('studledger.syid', $syid)
                ->where(function ($q) use ($semid, $levelid) {
                    if ($levelid == 14 || $levelid == 15) {
                        if ($semid == 3) {
                            $q->where('semid', 3);
                        } else {
                            if (DB::table('schoolinfo')->first()->shssetup == 0) {
                                $q->where('semid', $semid);
                            } else {
                                $q->where('semid', '!=', 3);
                            }
                        }
                    } elseif ($levelid >= 17 && $levelid <= 25) {
                        $q->where('semid', $semid);
                    }
                })
                ->where('payment', '>', 0)
                ->where('studledger.deleted', 0)
                ->where('studledger.void', 0)
                ->orderBy('studledger.createddatetime', 'asc')
                ->get();

            // MONTH DUE
            $monthsetup = DB::table('monthsetup')->where('id', $monthsetupid)->first();
            $monthinword = $monthsetup ? $monthsetup->description : '';
            $action = 'pdf';
            $monthdue = FinanceUtilityModel::assessment_gen($studid, $syid, $semid, $monthsetupid, $action);

            $selectedschoolyear = DB::table('sy')->where('id', $syid)->first()->sydesc ?? '';
            $selectedsemester = DB::table('semester')->where('id', $semid)->first()->semester ?? '';

            $pdf = PDF::loadView('finance/reports/pdf/pdf_statementofacct_default_v2', compact(
                'ledger',
                'payments',
                'monthsetup',
                'monthdue',
                'selectedschoolyear',
                'selectedsemester',
                'monthinword',
                'studinfo',
                'levelid',
                'courseabrv',
                'sectionname'
            ))->setPaper('letter');
            $pdfData = $pdf->output();

            \Mail::send('emails.soaemail', [
                'student' => $studinfo
            ], function ($message) use ($studinfo, $pdfData) {
                $message->to($studinfo->semail)
                    ->subject('Your Statement of Account')
                    ->attachData($pdfData, 'StatementOfAccount.pdf');
            });
        }

        return response()->json(['status' => 'ok']);
    }
    public function exportall_v2(Request $request)
    {
        $semid = $request->get('selectedsemester');
        $syid = $request->get('selectedschoolyear');
        $levelid = $request->get('selectedgradelevel');
        $sectionid = $request->get('selectedsection');
        $courseid = $request->get('selectedcourse');
        $selectedmonth = $request->get('selectedmonth');
        $students = $request->get('students');
        $balclassid = db::table('balforwardsetup')->first()->classid;
        $studarray = array();

        // return $students;

        $studinfo = db::table('studinfo')
            ->select(db::raw('id, sid, lastname, firstname, middlename, suffix'))
            ->whereIn('id', $students)
            ->get();

        $studinfo = collect($studinfo);

        $studledger = db::table('studledger')
            ->where('deleted', 0)
            ->whereIn('studid', $students)
            ->where('syid', $syid)
            ->where(function ($q) use ($levelid, $semid) {
                if ($levelid == 14 || $levelid == 15) {
                    if ($semid == 3) {
                        $q->where('semid', 3);
                    } else {
                        if (db::table('schoolinfo')->first()->shssetup == 0) {
                            $q->where('semid', $semid);
                        }
                    }
                } elseif ($levelid >= 17 && $levelid <= 25) {
                    $q->where('semid', $semid);
                } else {
                    if ($semid == 3) {
                        $q->where('semid', 3);
                    }
                }
            })
            ->orderBy('studid')
            ->orderBy('createddatetime')
            ->get();

        $ledgercollect = collect($studledger);
        // $ledger_old = $ledgercollect->where('classid', $balclassid)->where('amount', '>', 0);
        // $ledger_old->all();

        foreach ($students as $stud) {
            $totaldebit = 0;
            $totalcredit = 0;
            $oldtotal = 0;
            $chargestotal = 0;
            $paymenttotal = 0;

            $_stud = $studinfo->where('id', $stud)->first();
            $sid = $_stud->sid;
            $name = $_stud->lastname . ', ' . $_stud->firstname . ' ' . $_stud->middlename . ' ' . $_stud->suffix;
            $level = '';

            if ($levelid == 14 || $levelid == 15) {
                $einfo = db::table('sh_enrolledstud')
                    ->select('sectionname', 'levelname')
                    ->join('sections', 'sh_enrolledstud.sectionid', '=', 'sections.id')
                    ->join('gradelevel', 'sh_enrolledstud.levelid', '=', 'gradelevel.id')
                    ->where('studid', $stud)
                    ->where('sh_enrolledstud.syid', $syid)
                    ->where('sh_enrolledstud.deleted', 0)
                    ->first();

                if ($einfo) {
                    $level = $einfo->levelname . ' - ' . $einfo->sectionname;
                }
            } elseif ($levelid >= 17 && $levelid <= 25) {
                $einfo = db::table('college_enrolledstud')
                    ->select('courseabrv as course', 'levelname')
                    ->join('college_courses', 'college_enrolledstud.courseid', '=', 'college_courses.id')
                    ->join('gradelevel', 'college_enrolledstud.yearLevel', '=', 'gradelevel.id')
                    ->where('studid', $stud)
                    ->where('college_enrolledstud.syid', $syid)
                    ->where('college_enrolledstud.semid', $semid)
                    ->where('college_enrolledstud.deleted', 0)
                    ->first();

                if ($einfo) {
                    $level = $einfo->levelname . ' - ' . $einfo->course;
                }
            } else {
                $einfo = db::table('enrolledstud')
                    ->select('sectionname', 'levelname')
                    ->join('sections', 'enrolledstud.sectionid', '=', 'sections.id')
                    ->join('gradelevel', 'enrolledstud.levelid', '=', 'gradelevel.id')
                    ->where('studid', $stud)
                    ->where('enrolledstud.syid', $syid)
                    ->where('enrolledstud.deleted', 0)
                    ->first();

                if ($einfo) {
                    $level = $einfo->levelname . ' - ' . $einfo->sectionname;
                }

            }

            $ledgerold = array();
            $oldclass = $ledgercollect
                ->where('studid', $stud)
                ->where('classid', $balclassid)
                ->where('amount', '>', 0)
                ->all();

            // return $oldclass;

            foreach ($oldclass as $old) {
                array_push($ledgerold, (object) [
                    'createddatetime' => $old->createddatetime,
                    'particulars' => strtoupper($old->particulars),
                    'amount' => number_format($old->amount, 2)
                ]);

                $oldtotal += $old->amount;
                $totaldebit += $old->amount;
            }

            $ledgercharges = array();

            $charges = $ledgercollect
                ->where('studid', $stud)
                ->where('classid', '!=', $balclassid)
                ->where('amount', '>', 0)
                ->all();

            foreach ($charges as $chgs) {
                array_push($ledgercharges, (object) [
                    'createddatetime' => $chgs->createddatetime,
                    'particulars' => $chgs->particulars,
                    'amount' => number_format($chgs->amount, 2)
                ]);

                $chargestotal += $chgs->amount;
                $totaldebit += $chgs->amount;
            }

            $ledgerpayment = array();

            $payment = $ledgercollect
                ->where('studid', $stud)
                // ->where('classid', '!=', $balclassid)
                ->where('payment', '>', 0)
                ->all();

            foreach ($payment as $pay) {
                array_push($ledgerpayment, (object) [
                    'createddatetime' => $pay->createddatetime,
                    'ornum' => $pay->ornum,
                    'particulars' => $pay->particulars,
                    'payment' => number_format($pay->payment, 2)
                ]);

                $paymenttotal += $pay->payment;
                $totalcredit += $pay->payment;
            }

            // $monthdue =FinanceUtilityModel::assessment_gen($stud, $syid, $semid, $selectedmonth);
            $request['studid'] = $stud;
            $request['syid'] = $syid;
            $request['semid'] = $semid;
            $request['monthid'] = $selectedmonth;

            $monthdue = UtilityController::assessment_gen($request);
            // return $monthdue;
            $monthdueamount = 0;
            $totaldue = $totaldebit - $totalcredit;

            if ($monthdue) {
                foreach ($monthdue as $due) {
                    $monthdueamount += str_replace(',', '', $due->amount);
                }
            }


            array_push($studarray, (object) [
                'sid' => $sid,
                'name' => $name,
                'levelid' => $levelid,
                'level' => $level,
                'oldtotal' => number_format($oldtotal, 2),
                'chargestotal' => number_format($chargestotal, 2),
                'totaldebit' => number_format($totaldebit, 2),
                'totalcredit' => number_format($totalcredit, 2),
                'paymenttotal' => number_format($paymenttotal, 2),
                'ledgerold' => $ledgerold,
                'ledgercharges' => $ledgercharges,
                'ledgerpayment' => $ledgerpayment,
                'monthdue' => number_format($monthdueamount, 2),
                'totaldue' => number_format($totaldue, 2)
                // 'monthdue' => $monthdue
            ]);

        }

        $monthinword = db::table('monthsetup')
            ->where('id', $selectedmonth)
            ->first()
            ->description;

        // return $studarray;

        $pdf = new MYPDFStatementOfAccount(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetMargins(3, 10, 5);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->setPrintFooter(false);

        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // set some language-dependent strings (optional)
        if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
            require_once(dirname(__FILE__) . '/lang/eng.php');
            $pdf->setLanguageArray($l);
        }

        // Print a table

        $pdf->AddPage('8.5x11');



        $pdf = PDF::loadView('finance/reports/pdf/pdf_statementofacct_default_v2_all', compact(
            'monthinword',
            'studarray',
            'syid',
            'semid'
        ))
            ->setPaper('letter');
        return $pdf->stream('Statement Of Account.pdf');




    }

    public function statementofacctloadsection(Request $request)
    {
        $levelid = $request->get('levelid');
        $syid = $request->get('syid');
        $semid = $request->get('semid');

        if ($levelid == 14 || $levelid == 15) {
            $sections = DB::table('sections')
                ->select('sections.id', 'sectionname as description')
                ->join('sectiondetail', 'sections.id', 'sectionid')
                ->where('sectiondetail.deleted', 0)
                ->where('levelid', $levelid)
                ->where('syid', $syid)
                ->where('sections.deleted', 0)
                ->get();
        } elseif ($levelid >= 17 && $levelid <= 25) {
            $sections = db::table('college_courses')
                ->select('id', 'courseabrv as description')
                ->where('deleted', 0)
                ->get();
        } else {
            $sections = DB::table('sections')
                ->select('sections.id', 'sectionname as description')
                ->join('sectiondetail', 'sections.id', 'sectionid')
                ->where('sectiondetail.deleted', 0)
                ->where('levelid', $levelid)
                ->where('syid', $syid)
                ->where('sections.deleted', 0)
                ->get();
        }

        $list = '<option value="0">All</option>';
        foreach ($sections as $section) {
            $list .= '
                <option value="' . $section->id . '">' . $section->description . '</option>
            ';
        }

        return $list;
    }

    public function statementofaccsmsnotify(Request $request)
    {
        $semid = $request->get('semid');
        $syid = $request->get('syid');
        $levelid = $request->get('levelid');
        $sectionid = $request->get('sectionid');
        $courseid = $request->get('courseid');
        $selectedmonth = $request->get('monthid');
        $students = $request->get('students');
        $soainfo = array();

        $students = collect(StatementofAccountModel::allstudents($levelid, $syid, $semid, $sectionid, $courseid))->values();
        $month = db::table('monthsetup')->where('id', $selectedmonth)->first()->description;

        foreach ($students as $stud) {
            $request->replace([
                'studid' => $stud->id,
                'selectedschoolyear' => $syid,
                'selectedsemester' => $semid,
                'selectedmonth' => $selectedmonth,
                'action' => 'ep'
            ]);

            $balance = $this->getaccount_v2($request);

            $sid = $stud->sid;
            $name = $stud->lastname . ', ' . $stud->firstname . ' ' . $stud->middlename . ' ' . $stud->suffix;
            $contactno = '';

            if ($stud->ismothernum == 1) {
                $contactno = '+63' . substr($stud->mcontactno, 1);
            } elseif ($stud->isfathernum == 1) {
                $contactno = '+63' . substr($stud->fcontactno, 1);
            } else {
                $contactno = '+63' . substr($stud->gcontactno, 1);
            }


            array_push($soainfo, (object) array(
                'sid' => $sid,
                'name' => $name,
                'balance' => number_format($balance, 2),
                'contactno' => $contactno,
                'firstname' => $stud->firstname,
                'month' => $month
            ));
        }

        return $soainfo;

        // return UtilityControllers::statementofaccsmsnotify($semid, $syid, $levelid, $sectionid, $courseid, $selectedmonth, $students);

    }

    // public function statementofaccsmssend(Request $request)
    // {
    //     $smslist = $request->get('sms_list');
    //     $semid = $request->get('semid');
    //     $syid = $request->get('syid');
    //     $levelid = $request->get('levelid');
    //     $sectionid = $request->get('sectionid');
    //     $courseid = $request->get('courseid');
    //     $selectedmonth = $request->get('monthid');




    //     $datefrom = date_format(date_create(FinanceModel::getServerDateTime()), "Y-m-d 00:00");
    //     $dateto = date_format(date_create(FinanceModel::getServerDateTime()), "Y-m-d 23:59");

    //     $flag = db::table('soa_smsnotification')
    //         ->whereBetween('createddatetime', [$datefrom, $dateto])
    //         ->where('syid', $syid)
    //         ->where('semid', $semid)
    //         ->where('levelid', $levelid)
    //         ->where('sectionid', $sectionid)
    //         ->where('courseid', $courseid)
    //         ->where('monthid', $selectedmonth)
    //         ->count();

    //     if ($flag == 0) {
    //         foreach ($smslist as $sms) {
    //             $message = 'Hi ' . $sms['firstname'] . ', you have a remaining balance of ' . $sms['balance'] . ' on ' . $sms['month'] . ' For questions, please reach out to the Billing Office.';

    //             db::table('smsbunkertextblast')
    //                 ->insert([
    //                     'message' => $message,
    //                     'receiver' => $sms['contactno'],
    //                     'smsstatus' => 0,
    //                     'createddatetime' => FinanceModel::getServerDateTime()
    //                 ]);
    //         }
    //     } else {
    //         return 0;
    //     }

    //     return 1;
    // }

    public function statementofaccaddflag(Request $request)
    {
        $semid = $request->get('semid');
        $syid = $request->get('syid');
        $levelid = $request->get('levelid');
        $sectionid = $request->get('sectionid');
        $courseid = $request->get('courseid');
        $selectedmonth = $request->get('monthid');

        db::table('soa_smsnotification')
            ->insert([
                'syid' => $syid,
                'semid' => $semid,
                'levelid' => $levelid,
                'sectionid' => $sectionid,
                'courseid' => $courseid,
                'monthid' => $selectedmonth,
                'datesent' => FinanceModel::getServerDateTime(),
                'createddatetime' => FinanceModel::getServerDateTime(),
                'createdby' => auth()->user()->id
            ]);
    }

    public function statementofacctgetmonthrange(Request $request)
    {
        $ranges = db::table('month_range_setup')
            ->select('month_range_setup.*', 'academicprogram.progname', 'semester.semester')
            ->join('academicprogram', 'month_range_setup.acadprogid', '=', 'academicprogram.id')
            ->leftJoin('semester', 'month_range_setup.semid', '=', 'semester.id')
            ->where('month_range_setup.deleted', 0)
            ->orderBy('academicprogram.progname')
            ->orderBy('semester.semester')
            ->get();

        return $ranges;
    }

    public function updateMonthRange(Request $request)
    {
        DB::table('month_range_setup')
            ->where('id', $request->id)
            ->update([
                'sdate' => $request->start_month,
                'edate' => $request->end_month,
                'updateddatetime' => FinanceModel::getServerDateTime(),
                'updatedby' => auth()->user()->id
            ]);

        return 1;
    }

    public function addmonthrange(Request $request)
    {
        $s_month = $request->get('start_month');
        $e_month = $request->get('end_month');
        $acadprogid = $request->get('progid');
        $semid = $request->get('semid');

        // Build the exists query
        $existQuery = db::table('month_range_setup')
            ->where('sdate', $s_month)
            ->where('edate', $e_month)
            ->where('acadprogid', $acadprogid)
            ->where('deleted', 0);

        // Add semester condition if provided
        if ($semid) {
            $existQuery->where('semid', $semid);
        } else {
            $existQuery->whereNull('semid');
        }

        $exist = $existQuery->first();

        if ($exist) {
            return 0;
        }

        // Prepare insert data
        $insertData = [
            'sdate' => $s_month,
            'edate' => $e_month,
            'acadprogid' => $acadprogid,
            'isactive' => 1,
            'createddatetime' => FinanceModel::getServerDateTime(),
            'createdby' => auth()->user()->id
        ];

        // Add semester if provided
        if ($semid) {
            $insertData['semid'] = $semid;
        }

        return db::table('month_range_setup')
            ->insertGetId($insertData);
    }

    public function statementofacctaddmonthrange(Request $request)
    {

        db::table('monthsetup')->delete();

        $s_month = $request->get('start_month');
        $e_month = $request->get('end_month');

        // Array of month names
        $months = [
            1 => 'JANUARY',
            2 => 'FEBRUARY',
            3 => 'MARCH',
            4 => 'APRIL',
            5 => 'MAY',
            6 => 'JUNE',
            7 => 'JULY',
            8 => 'AUGUST',
            9 => 'SEPTEMBER',
            10 => 'OCTOBER',
            11 => 'NOVEMBER',
            12 => 'DECEMBER'
        ];

        // Array to hold the month data
        $monthsarray = [];

        // If start_month is less than or equal to end_month (e.g., July to December)
        if ($s_month <= $e_month) {
            for ($month = $s_month; $month <= $e_month; $month++) {
                $monthsarray[] = [
                    'description' => $months[$month],
                    'monthid' => $month
                ];
            }
        }
        // If start_month is greater than end_month (e.g., July to May)
        else {
            // Loop from start_month to December
            for ($month = $s_month; $month <= 12; $month++) {
                $monthsarray[] = [
                    'description' => $months[$month],
                    'monthid' => $month
                ];
            }

            // Loop from January to end_month
            for ($month = 1; $month <= $e_month; $month++) {
                $monthsarray[] = [
                    'description' => $months[$month],
                    'monthid' => $month
                ];
            }
        }

        // Store the data in the database
        foreach ($monthsarray as $monthdata) {
            db::table('monthsetup')
                ->insert([
                    'description' => $monthdata['description'],
                    'monthid' => $monthdata['monthid']
                ]);
        }

        $ifexist = db::table('month_range_setup')
            ->where('sdate', $s_month)
            ->where('edate', $e_month)
            ->where('deleted', 0)
            ->first();

        if ($ifexist) {
            return 0;
        } else {

            $insertdata = db::table('month_range_setup')
                ->insert([
                    'sdate' => $s_month,
                    'edate' => $e_month,
                    'isactive' => 1,
                    'createddatetime' => FinanceModel::getServerDateTime(),
                    'createdby' => auth()->user()->id
                ]);




            return 1;
        }
    }

    public function statementofacctselectedmonthrange(Request $request)
    {
        // $id = $request->get('rangeid');

        // Get the selected month range setup
        $monthsetupsrange = DB::table('month_range_setup')
            ->where('isactive', 1)
            ->where('deleted', 0)
            ->first();

        if ($monthsetupsrange) {

            // Get the list of all months
            $monthslist = DB::table('monthsetup')->orderBy('monthid', 'asc')->get();

            $startMonth = $monthsetupsrange->sdate;
            $endMonth = $monthsetupsrange->edate;

            // Filter months based on whether the range wraps around the year
            if ($startMonth <= $endMonth) {
                // If the range does not wrap around (e.g., January to May)
                $filteredMonths = $monthslist->filter(function ($item) use ($startMonth, $endMonth) {
                    return $item->monthid >= $startMonth && $item->monthid <= $endMonth;
                });
            } else {
                $filteredMonths = $monthslist->filter(function ($item) use ($startMonth) {
                    return $item->monthid >= $startMonth;
                })
                    ->concat(
                        $monthslist->filter(function ($item) use ($endMonth) {
                            return $item->monthid <= $endMonth;
                        })
                    );
            }

            return $filteredMonths->values();
        }

    }


    public function statementofacctremovemonthrange(Request $request)
    {
        $id = $request->get('id');

        $iffound = db::table('month_range_setup')
            ->where('id', $id)
            ->where('deleted', 0)
            ->count();

        if ($iffound > 0) {
            $monthsetupsrange = db::table('month_range_setup')
                ->where('id', $id)
                ->update([
                    'deleted' => 1,
                    'isactive' => 0,
                    'deleteddatetime' => FinanceModel::getServerDateTime(),
                    'deletedby' => auth()->user()->id
                ]);

            db::table('monthsetup')->delete();
        }

        return 1;
    }

}

class MYPDFStatementOfAccount extends TCPDF
{

    //Page header
    public function Header()
    {
        try {
            $schoollogo = DB::table('schoolinfo')->first();

            if ($schoollogo && !empty($schoollogo->picurl)) {
                $picurl = explode('?', $schoollogo->picurl)[0]; // Remove query string if exists
                $image_file = public_path($picurl);

                // Check if file exists before trying to use it
                if (file_exists($image_file)) {
                    $this->Image('@' . file_get_contents($image_file), 15, 9, 17, 17);
                } else {
                    // Fallback to default logo or just skip the image
                    // Log::warning("School logo not found at: ".$image_file);
                }
            }

            // School information text
            if ($schoollogo) {
                $this->writeHTMLCell(false, 50, 40, 10, '<span style="font-weight: bold">' . $schoollogo->schoolname . '</span>', false, false, false, true, 'L', true);
                $this->writeHTMLCell(false, 50, 40, 15, '<span style="font-weight: bold; font-size: 10px;">' . $schoollogo->address . '</span>', false, false, false, true, 'L', true);
            }

            $this->writeHTMLCell(false, 50, 40, 20, 'Statement of Account', false, false, false, true, 'L', true);

        } catch (Exception $e) {
            // Log::error("PDF Header Error: ".$e->getMessage());
            // Continue with the rest of the PDF generation even if header fails
        }
    }

    // Page footer
    public function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        $this->Cell(0, 10, date('m/d/Y'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}


class MYPDFStatementOfAccountccsa extends TCPDF
{

    //Page header
    public function Header()
    {
        $schoollogo = DB::table('schoolinfo')->first();
        $picurl = explode('?', $schoollogo->picurl);
        $image_file = public_path() . '/' . $picurl[0];
        $extension = explode('.', $schoollogo->picurl);
        $this->Image('@' . file_get_contents($image_file), 15, 9, 17, 17);

        $schoolname = $this->writeHTMLCell(false, 50, 7, 10, '<span style="font-weight: bold; text-align: center; font-size: 14px">' . $schoollogo->schoolname . '</span>', false, false, false, $reseth = true, $align = 'L', $autopadding = true);
        $schooladdress = $this->writeHTMLCell(false, 50, 7, 15, '<span style="font-weight: bold; font-size: 10px; text-align: center;">' . $schoollogo->address . '</span>', false, false, false, $reseth = true, $align = 'L', $autopadding = true);
        $title = $this->writeHTMLCell(false, 50, 7, 18, '<span style="font-size: 13px; text-align: center;">Statement of Account</span>', false, false, false, $reseth = true, $align = 'L', $autopadding = true);

        $image_fileright = public_path() . '/assets/images/ccsa/ccsa.png';
        $this->Image('@' . file_get_contents($image_fileright), 171, 9, 23, 17);
        // $title = $this->writeHTMLCell(false, 50, 40, 20, 'Statement of Account', false, false, false, $reseth=true, $align='L', $autopadding=true);
        // Ln();
    }

    // Page footer
    public function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        $this->Cell(0, 10, date('m/d/Y'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}


class MYPDFStatementOfAccountHccsi extends TCPDF
{

    //Page header
    public function Header()
    {
        $this->SetX(5);
        $this->Cell(10, 0, date('m/d/Y'), 0, false, 'L', 0, '', 0, false, 'T', 'M');

        $this->Cell(0, 0, 'Page ' . $this->getAliasNumPage(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
        // $schoolname = $this->writeHTMLCell(false, 50, 40, 10, '<span style="font-weight: bold">'.$schoollogo->schoolname.'</span>', false, false, false, $reseth=true, $align='L', $autopadding=true);
        // $schooladdress = $this->writeHTMLCell(false, 50, 40, 15, '<span style="font-weight: bold; font-size: 10px;">'.$schoollogo->address.'</span>', false, false, false, $reseth=true, $align='L', $autopadding=true);
        // $title = $this->writeHTMLCell(false, 50, 40, 20, 'Statement of Account', false, false, false, $reseth=true, $align='L', $autopadding=true);
        // Ln();
    }

    // // Page footer
    // public function Footer() {
    //     // Position at 15 mm from bottom
    //     $this->SetY(-15);
    //     // Set font
    //     $this->SetFont('helvetica', 'I', 8);
    //     // Page number
    //     $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().' of '.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    //     $this->Cell(0, 10, date('m/d/Y'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    // }

    // Payment scanning and distribution helper methods
}
