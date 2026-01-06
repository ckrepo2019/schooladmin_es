<?php
namespace App\Http\Controllers\Financev2Controller\Student;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use PDF;

class StudentAccountV2Controller extends Controller
{

    public function student_accounts()
    {
        return view('finance_v2.pages.student.student-account');
    }

    /**
     * Get student statuses from database
     */
    public function getStudentStatuses()
    {
        $statuses = DB::table('studentstatus')
            ->select('id', 'description')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $statuses
        ]);
    }

    public function getFilterDetails()
    {
        $schoolyears = DB::table('sy')
            ->select('id', 'sydesc', 'sdate', 'edate', 'isactive')
            ->whereIn('isactive', [0, 1])
            ->orderBy('sydesc', 'desc')
            ->get();

        $semesters = DB::table('semester')
            ->select('id', 'semester', 'isactive')
            ->when(Schema::hasColumn('semester', 'deleted'), function ($q) {
                $q->where('deleted', 0);
            })
            ->orderBy('semester')
            ->get();

        $academicPrograms = DB::table('academicprogram')
            ->orderBy('id')
            ->get();

        // Grade levels grouped by academic program
        $gradelevels = DB::table('gradelevel')
            ->select('id', 'levelname', 'acadprogid')
            ->where('deleted', 0)
            ->orderBy('levelname')
            ->get()
            ->groupBy('acadprogid');

        // College colleges (parent)
        $colleges = DB::table('college_colleges')
            ->select('id', 'collegeDesc', 'collegeabrv', 'acadprogid')
            ->when(Schema::hasColumn('college_colleges', 'deleted'), function ($q) {
                $q->where('deleted', 0);
            })
            ->orderBy('collegeDesc')
            ->get();

        // College courses grouped by college
        $courses = DB::table('college_courses')
            ->select('id', 'collegeid', 'courseDesc', 'courseChairman', 'courseabrv')
            ->where('deleted', 0)
            ->orderBy('courseDesc')
            ->get()
            ->groupBy('collegeid');

        // Higher education programs (parent)
        $higherEducations = DB::table('higher_educations')
            ->select('id', 'collegeDesc', 'collegeabrv', 'createdby')
            ->where('deleted', 0)
            ->orderBy('collegeDesc')
            ->get();

        // Higher education degrees grouped by higher education id
        $higherEdDegrees = DB::table('higher_ed_degree')
            ->select('id', 'degreeid', 'coursedesc', 'courseabrv', 'coursechairman')
            ->where('deleted', 0)
            ->where('cisactive', 1)
            ->orderBy('coursedesc')
            ->get()
            ->groupBy('degreeid');

        // SH strands grouped by track
        $strands = DB::table('sh_strand')
            ->select('id', 'strandname', 'strandcode', 'trackid', 'active')
            ->where('deleted', 0)
            ->orderBy('strandname')
            ->get()
            ->groupBy('trackid');

        // Grade sections grouped by level
        $gradeSections = DB::table('sections')
            ->select('id', 'sectionname', DB::raw('levelid as levelID'))
            ->where('deleted', 0)
            ->where('sectactive', 1)
            ->orderBy('sectionname')
            ->get()
            ->groupBy('levelID');

        // College sections grouped by college and course
        $collegeSections = DB::table('college_sections')
            ->select(
                'id',
                DB::raw('sectionDesc as sectionname'),
                DB::raw('yearID as levelID'),
                'courseID',
                'collegeID'
            )
            ->where('deleted', 0)
            ->orderBy('sectionDesc')
            ->get()
            ->groupBy(['collegeID', 'courseID']);

        // Regular subjects grouped by level
        $subjects = DB::table('subjects')
            ->select('id', 'subjdesc', 'subjcode', 'acadprogid', 'levelid')
            ->where('deleted', 0)
            ->where('isactive', 1)
            ->orderBy('subjdesc')
            ->get()
            ->groupBy('levelid');

        // College subjects grouped by course
        $collegeSubjects = DB::table('college_subjects')
            ->select('id', 'subjCourse', 'subjUnit', 'subjCode', 'subjDesc')
            ->where('deleted', 0)
            ->orderBy('subjDesc')
            ->get()
            ->groupBy('subjCourse');

        // Higher education subjects grouped by course
        $higherEdSubjects = DB::table('higher_ed_subjects')
            ->select('id', 'subjcourse', 'subjcode', 'subjdesc', 'subjunit', 'lecunits', 'labunits')
            ->where('deleted', 0)
            ->orderBy('subjdesc')
            ->get()
            ->groupBy('subjcourse');

        $scholarshipsQuery = DB::table('scholarshipprog')
            ->select('id', 'program', 'abbreviation', 'fullamount', 'constant');

        if (Schema::hasColumn('scholarshipprog', 'deleted')) {
            $scholarshipsQuery->where('deleted', 0);
        }

        $scholarships = $scholarshipsQuery
            ->when(
                Schema::hasColumn('scholarshipprog', 'program'),
                function ($q) {
                    $q->orderBy('program');
                },
                function ($q) {
                    $q->orderBy('id');
                }
            )
            ->get();

        return response()->json([
            'schoolyears' => $schoolyears,
            'semesters' => $semesters,
            'academicPrograms' => $academicPrograms,
            'gradelevels' => $gradelevels, // grouped by acadprogid
            'colleges' => $colleges,
            'courses' => $courses, // grouped by collegeid
            'higher_educations' => $higherEducations,
            'higher_ed_degrees' => $higherEdDegrees, // grouped by degreeid
            'strands' => $strands, // grouped by trackid
            'sections' => [
                'grade_sections' => $gradeSections, // grouped by levelID
                'college_sections' => $collegeSections, // grouped by collegeID and courseID
            ],
            'subjects' => [
                'regular_subjects' => $subjects, // grouped by levelid
                'college_subjects' => $collegeSubjects, // grouped by subjCourse
                'higher_ed_subjects' => $higherEdSubjects, // grouped by subjcourse
            ],
            'scholarships' => $scholarships,
        ]);
    }

    // test 1
    // public function getStudentsForBookEntry(Request $request)
    // {
    //     try {
    //         $ids = $request->student_ids ?? [];
    //         if (empty($ids)) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'No student IDs provided.'
    //             ], 400);
    //         }

    //         $students = DB::table('studinfo as s')
    //             ->whereIn('s.id', $ids)
    //             ->where('s.deleted', 0)
    //             ->select(
    //                 's.id',
    //                 's.sid as student_no',
    //                 DB::raw("CONCAT(s.lastname, ', ', s.firstname, ' ', COALESCE(s.middlename,'')) as full_name"),
    //                 DB::raw("0 as tuition"),
    //                 DB::raw("0 as payment"),
    //                 DB::raw("0 as balance")
    //             )
    //             ->get();

    //         return response()->json([
    //             'success' => true,
    //             'data' => $students
    //         ], 200);

    //     } catch (\Throwable $e) {
    //         \Log::error('Error in getStudentsForBookEntry: '.$e->getMessage(), [
    //             'file' => $e->getFile(),
    //             'line' => $e->getLine()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Server error while fetching students.',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    private static function processCollegeStudentFees($students, $selectedschoolyear, $selectedsemester)
    {
        if ($students->isEmpty())
            return $students;

        $studentIds = $students->pluck('id')->toArray();
        $levelIds = $students->pluck('levelid')->unique()->toArray();

        $allReceivables = DB::table('tuitionheader')
            ->select(DB::raw('itemclassification.id as classid, itemclassification.description, amount, levelid, istuition, persubj, semid, courseid'))
            ->join('tuitiondetail', 'tuitionheader.id', '=', 'tuitiondetail.headerid')
            ->join('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
            ->where('syid', $selectedschoolyear)
            ->where('semid', $selectedsemester)
            ->whereIn('levelid', $levelIds)
            ->where('tuitionheader.deleted', 0)
            ->where('tuitiondetail.deleted', 0)
            ->where('itemclassification.deleted', 0)
            ->get()
            ->groupBy('levelid');

        $allStudentUnits = DB::table('college_loadsubject')
            ->join('college_classsched', 'college_loadsubject.schedid', '=', 'college_classsched.id')
            ->join('college_prospectus', 'college_classsched.subjectID', '=', 'college_prospectus.id')
            ->whereIn('college_loadsubject.studid', $studentIds)
            ->where('college_loadsubject.syid', $selectedschoolyear)
            ->where('college_loadsubject.semid', $selectedsemester)
            ->where('college_loadsubject.deleted', 0)
            ->select('college_prospectus.*', 'college_loadsubject.studid')
            ->distinct()
            ->get()
            ->groupBy('studid');

        $subjectIds = $allStudentUnits->flatten()->pluck('subjectID')->unique()->toArray();

        $assessmentUnits = DB::table('tuition_assessmentunit')
            ->select('subjid', 'assessmentunit')
            ->join('college_subjects', 'tuition_assessmentunit.subjid', '=', 'college_subjects.id')
            ->where('tuition_assessmentunit.deleted', 0)
            ->whereIn('college_subjects.id', $subjectIds)
            ->get()
            ->keyBy('subjid');

        $subjectCounts = DB::table('college_loadsubject')
            ->select('college_loadsubject.studid', DB::raw('COUNT(DISTINCT college_classsched.subjectID) as subject_count'))
            ->join('college_classsched', 'college_loadsubject.schedid', '=', 'college_classsched.id')
            ->whereIn('college_loadsubject.studid', $studentIds)
            ->where('college_loadsubject.syid', $selectedschoolyear)
            ->where('college_loadsubject.semid', $selectedsemester)
            ->where('college_loadsubject.deleted', 0)
            ->where('college_classsched.deleted', 0)
            ->groupBy('studid')
            ->get()
            ->keyBy('studid');

        foreach ($students as $student) {
            $receivables = $allReceivables->get($student->levelid, collect())->where('courseid', $student->courseid);
            $studentUnits = $allStudentUnits->get($student->id, collect());

            $totalUnits = 0;
            foreach ($studentUnits as $unit) {
                if ($assessmentUnits->has($unit->subjectID)) {
                    $totalUnits += 1.5;
                } else {
                    $totalUnits += $unit->lecunits + $unit->labunits;
                }
            }

            $feeSum = 0;
            foreach ($receivables as $fee) {
                $amount = $fee->amount ?? 0;
                if ($fee->istuition == 1) {
                    $feeSum += $amount * $totalUnits;
                } elseif ($fee->persubj == 1) {
                    $subjectCount = $subjectCounts->get($student->id)->subject_count ?? 0;
                    $feeSum += $amount * $subjectCount;
                } else {
                    $feeSum += $amount;
                }
            }

            $student->units = $totalUnits;
            $student->feetotal = $feeSum;
        }

        return $students;
    }

    private static function getSHSTuiFees($studid, $syid, $semid, $to, $progid, $levelarray)
    {
        $total = 0;
        $fees = DB::table('tuitionheader')
            ->select(DB::raw('itemclassification.id as classid, itemclassification.`description`, `amount`, levelid, istuition, persubj'))
            ->join('tuitiondetail', 'tuitionheader.id', '=', 'tuitiondetail.headerid')
            ->join('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
            ->where('syid', $syid)
            ->where(function ($q) use ($semid) {
                $schoolInfoRecord = DB::table('schoolinfo')->first();
                if ($schoolInfoRecord && $schoolInfoRecord->shssetup == 0) {
                    $q->where('semid', $semid);
                }
            })
            ->whereIn('levelid', $levelarray)
            ->where('tuitionheader.deleted', 0)
            ->where('tuitiondetail.deleted', 0)
            ->groupBy('classificationid', 'levelid', 'pschemeid')
            ->get();

        $studentLevel = DB::table('sh_enrolledstud')
            ->join('sections', 'sh_enrolledstud.sectionid', '=', 'sections.id')
            ->where('sh_enrolledstud.studid', $studid)
            ->where('sh_enrolledstud.syid', $syid)
            ->where(function ($q) use ($semid) {
                $schoolInfoRecord = DB::table('schoolinfo')->first();
                if ($schoolInfoRecord && $schoolInfoRecord->shssetup == 0) {
                    $q->where('semid', $semid);
                }
            })
            ->where('sh_enrolledstud.deleted', 0)
            ->first();

        if ($studentLevel) {
            $studentLevelId = $studentLevel->levelid;
            $levelFees = $fees->where('levelid', $studentLevelId);

            foreach ($levelFees as $fee) {
                if ($fee->istuition == 1) {
                    $total += $fee->amount; // SHS tuition not per unit
                } elseif ($fee->persubj == 1) {
                    $subjectCount = DB::table('sh_studsched')
                        ->join('sh_classsched', 'sh_studsched.schedid', '=', 'sh_classsched.id')
                        ->where('sh_studsched.studid', $studid)
                        ->where('sh_studsched.deleted', 0)
                        ->where('sh_classsched.deleted', 0)
                        ->where('sh_classsched.syid', $syid)
                        ->where('sh_classsched.semid', $semid)
                        ->count();
                    $total += $fee->amount * $subjectCount;
                } else {
                    $total += $fee->amount;
                }
            }
        }
        return $total;
    }

    private static function getRegTuiFees($studid, $syid, $semid, $to, $progid, $levelarray)
    {
        $total = 0;
        $fees = DB::table('tuitionheader')
            ->select(DB::raw('itemclassification.id as classid, itemclassification.description, amount, levelid, istuition, persubj'))
            ->join('tuitiondetail', 'tuitionheader.id', '=', 'tuitiondetail.headerid')
            ->join('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
            ->where('syid', $syid)
            ->whereIn('levelid', $levelarray)
            ->where('tuitionheader.deleted', 0)
            ->where('tuitiondetail.deleted', 0)
            ->groupBy('classificationid', 'tuitiondetail.id')
            ->get();

        $studentLevel = DB::table('enrolledstud')
            ->join('sections', 'enrolledstud.sectionid', '=', 'sections.id')
            ->where('enrolledstud.studid', $studid)
            ->where('enrolledstud.syid', $syid)
            ->where('enrolledstud.deleted', 0)
            ->first();

        if ($studentLevel) {
            $studentLevelId = $studentLevel->levelid;
            $levelFees = $fees->where('levelid', $studentLevelId);
            foreach ($levelFees as $fee) {
                $total += $fee->amount; // fixed amounts
            }
        }

        return $total;
    }

    // ---- STUDENT OLD ACCOUNTS (NON-FORWARDED) ----
    public static function getStudentOldAccountsData($studid)
    {
        // Fetch non-forwarded old accounts from old_student_accounts table
        $oldAccounts = DB::table('old_student_accounts as soa')
            ->leftJoin('itemclassification as ic', 'soa.classid', '=', 'ic.id')
            ->leftJoin('items as i', 'soa.itemid', '=', 'i.id')
            ->where('soa.stud_id', $studid)
            ->where('soa.deleted', 0)
            ->where('soa.isforwarded', 0)
            ->select(
                'soa.id',
                'soa.classid',
                'soa.itemid',
                'soa.balance',
                'soa.payables',
                'soa.payment as old_payment',
                'soa.particulars',
                'ic.description as class_description',
                'i.description as item_description'
            )
            ->get();

        // Get payments from chrngcashtrans matched by particulars
        // Group payments by particulars to match with old account records
        $paymentsByParticulars = DB::table('chrngcashtrans as cct')
            ->join('chrngtrans as ct', 'cct.transno', '=', 'ct.transno')
            ->select('cct.particulars', DB::raw('SUM(cct.amount) as total_paid'))
            ->where('cct.studid', $studid)
            ->where('ct.studid', $studid)
            ->where('cct.deleted', 0)
            ->where('ct.cancelled', 0)
            ->groupBy('cct.particulars')
            ->get()
            ->keyBy('particulars');

        // Log payment data for debugging
        \Log::info('Old Accounts - Payments fetched from chrngcashtrans', [
            'studid' => $studid,
            'payments_count' => $paymentsByParticulars->count(),
            'payments' => $paymentsByParticulars->toArray()
        ]);

        // Group by classid and calculate payments
        $grouped = [];
        foreach ($oldAccounts as $record) {
            $classid = $record->classid;
            $particulars = $record->particulars ?? $record->item_description ?? 'Old Account';

            // Match payment by particulars (trim whitespace for better matching)
            $paymentAmount = 0;
            $trimmedParticulars = trim($particulars);

            if (isset($paymentsByParticulars[$trimmedParticulars])) {
                $paymentAmount = (float) $paymentsByParticulars[$trimmedParticulars]->total_paid;
                \Log::info('Old Accounts - Payment matched', [
                    'particulars' => $trimmedParticulars,
                    'payment_amount' => $paymentAmount
                ]);
            } else {
                \Log::info('Old Accounts - No payment match found', [
                    'particulars' => $trimmedParticulars,
                    'available_keys' => $paymentsByParticulars->keys()->toArray()
                ]);
            }

            // Calculate balance and payment
            $originalPayables = (float) ($record->payables ?? 0);
            $storedPayment = (float) ($record->old_payment ?? 0);
            $storedBalance = (float) ($record->balance ?? 0);

            // Total payment includes both stored and matched payments
            $totalPayment = $storedPayment + $paymentAmount;

            // Calculate current balance
            // If we have original payables, calculate from that; otherwise use stored balance and subtract new payments
            if ($originalPayables > 0) {
                $currentBalance = $originalPayables - $totalPayment;
            } else {
                // Only stored balance available, subtract the new matched payment
                $currentBalance = $storedBalance - $paymentAmount;
            }

            if (!isset($grouped[$classid])) {
                $grouped[$classid] = [
                    'classid' => $classid,
                    'description' => $record->class_description ?? 'Old Accounts',
                    'amount' => 0,
                    'payment' => 0,
                    'balance' => 0,
                    'items' => []
                ];
            }

            // Determine the original amount to display
            $displayAmount = $originalPayables > 0 ? $originalPayables : $storedBalance;

            $grouped[$classid]['amount'] += $displayAmount;
            $grouped[$classid]['payment'] += $totalPayment;
            $grouped[$classid]['balance'] += $currentBalance;
            $grouped[$classid]['items'][] = [
                'itemid' => $record->itemid,
                'particulars' => $particulars,
                'amount' => $displayAmount,
                'payment' => $totalPayment,
                'balance' => $currentBalance,
            ];
        }

        return array_values($grouped);
    }

    // ---- FORWARDED OLD ACCOUNTS ----
    private static function getForwardedOldAccountsData($studid, $toSyid, $toSemid)
    {
        // Get TO school year and semester descriptions
        $toSy = DB::table('sy')->where('id', $toSyid)->first();
        $toSem = DB::table('semester')->where('id', $toSemid)->first();

        $toSyDesc = $toSy ? $toSy->sydesc : '';
        $toSemDesc = $toSem ? $toSem->semester : '';

        // Fetch forwarded old accounts that match the target SY/Sem
        $forwardedAccounts = DB::table('old_accounts_forwarding as oaf')
            ->leftJoin('old_student_accounts as soa', 'oaf.old_student_account_id', '=', 'soa.id')
            ->leftJoin('itemclassification as ic', 'oaf.classid', '=', 'ic.id')
            ->leftJoin('items as i', 'oaf.itemid', '=', 'i.id')
            ->where('oaf.stud_id', $studid)
            ->where('oaf.to_syid', $toSyid)
            ->where('oaf.to_semid', $toSemid)
            ->where('oaf.voided', 0)
            ->select(
                'oaf.id',
                'oaf.classid',
                'oaf.itemid',
                'oaf.balance',
                'soa.particulars',
                'ic.description as class_description',
                'i.description as item_description'
            )
            ->get();

        // // Group by classid
        // $grouped = [];
        // foreach ($forwardedAccounts as $record) {
        //     $classid = $record->classid;

        //     if (!isset($grouped[$classid])) {
        //         $grouped[$classid] = [
        //             'classid' => $classid,
        //             'particulars' => $record->class_description ?? 'Old Accounts (Forwarded)',
        //             'total_amount' => 0,
        //             'total_paid' => 0,
        //             'total_balance' => 0,
        //             'items' => []
        //         ];
        //     }

        //     $grouped[$classid]['total_amount'] += (float) $record->balance;
        //     $grouped[$classid]['total_balance'] += (float) $record->balance;

        //     // Build particulars with TO information
        //     $baseParticulars = $record->particulars ?? $record->item_description ?? 'Old Account Balance';
        //     $particularsWithTo = $baseParticulars . ' TO ' . $toSyDesc . ($toSemDesc ? ', ' . $toSemDesc : '');

        //     $grouped[$classid]['items'][] = [
        //         'particulars' => $particularsWithTo,
        //         'amount' => (float) $record->balance,
        //         'payment' => 0,
        //         'balance' => (float) $record->balance,
        //         'classid' => $classid,
        //         'itemid' => $record->itemid,
        //         'items' => [] // No nested items for old accounts
        //     ];
        // }

        // return array_values($grouped);
    }

    private static function getOldAcctCharges($studid, $syid, $semid, $progid)
    {
        // Sum balances from non-forwarded old accounts (old_student_accounts where isforwarded = 0)
        $nonForwardedBalance = DB::table('old_student_accounts')
            ->where('stud_id', $studid)
            ->where('deleted', 0)
            ->where('isforwarded', 0)
            ->sum('balance');

        // // Sum balances from forwarded old accounts (old_accounts_forwarding to current SY/Sem)
        // $forwardedBalance = DB::table('old_accounts_forwarding')
        //     ->where('stud_id', $studid)
        //     ->where('to_syid', $syid)
        //     ->where('to_semid', $semid)
        //     ->where('voided', 0)
        //     ->sum('balance');

        // return (float) ($nonForwardedBalance + $forwardedBalance);
        return (float) ($nonForwardedBalance);
    }

    // ---- BOOK ENTRY CHARGES ----
    private static function getBookentriesCharges($studid, $syid, $semid, $progid)
    {
        $books = DB::table('bookentries')
            ->selectRaw('SUM(amount) as total_amount')
            ->where('bookentries.deleted', 0)
            ->where('syid', $syid)
            ->where('studid', $studid)
            ->where('bestatus', 'APPROVED')
            ->when($progid == 6, function ($q) use ($semid) {
                $q->where('semid', $semid);
            })
            ->when($progid == 5, function ($q) use ($semid) {
                $q->where(function ($q) use ($semid) {
                    $schoolInfoRecord = DB::table('schoolinfo')->first();
                    if ($schoolInfoRecord && $schoolInfoRecord->shssetup == 0) {
                        $q->where('semid', $semid);
                    }
                });
            })
            ->first();

        return (float) ($books->total_amount ?? 0);
    }

    // ---- ADJUSTMENT (DEBIT/CREDIT) ----
    private static function getAdjustmentCharges($studid, $syid, $semid, $progid, $levelarray, $purpose = 'charges')
    {
        $adjustments = DB::table('adjustmentdetails')
            ->selectRaw('SUM(amount) as total_amount')
            ->join('adjustments', 'adjustmentdetails.headerid', '=', 'adjustments.id')
            ->where('adjustmentdetails.studid', $studid)
            ->where('adjustmentdetails.deleted', '0')
            ->where('adjustments.deleted', '0')
            ->where('adjustments.syid', $syid)
            ->whereIn('adjustments.levelid', $levelarray)
            ->when($progid == 6, function ($q) use ($semid) {
                $q->where('adjustments.semid', $semid);
            })
            ->where(function ($q) use ($purpose) {
                if ($purpose === 'charges') {
                    $q->where('adjustments.isdebit', 1);
                } else {
                    $q->where('adjustments.iscredit', 1);
                }
            })
            ->when($progid == 5, function ($q) use ($semid) {
                $q->where(function ($q) use ($semid) {
                    $schoolInfoRecord = DB::table('schoolinfo')->first();
                    if ($schoolInfoRecord && $schoolInfoRecord->shssetup == 0) {
                        $q->where('adjustments.semid', $semid);
                    }
                });
            })
            ->first();

        return (float) ($adjustments->total_amount ?? 0);
    }

    // ---- LAB FEES (COLLEGE ONLY) ----
    private static function getLabFees($studentid, $syid, $semid)
    {
        // Get student's enrolled subjects from college_loadsubject (subjectID contains college_prospectus.id)
        $studentSubjectIDs = DB::table('college_loadsubject')
            ->where('studid', $studentid)
            ->where('syid', $syid)
            ->where('semid', $semid)
            ->where('deleted', 0)
            ->pluck('subjectID')
            ->unique()
            ->toArray();

        if (empty($studentSubjectIDs)) {
            return [
                'total' => 0.0,
                'items' => []
            ];
        }

        // Query labfees directly where subjid matches subjectID from college_loadsubject
        $labs = DB::table('labfees as lf')
            ->leftJoin('college_prospectus as cp', 'lf.subjid', '=', 'cp.id')
            ->select([
                'lf.id',
                'cp.subjCode as subjcode',
                'cp.subjDesc as subjdesc',
                'lf.amount',
                'lf.subjid'
            ])
            ->where('lf.deleted', 0)
            ->where('lf.syid', $syid)
            ->where('lf.semid', $semid)
            ->whereIn('lf.subjid', $studentSubjectIDs)
            ->orderBy('cp.subjCode')
            ->get();

        if ($labs->isEmpty()) {
            return [
                'total' => 0.0,
                'items' => []
            ];
        }

        $labfees = $labs->keyBy('subjid');

        $labtotal = 0.0;
        $labFeeItems = [];
        foreach ($labs as $labfee) {
            $labtotal += (float) $labfee->amount;

            // Get laboratory fee items from laboratory_fee_items table
            $feeItems = DB::table('laboratory_fee_items as lfi')
                ->select([
                    'lfi.id',
                    'lfi.laboratory_fee_id',
                    'lfi.item_id',
                    'lfi.amount',
                    'items.description as item_description'
                ])
                ->leftJoin('items', 'lfi.item_id', '=', 'items.id')
                ->where('lfi.laboratory_fee_id', $labfee->id)
                ->where('lfi.deleted', 0)
                ->get();

            $labFeeItems[] = [
                'id' => $labfee->id,
                'subjid' => $labfee->subjid,
                'subjcode' => $labfee->subjcode,
                'subjdesc' => $labfee->subjdesc,
                'amount' => (float) $labfee->amount,
                'fee_items' => $feeItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'item_id' => $item->item_id,
                        'item_description' => $item->item_description,
                        'amount' => (float) $item->amount
                    ];
                })->toArray()
            ];
        }

        return [
            'total' => (float) $labtotal,
            'items' => $labFeeItems
        ];
    }


    /**
     * --- MAIN: Get account details + tuition_fee only (no payments/balances) ---
     */
    public function getStudentAccounts(Request $request)
    {
        // ---- inputs ----
        $readId = function (string $key) use ($request) {
            $raw = $request->input("$key.value", $request->input($key));
            if (is_array($raw)) {
                $raw = $raw['value'] ?? null;
            }
            if ($raw === '' || $raw === null)
                return null;
            return is_numeric($raw) ? (int) $raw : null;
        };

        $filter = [
            'school_year_id' => $readId('schoolYear'),
            'semester_id' => $readId('semester'),
            'program_id' => $readId('program'),
            'college_id' => $readId('college'),
            'level_id' => $readId('level'),
            'course_id' => $readId('course'),
            'strand_id' => $readId('strand'),
            'section_id' => $readId('section'),
            'scholarship_id' => $readId('scholarship'),
        ];

        $hasSY = !is_null($filter['school_year_id']);
        $hasSem = !is_null($filter['semester_id']);

        // pagination
        $page = max(1, (int) $request->input('page', 1));     // starts at 1
        $perPage = max(1, (int) $request->input('per_page', 10)); // default 10 per page
        $offset = ($page - 1) * $perPage;

        $hasSpecificFilters =
            !is_null($filter['level_id']) ||
            !is_null($filter['course_id']) ||
            !is_null($filter['strand_id']) ||
            !is_null($filter['section_id']);

        // pre-scope by scholarship
        $scopedIds = null;
        if (!is_null($filter['scholarship_id'])) {
            $scopedIds = DB::table('scholarshipstud')
                ->where('scholarid', $filter['scholarship_id'])
                ->pluck('studid');
            if ($scopedIds->isEmpty()) {
                return response()->json([
                    'filters' => $filter,
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'pages' => 0,
                    ],
                    'students' => [],
                ]);
            }
        }

        // if college chosen and no course, get all course ids in that college
        $courseIdsForCollege = null;
        if (!is_null($filter['college_id']) && is_null($filter['course_id'])) {
            $courseIdsForCollege = DB::table('college_courses')
                ->where('collegeid', $filter['college_id'])
                ->pluck('id');
        }

        // enrollment filter by syid/semid (enrolledstud has no semid)
        $matchedBySySem = null;
        if ($hasSY || $hasSem) {
            $applyEnrollFilters = function ($q, string $table) use ($hasSY, $hasSem, $filter) {
                if ($hasSY && Schema::hasColumn($table, 'syid')) {
                    $q->where("$table.syid", $filter['school_year_id']);
                }
                // Only apply semester filter to tables that use semesters (SHS and College)
                // Exclude: enrolledstud (grade school) and tesda_enrolledstud (TESDA)
                if (!in_array($table, ['enrolledstud', 'tesda_enrolledstud']) && $hasSem && Schema::hasColumn($table, 'semid')) {
                    $q->where("$table.semid", $filter['semester_id']);
                }
                return $q;
            };

            $collectIds = collect();

            if (Schema::hasTable('enrolledstud')) {
                $q = DB::table('enrolledstud')->select('studid');
                $q = $applyEnrollFilters($q, 'enrolledstud');
                $collectIds = $collectIds->merge($q->pluck('studid'));
            }
            if (Schema::hasTable('sh_enrolledstud')) {
                $q = DB::table('sh_enrolledstud')->select('studid');
                $q = $applyEnrollFilters($q, 'sh_enrolledstud');
                $collectIds = $collectIds->merge($q->pluck('studid'));
            }
            if (Schema::hasTable('college_enrolledstud')) {
                $q = DB::table('college_enrolledstud')->select('studid');
                $q = $applyEnrollFilters($q, 'college_enrolledstud');
                $collectIds = $collectIds->merge($q->pluck('studid'));
            }
            if (Schema::hasTable('tesda_enrolledstud')) {
                $q = DB::table('tesda_enrolledstud')->select('studid');
                $q = $applyEnrollFilters($q, 'tesda_enrolledstud');
                $collectIds = $collectIds->merge($q->pluck('studid'));
            }

            $collectIds = $collectIds->filter()->unique()->values();
            if (($hasSY || $hasSem) && $collectIds->isEmpty()) {
                return response()->json([
                    'filters' => $filter,
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'pages' => 0,
                    ],
                    'students' => [],
                ]);
            }
            $matchedBySySem = $collectIds;
        }

        // base query builder (to be cloned for gender-specific queries)
        $base = DB::table('studinfo as si')
            ->leftJoin('gradelevel as gl', 'gl.id', '=', 'si.levelid')
            ->leftJoin('college_courses as cc', 'cc.id', '=', 'si.courseid')
            ->leftJoin('tesda_courses as tc', 'tc.id', '=', 'si.courseid')
            ->leftJoin('college_sections as csec', 'csec.id', '=', 'si.sectionid')
            ->leftJoin('sections as sec', 'sec.id', '=', 'si.sectionid')
            ->leftJoin('sh_strand as shs', 'shs.id', '=', 'si.strandid')
            ->select([
                'si.id',
                'si.sid',
                DB::raw("TRIM(CONCAT(COALESCE(si.lastname,''), ', ', COALESCE(si.firstname,''))) as fullname"),
                'si.gender',
                'si.levelid',
                'si.sectionid',
                'si.strandid',
                'si.courseid',
                'gl.levelname as academic_level',
                'gl.acadprogid as acadprog_id',
                DB::raw("CASE 
                                WHEN gl.acadprogid = 6 THEN cc.courseDesc
                                WHEN gl.acadprogid = 7 THEN tc.course_name
                                ELSE NULL
                            END as course_name"),
                DB::raw("CASE 
                                WHEN gl.acadprogid = 6 THEN csec.sectionDesc
                                ELSE sec.sectionname
                            END as section_name"),
                'shs.strandname',
            ]);

        if (Schema::hasColumn('studinfo', 'deleted')) {
            $base->where('si.deleted', 0);
        }
        if (!is_null($scopedIds)) {
            $base->whereIn('si.id', $scopedIds);
        }
        if (!is_null($filter['program_id'])) {
            $base->where('gl.acadprogid', $filter['program_id']);
        }
        if (!is_null($filter['college_id']) && is_null($filter['course_id']) && $courseIdsForCollege && $courseIdsForCollege->count()) {
            $base->whereIn('si.courseid', $courseIdsForCollege);
        }
        if ($hasSpecificFilters) {
            if (!is_null($filter['level_id']))
                $base->where('si.levelid', $filter['level_id']);
            if (!is_null($filter['course_id']))
                $base->where('si.courseid', $filter['course_id']);
            if (!is_null($filter['strand_id']))
                $base->where('si.strandid', $filter['strand_id']);
            if (!is_null($filter['section_id']))
                $base->where('si.sectionid', $filter['section_id']);
        }
        if (!is_null($matchedBySySem)) {
            $base->whereIn('si.id', $matchedBySySem);
        }

        // Get total count
        $totalCount = (clone $base)->count();

        // Fetch students with pagination
        // Order by enrollment status first (enrolled students first), then by name
        $baseQuery = (clone $base);
        if (!is_null($matchedBySySem) && $matchedBySySem->isNotEmpty()) {
            $enrolledIdsArray = $matchedBySySem->toArray();
            $placeholders = implode(',', array_fill(0, count($enrolledIdsArray), '?'));
            $students = $baseQuery->orderByRaw("CASE WHEN si.id IN ({$placeholders}) THEN 0 ELSE 1 END", $enrolledIdsArray)
                ->orderBy('si.lastname')->orderBy('si.firstname')
                ->skip($offset)->take($perPage)->get();
        } else {
            $students = $baseQuery->orderBy('si.lastname')->orderBy('si.firstname')
                ->skip($offset)->take($perPage)->get();
        }

        // if empty
        if ($students->isEmpty()) {
            return response()->json([
                'filters' => $filter,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalCount,
                    'pages' => (int) ceil($totalCount / max(1, $perPage)),
                ],
                'students' => [],
            ]);
        }

        // helper: add status, tuition & charges to a collection
        $processCollection = function ($students) use ($hasSY, $hasSem, $filter) {
            if ($students->isEmpty())
                return $students;

            // buckets by acadprog
            $bucket = ['basic' => [], 'shs' => [], 'college' => [], 'tesda' => []];
            foreach ($students as $s) {
                $ap = (int) ($s->acadprog_id ?? 0);
                if (in_array($ap, [2, 3, 4], true))
                    $bucket['basic'][] = $s->id;
                elseif ($ap === 5)
                    $bucket['shs'][] = $s->id;
                elseif ($ap === 6)
                    $bucket['college'][] = $s->id;
                elseif ($ap === 7)
                    $bucket['tesda'][] = $s->id;
            }

            $statusById = [];
            $applySY = function ($q, string $table) use ($hasSY, $filter) {
                if ($hasSY && Schema::hasColumn($table, 'syid'))
                    $q->where('syid', $filter['school_year_id']);
                return $q;
            };
            $applySem = function ($q, string $table) use ($hasSem, $filter) {
                if ($hasSem && Schema::hasColumn($table, 'semid'))
                    $q->where('semid', $filter['semester_id']);
                return $q;
            };

            if (!empty($bucket['basic'])) {
                $tbl = 'enrolledstud';
                $q1 = DB::table($tbl)->select('studid as id', 'studstatus')->whereIn('studid', $bucket['basic']);
                $q1 = $applySY($q1, $tbl);
                foreach ($q1->get() as $r)
                    $statusById[$r->id] = (int) $r->studstatus;
            }
            if (!empty($bucket['shs'])) {
                $tbl = 'sh_enrolledstud';
                $q1 = DB::table($tbl)->select('studid as id', 'studstatus')->whereIn('studid', $bucket['shs']);
                $q1 = $applySY($q1, $tbl);
                $q1 = $applySem($q1, $tbl);
                foreach ($q1->get() as $r)
                    $statusById[$r->id] = (int) $r->studstatus;
            }
            if (!empty($bucket['college'])) {
                $tbl = 'college_enrolledstud';
                $q1 = DB::table($tbl)->select('studid as id', 'studstatus')->whereIn('studid', $bucket['college']);
                $q1 = $applySY($q1, $tbl);
                $q1 = $applySem($q1, $tbl);
                foreach ($q1->get() as $r)
                    $statusById[$r->id] = (int) $r->studstatus;
            }
            if (!empty($bucket['tesda'])) {
                $tbl = 'tesda_enrolledstud';
                $q1 = DB::table($tbl)->select('studid as id', 'studstatus')->whereIn('studid', $bucket['tesda']);
                $q1 = $applySY($q1, $tbl);
                $q1 = $applySem($q1, $tbl);
                foreach ($q1->get() as $r)
                    $statusById[$r->id] = (int) $r->studstatus;
            }

            $statusDesc = DB::table('studentstatus')->pluck('description', 'id');

            // attach status
            $students->transform(function ($s) use ($statusById, $statusDesc) {
                $statusId = $statusById[$s->id] ?? null;
                $s->studstatus_id = $statusId;
                $s->studstatus_desc = $statusId ? ($statusDesc[$statusId] ?? null) : null;
                return $s;
            });

            // tuition per program
            $sy = $filter['school_year_id'] ?? 0;
            $sem = $filter['semester_id'] ?? 0;

            if ($hasSY && $hasSem) {
                $collegeSubset = $students->filter(fn($s) => (int) $s->acadprog_id === 6);
                if ($collegeSubset->isNotEmpty()) {
                    self::processCollegeStudentFees($collegeSubset, $sy, $sem);
                }
            }

            $students->transform(function ($s) use ($sy, $sem, $hasSY, $hasSem) {
                $ap = (int) ($s->acadprog_id ?? 0);
                $levelArray = array_filter([(int) ($s->levelid ?? 0)]);

                if (!$hasSY) {
                    $s->tuition_fee = 0.0;
                } else if ($ap === 6) {
                    $s->tuition_fee = round((float) ($s->feetotal ?? 0), 2);
                } else if ($ap === 5) {
                    $s->tuition_fee = round((float) self::getSHSTuiFees($s->id, $sy, $sem, null, $ap, $levelArray), 2);
                } else if (in_array($ap, [2, 3, 4], true)) {
                    $s->tuition_fee = round((float) self::getRegTuiFees($s->id, $sy, $sem, null, $ap, $levelArray), 2);
                } else {
                    $s->tuition_fee = 0.0;
                }

                return $s;
            });

            // charges + totals (whole SY/Sem)
            $students->transform(function ($s) use ($hasSY, $hasSem, $filter) {
                $sy = $filter['school_year_id'] ?? 0;
                $sem = $filter['semester_id'] ?? 0;
                $ap = (int) ($s->acadprog_id ?? 0);
                $levelArray = array_filter([(int) ($s->levelid ?? 0)]);

                $s->oldacc_charges = round((float) self::getOldAcctCharges($s->id, $sy, $sem, $ap), 2);
                $s->book_entry_charges = round((float) self::getBookentriesCharges($s->id, $sy, $sem, $ap), 2);
                $s->adjustment_charges = round((float) self::getAdjustmentCharges($s->id, $sy, $sem, $ap, $levelArray, 'charges'), 2);
                $s->credit_adjustment_charges = round((float) self::getAdjustmentCharges($s->id, $sy, $sem, $ap, $levelArray, 'credits'), 2);

                $s->lab_fee = 0.0;
                $s->lab_fee_items = [];
                if ($ap === 6 && $hasSY && $hasSem) {
                    $labFeeData = self::getLabFees($s->id, $sy, $sem);
                    $s->lab_fee = round((float) $labFeeData['total'], 2);
                    $s->lab_fee_items = $labFeeData['items'];
                }

                $s->total_fees = round(
                    (float) $s->tuition_fee
                    + (float) $s->lab_fee
                    + (float) $s->adjustment_charges
                    + (float) $s->book_entry_charges
                    + (float) $s->oldacc_charges
                    + (float) $s->credit_adjustment_charges
                    ,
                    2
                );

                return $s;
            });

            return $students;
        };

        // process students collection
        $students = $processCollection($students);

        return response()->json([
            'filters' => $filter,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'pages' => (int) ceil($totalCount / max(1, $perPage)),
            ],
            'students' => $students,
        ]);
    }
    /**
     * Get filtered and searchable student list
     */
    public function getFilteredStudents(Request $request)
    {
        // Get filter parameters
        $schoolYear = $request->input('school_year');
        $semester = $request->input('semester');
        $academicProgram = $request->input('academic_program');
        $academicLevel = $request->input('academic_level');
        $college = $request->input('college');
        $course = $request->input('course');
        $strand = $request->input('strand');
        $section = $request->input('section');
        $scholarship = $request->input('scholarship');
        $status = $request->input('status');

        // Search parameter
        $search = $request->input('search', '');

        // Pagination parameters
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));

        // Active term for feesid fallback (gradeschool has no sem)
        $activeSY = DB::table('sy')->where('isactive', 1)->orderByDesc('sydesc')->first();
        $activeSem = DB::table('semester')->where('isactive', 1)->orderByDesc('id')->first();
        $activeSyId = $activeSY->id ?? null;
        $activeSemId = $activeSem->id ?? null;

        try {
            // Get enrolled student IDs first if school year is provided
            // Note: semester filter is applied internally only to programs that use semesters (SHS, College)
            $enrolledStudentIds = collect();
            if ($schoolYear) {
                $enrolledStudentIds = $this->getEnrolledStudentIds($schoolYear, $semester, $academicProgram);
            }

            // Base query
            $query = DB::table('studinfo as si')
                ->leftJoin('gradelevel as gl', function ($join) {
                    $join->on('gl.id', '=', 'si.levelid')
                        ->where('gl.deleted', '=', 0);
                })
                ->leftJoin('sections as sec', function ($join) {
                    $join->on('sec.id', '=', 'si.sectionid')
                        ->where('sec.deleted', '=', 0);
                })
                ->leftJoin('college_sections as csec', function ($join) {
                    $join->on('csec.id', '=', 'si.sectionid')
                        ->where('csec.deleted', '=', 0);
                })
                ->leftJoin('college_courses as cc', function ($join) {
                    $join->on('cc.id', '=', 'si.courseid')
                        ->where('cc.deleted', '=', 0);
                })
                ->leftJoin('tesda_courses as tc', function ($join) {
                    $join->on('tc.id', '=', 'si.courseid')
                        ->where('tc.deleted', '=', 0);
                })
                ->leftJoin('sh_strand as shs', function ($join) {
                    $join->on('shs.id', '=', 'si.strandid')
                        ->where('shs.deleted', '=', 0);
                })
                ->leftJoin('academicprogram as ap', function ($join) {
                    $join->on('ap.id', '=', 'gl.acadprogid');
                })
                ->leftJoin('grantee as g', function ($join) {
                    $join->on('g.id', '=', 'si.grantee');
                })
                ->leftJoin('permittoexam as pte', function ($join) use ($schoolYear, $semester) {
                    $join->on('pte.studid', '=', 'si.id')
                        ->where('pte.deleted', '=', 0);
                    if ($schoolYear) {
                        $join->where('pte.syid', '=', $schoolYear);
                    }
                    if ($semester) {
                        $join->where('pte.semid', '=', $semester);
                    }
                })
                ->select([
                    'si.id',
                    'si.sid',
                    'si.lrn',
                    DB::raw("TRIM(CONCAT(
                        COALESCE(si.lastname, ''), ', ',
                        COALESCE(si.firstname, ''),
                        CASE WHEN si.middlename IS NOT NULL AND si.middlename != ''
                            THEN CONCAT(' ', LEFT(si.middlename, 1), '.')
                            ELSE ''
                        END,
                        CASE WHEN si.suffix IS NOT NULL AND si.suffix != ''
                            THEN CONCAT(' ', si.suffix)
                            ELSE ''
                        END
                    )) as fullname"),
                    'si.lastname',
                    'si.firstname',
                    'si.middlename',
                    'si.suffix',
                    'si.gender',
                    'si.dob',
                    'si.contactno',
                    'si.levelid',
                    'si.sectionid',
                    'si.strandid',
                    'si.courseid',
                    'si.feesid',
                    'si.studstatus',
                    'si.nodp',
                    'si.allowtoexam',
                    'si.grantee',
                    'gl.levelname as academic_level',
                    'gl.acadprogid as program_id',
                    'gl.nodp as gradelevel_nodp',
                    'gl.voucher',
                    'gl.esc',
                    'ap.progname as program_name',
                    'g.description as grantee_desc',
                    DB::raw("CASE
                        WHEN gl.acadprogid = 6 THEN csec.sectionDesc
                        ELSE sec.sectionname
                    END as section_name"),
                    DB::raw("CASE
                        WHEN gl.acadprogid = 6 THEN cc.courseDesc
                        WHEN gl.acadprogid = 7 THEN tc.course_name
                        ELSE NULL
                    END as course_name"),
                    'shs.strandname',
                    'shs.strandcode',
                    DB::raw("CASE WHEN pte.id IS NOT NULL THEN 1 ELSE 0 END as permit")
                ]);

            // Apply deleted filter
            if (Schema::hasColumn('studinfo', 'deleted')) {
                $query->where('si.deleted', 0);
            }

            // Apply active student filter
            if (Schema::hasColumn('studinfo', 'studisactive')) {
                $query->where('si.studisactive', 1);
            }

            // Filter based on enrollment status using studstatus
            if ($status !== null && $status !== '') {
                if ($status == 0) {
                    // Not enrolled - use the exclusion pattern from the original code
                    if ($schoolYear && $enrolledStudentIds->isNotEmpty()) {
                        $query->whereNotIn('si.id', $enrolledStudentIds);
                    }
                    // Also check studstatus field
                    $query->where(function ($q) {
                        $q->where('si.studstatus', 0)
                            ->orWhereNull('si.studstatus');
                    });
                } else {
                    // Enrolled with specific status
                    if ($schoolYear) {
                        if ($enrolledStudentIds->isEmpty()) {
                            return response()->json([
                                'success' => true,
                                'data' => [],
                                'pagination' => [
                                    'current_page' => $page,
                                    'per_page' => $perPage,
                                    'total' => 0,
                                    'last_page' => 1,
                                    'from' => 0,
                                    'to' => 0
                                ],
                                'filters_applied' => $this->getAppliedFilters($request)
                            ]);
                        }
                        $query->whereIn('si.id', $enrolledStudentIds);
                    }
                    $query->where('si.studstatus', $status);
                }
            } else {
                // No status filter - show ALL students (both enrolled and not enrolled)
                // The enrolled students will be ordered first via the ORDER BY clause
                // No need to filter by enrollment when showing all statuses
            }

            // Academic Program filter
            if ($academicProgram) {
                $query->where('gl.acadprogid', $academicProgram);
            }

            // Academic Level filter
            if ($academicLevel) {
                $query->where('si.levelid', $academicLevel);
            }

            // College filter - get all courses in the selected college
            if ($college && !$course) {
                $courseIdsInCollege = DB::table('college_courses')
                    ->where('collegeid', $college)
                    ->where('deleted', 0)
                    ->pluck('id');

                if ($courseIdsInCollege->isNotEmpty()) {
                    $query->whereIn('si.courseid', $courseIdsInCollege);
                }
            }

            // Course filter
            if ($course) {
                $query->where('si.courseid', $course);
            }

            // Strand filter
            if ($strand) {
                $query->where('si.strandid', $strand);
            }

            // Section filter
            if ($section) {
                $query->where('si.sectionid', $section);
            }

            // Scholarship filter
            $scholarshipStudentIds = null;
            if ($scholarship) {
                $scholarshipStudentIds = DB::table('scholarshipstud')
                    ->where('scholarid', $scholarship)
                    ->where('deleted', 0)
                    ->pluck('studid');

                if ($scholarshipStudentIds->isEmpty()) {
                    return response()->json([
                        'success' => true,
                        'data' => [],
                        'pagination' => [
                            'current_page' => $page,
                            'per_page' => $perPage,
                            'total' => 0,
                            'last_page' => 1,
                            'from' => 0,
                            'to' => 0
                        ],
                        'filters_applied' => $this->getAppliedFilters($request)
                    ]);
                }

                $query->whereIn('si.id', $scholarshipStudentIds);
            }

            // Apply search filter
            if (!empty($search)) {
                $searchTerm = trim($search);
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('si.sid', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('si.lrn', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('si.lastname', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('si.firstname', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('si.middlename', 'LIKE', "%{$searchTerm}%")
                        ->orWhere(DB::raw("CONCAT(si.firstname, ' ', si.lastname)"), 'LIKE', "%{$searchTerm}%")
                        ->orWhere(DB::raw("CONCAT(si.lastname, ', ', si.firstname)"), 'LIKE', "%{$searchTerm}%")
                        ->orWhere(DB::raw("CONCAT(si.lastname, ', ', si.firstname, ' ', COALESCE(si.middlename, ''))"), 'LIKE', "%{$searchTerm}%");
                });
            }

            // Get total count
            $total = $query->count();

            // Get ALL student IDs (optimized query without heavy joins)
            $studentIdsQuery = DB::table('studinfo as si');

            if (Schema::hasColumn('studinfo', 'deleted')) {
                $studentIdsQuery->where('si.deleted', 0);
            }
            if (Schema::hasColumn('studinfo', 'studisactive')) {
                $studentIdsQuery->where('si.studisactive', 1);
            }

            // Apply same enrollment status logic
            if ($status !== null && $status !== '') {
                if ($status == 0) {
                    if ($schoolYear && $enrolledStudentIds->isNotEmpty()) {
                        $studentIdsQuery->whereNotIn('si.id', $enrolledStudentIds);
                    }
                    $studentIdsQuery->where(function ($q) {
                        $q->where('si.studstatus', 0)
                            ->orWhereNull('si.studstatus');
                    });
                } else {
                    if ($schoolYear && $enrolledStudentIds->isNotEmpty()) {
                        $studentIdsQuery->whereIn('si.id', $enrolledStudentIds);
                    }
                    $studentIdsQuery->where('si.studstatus', $status);
                }
            } else {
                // No status filter - show ALL students (both enrolled and not enrolled)
                // No need to filter by enrollment when showing all statuses
            }

            if ($academicProgram) {
                $studentIdsQuery->join('gradelevel as gl', 'gl.id', '=', 'si.levelid')
                    ->where('gl.acadprogid', $academicProgram);
            }
            if ($academicLevel) {
                $studentIdsQuery->where('si.levelid', $academicLevel);
            }
            // College filter for student IDs query
            if ($college && !$course) {
                $courseIdsInCollege = DB::table('college_courses')
                    ->where('collegeid', $college)
                    ->where('deleted', 0)
                    ->pluck('id');

                if ($courseIdsInCollege->isNotEmpty()) {
                    $studentIdsQuery->whereIn('si.courseid', $courseIdsInCollege);
                }
            }
            if ($course) {
                $studentIdsQuery->where('si.courseid', $course);
            }
            if ($strand) {
                $studentIdsQuery->where('si.strandid', $strand);
            }
            if ($section) {
                $studentIdsQuery->where('si.sectionid', $section);
            }
            if ($scholarship && $scholarshipStudentIds) {
                $studentIdsQuery->whereIn('si.id', $scholarshipStudentIds);
            }
            if (!empty($search)) {
                $searchTerm = trim($search);
                $studentIdsQuery->where(function ($q) use ($searchTerm) {
                    $q->where('si.sid', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('si.lrn', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('si.lastname', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('si.firstname', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('si.middlename', 'LIKE', "%{$searchTerm}%")
                        ->orWhere(DB::raw("CONCAT(si.firstname, ' ', si.lastname)"), 'LIKE', "%{$searchTerm}%")
                        ->orWhere(DB::raw("CONCAT(si.lastname, ', ', si.firstname)"), 'LIKE', "%{$searchTerm}%")
                        ->orWhere(DB::raw("CONCAT(si.lastname, ', ', si.firstname, ' ', COALESCE(si.middlename, ''))"), 'LIKE', "%{$searchTerm}%");
                });
            }

            $studentIds = $studentIdsQuery->pluck('si.id')->toArray();

            // Apply pagination
            $offset = ($page - 1) * $perPage;

            // Order by:
            // 1. Enrollment status (enrolled students ALWAYS first, regardless of level)
            // 2. Has fees (students with feesid first within each group)
            // 3. levelid (lowest level first within each group)
            // 4. Name (lastname, firstname)
            if ($enrolledStudentIds->isNotEmpty()) {
                $enrolledIdsArray = $enrolledStudentIds->toArray();
                $placeholders = implode(',', array_fill(0, count($enrolledIdsArray), '?'));
                $students = $query->orderByRaw("CASE WHEN si.id IN ({$placeholders}) THEN 0 ELSE 1 END", $enrolledIdsArray)
                    ->orderByRaw("CASE WHEN si.feesid IS NOT NULL AND si.feesid != '' AND si.feesid != 0 THEN 0 ELSE 1 END")
                    ->orderBy('si.levelid', 'asc')
                    ->orderBy('si.lastname')
                    ->orderBy('si.firstname')
                    ->offset($offset)
                    ->limit($perPage)
                    ->get();
            } else {
                // Order by enrollment status, has fees, levelid, then name
                $students = $query->orderBy('si.studstatus', 'desc')
                    ->orderByRaw("CASE WHEN si.feesid IS NOT NULL AND si.feesid != '' AND si.feesid != 0 THEN 0 ELSE 1 END")
                    ->orderBy('si.levelid', 'asc')
                    ->orderBy('si.lastname')
                    ->orderBy('si.firstname')
                    ->offset($offset)
                    ->limit($perPage)
                    ->get();
            }

            // Add enrollment status flag to each student
            $students = $students->map(function ($student) use ($enrolledStudentIds) {
                $student->is_enrolled = $enrolledStudentIds->contains($student->id) ? 1 : 0;
                return $student;
            });

            // Resolve feesid from active enrollment when studinfo.feesid is empty
            $students = $students->map(function ($student) use ($activeSyId, $activeSemId) {
                if (!empty($student->feesid)) {
                    return $student;
                }

                $level = (int) ($student->levelid ?? 0);
                $enrollment = null;

                if ($activeSyId) {
                    if ($level >= 14 && $level <= 15) {
                        $enrollment = DB::table('sh_enrolledstud')
                            ->where('studid', $student->id)
                            ->where('syid', $activeSyId)
                            ->when($activeSemId, function ($q) use ($activeSemId) {
                                $q->where('semid', $activeSemId);
                            })
                            ->where('deleted', 0)
                            ->orderByDesc('id')
                            ->first();
                    } elseif ($level >= 17 && $level <= 25) {
                        $enrollment = DB::table('college_enrolledstud')
                            ->where('studid', $student->id)
                            ->where('syid', $activeSyId)
                            ->when($activeSemId, function ($q) use ($activeSemId) {
                                $q->where('semid', $activeSemId);
                            })
                            ->where('deleted', 0)
                            ->orderByDesc('id')
                            ->first();
                    } else {
                        $enrollment = DB::table('enrolledstud')
                            ->where('studid', $student->id)
                            ->where('syid', $activeSyId)
                            ->where('deleted', 0)
                            ->orderByDesc('id')
                            ->first();
                    }
                }

                if ($enrollment && isset($enrollment->feesid)) {
                    $student->feesid = $enrollment->feesid;
                }

                return $student;
            });

            // Add student status description
            $students = $this->addStudentStatusDescription($students);

            // Add financial data
            // Note: semester can be null for grade school students, they only need school year
            if ($schoolYear) {
                try {

                    $students = $this->addFinancialData($students, $schoolYear, $semester, $academicProgram);
                } catch (\Exception $e) {
                    // Log the error and continue without financial data
                    \Log::error('[CRITICAL] addFinancialData failed entirely', [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'school_year' => $schoolYear,
                        'semester' => $semester,
                        'academic_program' => $academicProgram,
                        'students_count' => $students->count(),
                        'student_ids' => $students->pluck('id')->toArray(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Add error to response for debugging (visible in response)
                    $errorInfo = [
                        'error' => 'addFinancialData_failed',
                        'message' => $e->getMessage(),
                        'file' => basename($e->getFile()),
                        'line' => $e->getLine()
                    ];

                    // Set default financial data structure to prevent errors
                    $students = $students->map(function ($student) use ($errorInfo) {
                        $student->financial_data = [
                            'is_itemized' => false,
                            'current_due_date' => null,
                            'due_dates' => [],
                            'outside_fees' => [],
                            'syid' => null,
                            'semid' => null,
                            'totals' => [
                                'total_fees' => 0,
                                'total_payment' => 0,
                                'current_balance' => 0,
                                'full_balance' => 0,
                                'total_balance' => 0,
                                'total_overpayment' => 0,
                            ],
                            'debug_error' => $errorInfo
                        ];
                        return $student;
                    });
                }
            }

            // Calculate pagination
            $lastPage = ceil($total / $perPage);
            $from = $total > 0 ? $offset + 1 : 0;
            $to = min($offset + $perPage, $total);
            $nextPage = $page < $lastPage ? $page + 1 : null;
            $prevPage = $page > 1 ? $page - 1 : null;

            return response()->json([
                'success' => true,
                'data' => $students,
                'student_ids' => $studentIds,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $lastPage,
                    'last_page' => $lastPage,
                    'next_page' => $nextPage,
                    'prev_page' => $prevPage,
                    'from' => $from,
                    'to' => $to
                ],
                'filters_applied' => $this->getAppliedFilters($request)
            ]);

        } catch (\Exception $e) {
            // Log the full error details for debugging
            \Log::error('Error in getStudentAccounts', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'previous' => $e->getPrevious() ? [
                    'message' => $e->getPrevious()->getMessage(),
                    'file' => $e->getPrevious()->getFile(),
                    'line' => $e->getPrevious()->getLine(),
                ] : null
            ]);

            // Return a more user-friendly error message
            $errorMessage = $e->getMessage();
            // Don't expose internal file paths to users
            if (strpos($errorMessage, 'Undefined offset') !== false) {
                $errorMessage = 'Data processing error. Please contact support.';
            }

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving student data: ' . $errorMessage,
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'total_pages' => 1,
                    'last_page' => 1,
                    'next_page' => null,
                    'prev_page' => null,
                    'from' => 0,
                    'to' => 0
                ]
            ], 500);
        }
    }

    /**
     * Get total payments for students
     */
    private function getStudentPayments($studentIds, $schoolYear, $semester, $academicProgram)
    {
        if (empty($studentIds)) {
            return collect();
        }

        // Get payments from chrngtrans (regular payments)
        $payments = DB::table('chrngtrans')
            ->selectRaw('SUM(amountpaid) as amount, studid')
            ->whereIn('studid', $studentIds)
            ->where('cancelled', 0)
            ->where('syid', $schoolYear)
            ->when($academicProgram == 6 && $semester, function ($q) use ($semester) {
                $q->where('semid', $semester);
            })
            ->groupBy('studid')
            ->get()
            ->keyBy('studid');

        // Get old account payments from studledger
        $balForwardRecord = DB::table('balforwardsetup')->first();
        $balclassid = $balForwardRecord ? $balForwardRecord->classid : null;
        $studledgerPayments = collect();

        if ($balclassid) {
            $studledgerPayments = DB::table('studledger')
                ->selectRaw('SUM(payment) as amount, studid')
                ->whereIn('studid', $studentIds)
                ->where('deleted', 0)
                ->where('syid', $schoolYear)
                ->when($academicProgram == 6 && $semester, function ($q) use ($semester) {
                    $q->where('semid', $semester);
                })
                ->where('payment', '>', 0)
                ->where('classid', $balclassid)
                ->groupBy('studid')
                ->get()
                ->keyBy('studid');
        }

        // Get credit adjustments
        $adjustmentCredits = DB::table('adjustmentdetails')
            ->selectRaw('SUM(amount) as amount, studid')
            ->join('adjustments', 'adjustmentdetails.headerid', '=', 'adjustments.id')
            ->whereIn('adjustmentdetails.studid', $studentIds)
            ->where('adjustmentdetails.deleted', 0)
            ->where('adjustments.deleted', 0)
            ->where('adjustments.syid', $schoolYear)
            ->when($academicProgram == 6 && $semester, function ($q) use ($semester) {
                $q->where('adjustments.semid', $semester);
            })
            ->where('adjustments.iscredit', 1)
            ->groupBy('adjustmentdetails.studid')
            ->get()
            ->keyBy('studid');

        // Merge all payment sources
        $result = collect();
        foreach ($studentIds as $studid) {
            $payment = optional($payments->get($studid))->amount ?? 0;
            $studledger = optional($studledgerPayments->get($studid))->amount ?? 0;
            $adjustment = optional($adjustmentCredits->get($studid))->amount ?? 0;

            $result->put($studid, $payment + $studledger + $adjustment);
        }

        return $result;
    }

    /**
     * Get enrolled student IDs based on school year, semester, and program
     */
    private function getEnrolledStudentIds($schoolYear = null, $semester = null, $academicProgram = null)
    {
        $enrolledIds = collect();

        // Regular/Basic Education (acadprogid: 2,3,4)
        if (!$academicProgram || in_array($academicProgram, [2, 3, 4])) {
            if (Schema::hasTable('enrolledstud')) {
                $query = DB::table('enrolledstud')->select('studid');

                if ($schoolYear) {
                    $query->where('syid', $schoolYear);
                }

                $query->where('deleted', 0);
                $enrolledIds = $enrolledIds->merge($query->pluck('studid'));
            }
        }

        // Senior High School (acadprogid: 5)
        if (!$academicProgram || $academicProgram == 5) {
            if (Schema::hasTable('sh_enrolledstud')) {
                $query = DB::table('sh_enrolledstud')->select('studid');

                if ($schoolYear) {
                    $query->where('syid', $schoolYear);
                }
                if ($semester) {
                    $query->where('semid', $semester);
                }

                $query->where('deleted', 0);
                $enrolledIds = $enrolledIds->merge($query->pluck('studid'));
            }
        }

        // College (acadprogid: 6)
        if (!$academicProgram || $academicProgram == 6) {
            if (Schema::hasTable('college_enrolledstud')) {
                $query = DB::table('college_enrolledstud')->select('studid');

                if ($schoolYear) {
                    $query->where('syid', $schoolYear);
                }
                if ($semester) {
                    $query->where('semid', $semester);
                }

                $query->where('deleted', 0);
                $enrolledIds = $enrolledIds->merge($query->pluck('studid'));
            }
        }

        // TESDA (acadprogid: 7)
        if (!$academicProgram || $academicProgram == 7) {
            if (Schema::hasTable('tesda_enrolledstud')) {
                $query = DB::table('tesda_enrolledstud')->select('studid');

                if ($schoolYear) {
                    $query->where('syid', $schoolYear);
                }

                $query->where('deleted', 0);
                $enrolledIds = $enrolledIds->merge($query->pluck('studid'));
            }
        }

        return $enrolledIds->unique()->filter();
    }

    /**
     * Add student status description to the student records
     */
    private function addStudentStatusDescription($students)
    {
        if ($students->isEmpty()) {
            return $students;
        }

        $statusDescriptions = DB::table('studentstatus')
            ->pluck('description', 'id');

        return $students->map(function ($student) use ($statusDescriptions) {
            $student->studstatus_desc = $statusDescriptions[$student->studstatus] ?? null;
            return $student;
        });
    }

    /**
     * Add financial data (fees, payments, balance) to student records
     * For enrolled students: Returns breakdown by due dates
     * For not enrolled students: Returns totals only
     */
    private function addFinancialData($students, $schoolYear, $semester, $academicProgram)
    {
        if ($students->isEmpty()) {
            return $students;
        }

        $studentIds = $students->pluck('id')->toArray();

        // Prefetch enrollment semid per student for SHS/College (for semester fallback)
        $shSemMap = collect();
        $collegeSemMap = collect();
        if (!empty($studentIds)) {
            if (Schema::hasTable('sh_enrolledstud')) {
                $shSemMap = DB::table('sh_enrolledstud')
                    ->whereIn('studid', $studentIds)
                    ->where('syid', $schoolYear)
                    ->when($semester, function ($q) use ($semester) {
                        $q->where('semid', $semester);
                    })
                    ->where('deleted', 0)
                    ->pluck('semid', 'studid');
            }
            if (Schema::hasTable('college_enrolledstud')) {
                $collegeSemMap = DB::table('college_enrolledstud')
                    ->whereIn('studid', $studentIds)
                    ->where('syid', $schoolYear)
                    ->when($semester, function ($q) use ($semester) {
                        $q->where('semid', $semester);
                    })
                    ->where('deleted', 0)
                    ->pluck('semid', 'studid');
            }
        }

        // Get enrolled student IDs
        $enrolledStudentIds = $this->getEnrolledStudentIds($schoolYear, $semester, $academicProgram);

        // Separate enrolled and not enrolled students
        $enrolledIds = $enrolledStudentIds->intersect($studentIds)->toArray();
        $notEnrolledIds = array_diff($studentIds, $enrolledIds);

        // Get payment schedule details for enrolled students
        $paymentSchedules = [];
        if (!empty($enrolledIds)) {
            try {
                $paymentSchedules = $this->getStudentPaymentSchedules($enrolledIds, $schoolYear, $semester);
            } catch (\Exception $e) {
                throw new \Exception("Error in getStudentPaymentSchedules: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            }
        }

        // Get old calculation for not enrolled students
        $paymentsMap = collect();
        if (!empty($notEnrolledIds)) {
            try {
                $paymentsMap = $this->getStudentPayments($notEnrolledIds, $schoolYear, $semester, $academicProgram);
            } catch (\Exception $e) {
                throw new \Exception("Error in getStudentPayments: " . $e->getMessage());
            }
        }

        // Process each student
        return $students
            // Attach enrollment semid for SHS/College
            ->map(function ($student) use ($shSemMap, $collegeSemMap) {
                if (!isset($student->enrollment_semid)) {
                    $acad = $student->program_id ?? 0;
                    if (in_array($acad, [5]) && isset($shSemMap[$student->id])) {
                        $student->enrollment_semid = $shSemMap[$student->id];
                    } elseif (in_array($acad, [6]) && isset($collegeSemMap[$student->id])) {
                        $student->enrollment_semid = $collegeSemMap[$student->id];
                    }
                }
                return $student;
            })
            // If semester filter is provided, drop SHS/College students whose enrollment semid mismatches
            // If semester filter is empty, exclude SHS/College entirely to avoid mixing terms
            ->filter(function ($student) use ($semester) {
                $acad = $student->program_id ?? 0;
                if (!$semester && in_array($acad, [5, 6])) {
                    return false;
                }
                if ($semester && in_array($acad, [5, 6])) {
                    if (isset($student->enrollment_semid) && (string) $student->enrollment_semid !== (string) $semester) {
                        return false;
                    }
                }
                return true;
            })
            ->values()
            ->map(function ($student) use ($schoolYear, $semester, $enrolledIds, $paymentSchedules, $paymentsMap, $shSemMap, $collegeSemMap) {
                try {
                    $isEnrolled = in_array($student->id, $enrolledIds);
                    $acadprogid = $student->program_id ?? 0;
                    $levelArray = [$student->levelid];

                    // Attach enrollment semid for SHS/College when available (used as fallback below)
                    if (!isset($student->enrollment_semid)) {
                        if (in_array($acadprogid, [5]) && isset($shSemMap[$student->id])) {
                            $student->enrollment_semid = $shSemMap[$student->id];
                        } elseif (in_array($acadprogid, [6]) && isset($collegeSemMap[$student->id])) {
                            $student->enrollment_semid = $collegeSemMap[$student->id];
                        }
                    }

                    if ($isEnrolled) {
                        // ENROLLED: Get breakdown by due dates from studpayscheddetail
                        $scheduleData = $paymentSchedules[$student->id] ?? [];

                        // Extract schedule and outside fees with proper safety checks
                        // Ensure scheduleData is an array
                        if (!is_array($scheduleData)) {
                            $scheduleData = [];
                        }

                        // Extract schedule - ensure it's always an array
                        if (isset($scheduleData['due_dates']) && is_array($scheduleData['due_dates'])) {
                            $schedule = $scheduleData['due_dates'];
                        } elseif (is_array($scheduleData) && !isset($scheduleData['due_dates'])) {
                            // If scheduleData is an array but doesn't have 'due_dates' key, use it directly if it's numeric indexed
                            $schedule = array_values($scheduleData); // Re-index to ensure numeric indices
                        } else {
                            $schedule = [];
                        }

                        // Ensure schedule is an array
                        if (!is_array($schedule)) {
                            $schedule = [];
                        }

                        $outsideFees = isset($scheduleData['outside_fees']) && is_array($scheduleData['outside_fees'])
                            ? $scheduleData['outside_fees']
                            : [];

                        // If still no schedule (e.g., missing studpaysched rows), fall back to non-itemized totals
                        if (empty($schedule)) {
                            $tuitionFee = $this->calculateTuitionFee($student, $schoolYear, $semester, $acadprogid, $levelArray);
                            $oldAcctCharges = $this->getOldAcctCharges($student->id, $schoolYear, $semester, $acadprogid);
                            $bookCharges = $this->getBookentriesCharges($student->id, $schoolYear, $semester, $acadprogid);
                            $adjustmentCharges = $this->getAdjustmentCharges($student->id, $schoolYear, $semester, $acadprogid, $levelArray, 'charges');
                            $creditAdjustments = $this->getAdjustmentCharges($student->id, $schoolYear, $semester, $acadprogid, $levelArray, 'credits');
                            $labFeeData = ($acadprogid == 6) ? self::getLabFees($student->id, $schoolYear, $semester) : ['total' => 0, 'items' => []];
                            $labFee = $labFeeData['total'];

                            $totalFees = $tuitionFee + $oldAcctCharges + $bookCharges + $adjustmentCharges + $creditAdjustments + $labFee;
                            $totalPayment = $paymentsMap->get($student->id, 0);
                            $balance = $totalFees - $totalPayment;

                            $student->financial_data = [
                                'is_itemized' => false,
                                'current_due_date' => null,
                                'due_dates' => [],
                                'outside_fees' => $outsideFees,
                                'lab_fee_items' => $labFeeData['items'] ?? [],
                                'syid' => $schoolYear,
                                'semid' => $semester,
                                'totals' => [
                                    'total_fees' => round($totalFees, 2),
                                    'total_payment' => round($totalPayment, 2),
                                    'total_balance' => round($balance, 2),
                                ]
                            ];
                            return $student;
                        }

                        // Check if priorities should override
                        // For SHS/College: if semester param is empty, fall back to enrollment semid to avoid mixing terms
                        if (!$semester && in_array($acadprogid, [5, 6]) && isset($student->enrollment_semid)) {
                            $semester = $student->enrollment_semid;
                        }

                        $schedule = $this->applyPriorityOverrides($student, $schoolYear, $semester, $schedule);

                        // Determine current due date (first due date with balance > 0)
                        $currentDueDate = null;
                        foreach ($schedule as $item) {
                            // Safety check: ensure item is an array and has required keys
                            if (!is_array($item)) {
                                continue;
                            }
                            if (isset($item['balance']) && $item['balance'] > 0) {
                                $currentDueDate = $item['duedate'] ?? null;
                                break;
                            }
                        }

                        // Get total overpayment from the schedule data (calculated with cascading)
                        $totalOverpayment = $scheduleData['total_overpayment'] ?? 0;

                        // Calculate full balance (all due dates including future)
                        $fullBalance = round(array_sum(array_column($schedule, 'balance')), 2);

                        // Calculate current balance: overdue + current month only (exclude future due dates)
                        $currentMonth = date('Y-m');
                        $currentBalance = 0;

                        foreach ($schedule as $item) {
                            // Safety check: ensure item is an array and has required keys
                            if (!is_array($item)) {
                                continue;
                            }
                            if (!empty($item['duedate'] ?? null)) {
                                $itemMonth = date('Y-m', strtotime($item['duedate']));
                                // Include if due date is in the past or current month
                                if ($itemMonth <= $currentMonth) {
                                    $currentBalance += $item['balance'] ?? 0;
                                }
                            }
                        }

                        $student->financial_data = [
                            'is_itemized' => true,
                            'current_due_date' => $currentDueDate,
                            'due_dates' => $schedule, // Send all due dates to frontend
                            'outside_fees' => $outsideFees, // Send outside fees to frontend
                            'syid' => $schoolYear,
                            'semid' => $semester,
                            'totals' => [
                                'total_fees' => round(array_sum(array_column($schedule, 'amount')), 2),
                                'total_payment' => round(array_sum(array_column($schedule, 'amountpay')), 2),
                                'current_balance' => round($currentBalance, 2), // Only overdue + current month
                                'full_balance' => round($fullBalance, 2), // All balances including future
                                'total_balance' => round($fullBalance, 2), // For backward compatibility, use full balance
                                'total_overpayment' => round($totalOverpayment, 2),
                            ]
                        ];
                    } else {
                        // NOT ENROLLED: Calculate totals only
                        try {
                            $tuitionFee = $this->calculateTuitionFee($student, $schoolYear, $semester, $acadprogid, $levelArray);
                        } catch (\Exception $e) {
                            throw new \Exception("Error in calculateTuitionFee: " . $e->getMessage());
                        }

                        try {
                            $oldAcctCharges = $this->getOldAcctCharges($student->id, $schoolYear, $semester, $acadprogid);
                        } catch (\Exception $e) {
                            throw new \Exception("Error in getOldAcctCharges: " . $e->getMessage());
                        }

                        try {
                            $bookCharges = $this->getBookentriesCharges($student->id, $schoolYear, $semester, $acadprogid);
                        } catch (\Exception $e) {
                            throw new \Exception("Error in getBookentriesCharges: " . $e->getMessage());
                        }

                        try {
                            $adjustmentCharges = $this->getAdjustmentCharges($student->id, $schoolYear, $semester, $acadprogid, $levelArray, 'charges');
                        } catch (\Exception $e) {
                            throw new \Exception("Error in getAdjustmentCharges (charges): " . $e->getMessage());
                        }

                        try {
                            $creditAdjustments = $this->getAdjustmentCharges($student->id, $schoolYear, $semester, $acadprogid, $levelArray, 'credits');
                        } catch (\Exception $e) {
                            throw new \Exception("Error in getAdjustmentCharges (credits): " . $e->getMessage());
                        }

                        try {
                            $labFeeData = ($acadprogid == 6) ? self::getLabFees($student->id, $schoolYear, $semester) : ['total' => 0, 'items' => []];
                            $labFee = $labFeeData['total'];
                            $labFeeItems = $labFeeData['items'];
                        } catch (\Exception $e) {
                            throw new \Exception("Error in getLabFees: " . $e->getMessage());
                        }

                        $totalFees = $tuitionFee + $oldAcctCharges + $bookCharges + $adjustmentCharges + $creditAdjustments + $labFee;
                        $totalPayment = $paymentsMap->get($student->id, 0);
                        $balance = $totalFees - $totalPayment;

                        $student->financial_data = [
                            'is_itemized' => false,
                            'current_due_date' => null,
                            'due_dates' => [],
                            'lab_fee_items' => $labFeeItems,
                            'syid' => $schoolYear,
                            'semid' => $semester,
                            'totals' => [
                                'total_fees' => round($totalFees, 2),
                                'total_payment' => round($totalPayment, 2),
                                'total_balance' => round($balance, 2),
                            ]
                        ];
                    }

                    return $student;
                } catch (\Exception $e) {
                    // Log error for this specific student but continue processing others
                    \Log::error('Error processing financial data for student', [
                        'student_id' => $student->id,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Return student with default financial data
                    $student->financial_data = [
                        'is_itemized' => false,
                        'current_due_date' => null,
                        'due_dates' => [],
                        'outside_fees' => [],
                        'syid' => $schoolYear,
                        'semid' => $semester,
                        'totals' => [
                            'total_fees' => 0,
                            'total_payment' => 0,
                            'current_balance' => 0,
                            'full_balance' => 0,
                            'total_balance' => 0,
                            'total_overpayment' => 0,
                        ]
                    ];
                    return $student;
                }
            });
    }

    /**
     * Get student payment schedules calculated from tuitiondetail and chrngcashtrans
     * Manually calculates fees like resetpayment_v3 does (units * amount for tuition, etc.)
     */
    public function getStudentPaymentSchedules($studentIds, $schoolYear, $semester)
    {
        if (empty($studentIds)) {
            return [];
        }

        $grouped = [];

        // Get students with their level info
        $students = DB::table('studinfo')
            ->select('id', 'levelid', 'courseid', 'strandid', 'feesid')
            ->whereIn('id', $studentIds)
            ->where('deleted', 0)
            ->get()
            ->keyBy('id');

        foreach ($studentIds as $studid) {
            try {
                $student = $students->get($studid);
                if (!$student)
                    continue;

                if ($studid == 9) {
                    \Log::info('[STUDENT-9-DEBUG] Starting processing for student 9', [
                        'levelid' => $student->levelid,
                        'courseid' => $student->courseid
                    ]);
                }

                // Get the feesid from enrolled student table (determines which tuitionheader to use)
                $feesId = null;
                $enrollTable = null;

                if ($student->levelid == 14 || $student->levelid == 15) {
                    $enrollTable = 'sh_enrolledstud';
                } elseif ($student->levelid >= 17 && $student->levelid <= 25) {
                    $enrollTable = 'college_enrolledstud';
                } elseif ($student->levelid == 26) {
                    $enrollTable = 'tesda_enrolledstud';
                } else {
                    $enrollTable = 'enrolledstud';
                }

                $isBasicLevel = (($student->levelid >= 1 && $student->levelid <= 13) || $student->levelid == 16);

                $enrollQuery = DB::table($enrollTable)
                    ->where('studid', $studid)
                    ->where('syid', $schoolYear);

                // Apply semester filter ONLY for SHS/College; ignore sem for basic (level < 14)
                if (!is_null($semester) && !$isBasicLevel) {
                    $enrollQuery->where('semid', $semester);
                }

                $enrollInfo = $enrollQuery->where('deleted', 0)->first();

                \Log::debug('[TUITION-FEES-DEBUG] Enrollment query for student', [
                    'studid' => $studid,
                    'levelid' => $student->levelid,
                    'enroll_table' => $enrollTable,
                    'school_year' => $schoolYear,
                    'semester' => $semester,
                    'enroll_info_exists' => !is_null($enrollInfo),
                    'enroll_info' => $enrollInfo ? (array) $enrollInfo : null
                ]);

                if ($enrollInfo && isset($enrollInfo->feesid)) {
                    $feesId = $enrollInfo->feesid;
                }

                \Log::debug('[TUITION-FEES-DEBUG] Feesid extracted', [
                    'studid' => $studid,
                    'feesid' => $feesId
                ]);

                // Fallback: use studinfo feesid if enrollment feesid is empty
                if (!$feesId && !empty($student->feesid)) {
                    $feesId = $student->feesid;
                }

                // Fallback: pull latest feesid using helper (checks studinfo then enrollment tables)
                if (!$feesId) {
                    $feesId = $this->getLatestFeesId($studid, (int) $student->levelid, $schoolYear, $isBasicLevel ? null : $semester);
                    \Log::debug('[TUITION-FEES-DEBUG] Feesid fallback via getLatestFeesId', [
                        'studid' => $studid,
                        'feesid' => $feesId
                    ]);
                }

                // If no feesid was found at all, skip (no fee structure assigned)
                if (!$feesId) {
                    \Log::warning('[TUITION-FEES-DEBUG] Skipping - no feesid found', [
                        'studid' => $studid,
                        'enroll_info_exists' => !is_null($enrollInfo),
                        'enroll_table' => $enrollTable
                    ]);
                    continue;
                }

                // Get tuition setup from tuitionheader/tuitiondetail using the specific feesid
                // For college and SHS students, also validate syid and semid to ensure correct matching
                $query = DB::table('tuitionheader as th')
                    ->join('tuitiondetail as td', 'th.id', '=', 'td.headerid')
                    ->join('itemclassification as ic', 'td.classificationid', '=', 'ic.id')
                    ->select([
                        'td.id as tuitiondetailid',
                        'td.classificationid as classid',
                        'ic.description as particulars',
                        'ic.classcode as priority',
                        'td.amount',
                        'td.pschemeid',
                        'td.istuition',
                        'td.persubj',
                        'td.permop',
                        'td.permopid',
                        'td.perunit'  // Add perunit field for proper fee calculation
                    ])
                    ->where('th.id', $feesId)  // Always use the feesid from enrollment - this uniquely identifies the tuitionheader
                    ->where('th.syid', $schoolYear);  // Validate syid matches

                // For SHS and College students, also validate semid matches
                // This ensures we get the correct tuitionheader even if there are data inconsistencies
                if (!is_null($semester) && !$isBasicLevel) {
                    $query->where(function ($q) use ($semester) {
                        $q->where('th.semid', $semester)
                            ->orWhereNull('th.semid');  // Allow NULL semid as fallback
                    });
                }

                $query->where('th.deleted', 0)
                    ->where('td.deleted', 0)
                    ->where('ic.deleted', 0);
                // Note: Don't filter by amount > 0 here because tuition fees with perunit=1 
                // may have a base amount that gets multiplied by units later

                $tuitionFees = $query->get();

                // Fallback: if nothing returned, relax SY filter (feesid should be unique)
                if ($tuitionFees->isEmpty() && $feesId) {
                    $fallbackQuery = DB::table('tuitionheader as th')
                        ->join('tuitiondetail as td', 'th.id', '=', 'td.headerid')
                        ->join('itemclassification as ic', 'td.classificationid', '=', 'ic.id')
                        ->select([
                            'td.id as tuitiondetailid',
                            'td.classificationid as classid',
                            'ic.description as particulars',
                            'ic.classcode as priority',
                            'td.amount',
                            'td.pschemeid',
                            'td.istuition',
                            'td.persubj',
                            'td.permop',
                            'td.permopid',
                            'td.perunit'
                        ])
                        ->where('th.id', $feesId)
                        ->where('th.deleted', 0)
                        ->where('td.deleted', 0)
                        ->where('ic.deleted', 0);

                    if (!is_null($semester) && !$isBasicLevel) {
                        $fallbackQuery->where(function ($q) use ($semester) {
                            $q->where('th.semid', $semester)
                                ->orWhereNull('th.semid');
                        });
                    }

                    $tuitionFees = $fallbackQuery->get();

                    \Log::debug('[TUITION-FEES-DEBUG] Tuition fees fallback without sy filter', [
                        'studid' => $studid,
                        'feesid' => $feesId,
                        'tuition_fees_count' => $tuitionFees->count(),
                    ]);
                }

                // Fallback: if still empty, match by level/course/strand for the term
                if ($tuitionFees->isEmpty()) {
                    $levelMatchQuery = DB::table('tuitionheader as th')
                        ->join('tuitiondetail as td', 'th.id', '=', 'td.headerid')
                        ->join('itemclassification as ic', 'td.classificationid', '=', 'ic.id')
                        ->select([
                            'td.id as tuitiondetailid',
                            'td.classificationid as classid',
                            'ic.description as particulars',
                            'ic.classcode as priority',
                            'td.amount',
                            'td.pschemeid',
                            'td.istuition',
                            'td.persubj',
                            'td.permop',
                            'td.permopid',
                            'td.perunit'
                        ])
                        ->where('th.syid', $schoolYear)
                        ->where('th.levelid', $student->levelid)
                        ->where('th.deleted', 0)
                        ->where('td.deleted', 0)
                        ->where('ic.deleted', 0);

                    if (!is_null($semester) && !$isBasicLevel) {
                        $levelMatchQuery->where(function ($q) use ($semester) {
                            $q->where('th.semid', $semester)
                                ->orWhereNull('th.semid');
                        });
                    }

                    if ($student->courseid) {
                        $levelMatchQuery->where('th.courseid', $student->courseid);
                    } elseif ($student->strandid) {
                        $levelMatchQuery->where('th.strandid', $student->strandid);
                    }

                    $tuitionFees = $levelMatchQuery->get();

                    \Log::debug('[TUITION-FEES-DEBUG] Level-match fallback tuition fees', [
                        'studid' => $studid,
                        'levelid' => $student->levelid,
                        'courseid' => $student->courseid,
                        'strandid' => $student->strandid,
                        'tuition_fees_count' => $tuitionFees->count(),
                    ]);
                }

                \Log::debug('[TUITION-FEES-DEBUG] Tuition fees query result', [
                    'studid' => $studid,
                    'feesid' => $feesId,
                    'school_year' => $schoolYear,
                    'semester' => $semester,
                    'levelid' => $student->levelid,
                    'tuition_fees_count' => $tuitionFees->count(),
                    'tuition_fees' => $tuitionFees->toArray()
                ]);

                if ($studid == 9) {
                    \Log::info('[STUDENT-9-DEBUG] Tuition fees retrieved', [
                        'tuition_fees_count' => $tuitionFees->count(),
                        'tuition_fees' => $tuitionFees->toArray()
                    ]);
                }

                if ($tuitionFees->isEmpty()) {
                    if ($studid == 9) {
                        \Log::warning('[STUDENT-9-DEBUG] Skipping - no tuition fees found');
                    }
                    continue;
                }

                // Get enrolled units for college students (level 17-25)
                $units = 0;
                if ($student->levelid >= 17 && $student->levelid <= 25) {
                    $unitsResult = DB::table('college_loadsubject as cls')
                        ->join('college_classsched as cs', 'cls.schedid', '=', 'cs.id')
                        ->join('college_prospectus as cp', 'cs.subjectID', '=', 'cp.id')
                        ->select(DB::raw('SUM(cp.lecunits + cp.labunits) as totalunits'))
                        ->where('cls.studid', $studid)
                        ->where('cls.syid', $schoolYear)
                        ->where('cls.semid', $semester)
                        ->where('cls.deleted', 0)
                        ->where(function ($q) {
                            $q->where('cls.isDropped', 0)
                                ->orWhereNull('cls.isDropped');
                        })
                        ->first();

                    $units = $unitsResult && $unitsResult->totalunits ? (float) $unitsResult->totalunits : 0;

                    // Get lab fees for college students and add them to tuition fees
                    // Split by subject (laboratory_fee_id) so each subject has its own breakdown items
                    // Note: labfees.amount = lab_amount + sum of items from laboratory_fee_items
                    // Get classid and itemid from labfeesetup (always 1 row)
                    $labFeeSetup = DB::table('labfeesetup')
                        ->where('deleted', 0)
                        ->first();

                    if ($labFeeSetup) {
                        $labFeeClassId = $labFeeSetup->classid;
                        $labFeeItemId = $labFeeSetup->itemid;

                        // Get student's enrolled subjects from college_loadsubject (subjectID contains college_prospectus.id)
                        $studentSubjectIDs = DB::table('college_loadsubject')
                            ->where('studid', $studid)
                            ->where('syid', $schoolYear)
                            ->where('semid', $semester)
                            ->where('deleted', 0)
                            ->pluck('subjectID')
                            ->unique()
                            ->toArray();

                        if (!empty($studentSubjectIDs)) {
                            // Query labfees directly where subjid matches subjectID from college_loadsubject
                            $labFeesBySubject = DB::table('labfees as lf')
                                ->leftJoin('college_prospectus as cp', 'lf.subjid', '=', 'cp.id')
                                ->select(
                                    'lf.id as laboratory_fee_id',
                                    'lf.amount',
                                    'lf.lab_amount', // The general lab fee amount (separate from items)
                                    'lf.mode_of_payment',
                                    'cp.subjCode as subjcode',
                                    'cp.subjDesc as subjdesc'
                                )
                                ->where('lf.syid', $schoolYear)
                                ->where('lf.semid', $semester)
                                ->where('lf.deleted', 0)
                                ->whereIn('lf.subjid', $studentSubjectIDs)
                                ->get();
                        } else {
                            $labFeesBySubject = collect([]);
                        }

                        foreach ($labFeesBySubject as $labFee) {
                            // Get sum of item amounts from laboratory_fee_items
                            $itemAmountsSum = DB::table('laboratory_fee_items')
                                ->where('laboratory_fee_id', $labFee->laboratory_fee_id)
                                ->where('deleted', 0)
                                ->sum('amount');

                            $labAmount = (float) ($labFee->lab_amount ?? 0);
                            $itemAmountsSum = (float) ($itemAmountsSum ?? 0);
                            $totalAmount = $labAmount + $itemAmountsSum;

                            if ($totalAmount > 0) {
                                $labFeePschemeid = $labFee->mode_of_payment ?? null;

                                // Create subject-specific label
                                $labFeeParticulars = 'Laboratory Fee';
                                if ($labFee->subjcode && $labFee->subjdesc) {
                                    $labFeeParticulars = "Laboratory Fee - {$labFee->subjcode} ({$labFee->subjdesc})";
                                } elseif ($labFee->subjcode) {
                                    $labFeeParticulars = "Laboratory Fee - {$labFee->subjcode}";
                                }

                                // Add lab fee as a tuition fee item with laboratory_fee_id
                                // Use classid and itemid from labfeesetup
                                $labFeeItem = (object) [
                                    'tuitiondetailid' => 'LAB_FEE_' . $labFee->laboratory_fee_id,
                                    'classid' => $labFeeClassId, // Get from labfeesetup
                                    'itemid' => $labFeeItemId, // Get from labfeesetup
                                    'particulars' => $labFeeParticulars,
                                    'priority' => 999,
                                    'amount' => $totalAmount, // Total = lab_amount + sum of items
                                    'pschemeid' => $labFeePschemeid,
                                    'istuition' => 0,
                                    'persubj' => 0,
                                    'permop' => 0,
                                    'permopid' => null,
                                    'laboratory_fee_id' => $labFee->laboratory_fee_id, // Add laboratory_fee_id for subject identification
                                    'lab_amount' => $labAmount // Store lab_amount separately for nested items
                                ];

                                // Add to tuition fees collection
                                $tuitionFees->push($labFeeItem);
                            }
                        }
                    }
                }

                // Get item management charges (aid plans, other items, etc.) - no payment schedule
                $itemManagementCharges = DB::table('item_management as im')
                    ->leftJoin('item_management_setup as ims', function ($join) {
                        $join->on('im.classid', '=', 'ims.classid')
                            ->on('im.itemid', '=', 'ims.itemid')
                            ->where('ims.deleted', '=', 0);
                    })
                    ->leftJoin('itemclassification as ic', 'im.classid', '=', 'ic.id')
                    ->leftJoin('items as i', 'im.itemid', '=', 'i.id')
                    ->select(
                        'im.id as item_management_id',
                        'im.classid',
                        'im.itemid',
                        'im.amount',
                        'im.isAidPlan',
                        'ic.description as class_description',
                        'i.description as item_description'
                    )
                    ->distinct() // Add distinct to avoid duplicates from item_management_setup join
                    ->where('im.studid', $studid)
                    ->where('im.syid', $schoolYear)
                    ->where('im.deleted', 0);

                // Apply semester filter only for non-basic level students
                if (!is_null($semester) && !$isBasicLevel) {
                    $itemManagementCharges->where('im.semid', $semester);
                }

                $itemManagementCharges = $itemManagementCharges->get();

                // Add item management charges as tuition fee items (no payment schedule)
                foreach ($itemManagementCharges as $itemCharge) {
                    if ($itemCharge->amount > 0) {
                        // Determine the classification name based on isAidPlan flag
                        $particulars = '';
                        if ($itemCharge->isAidPlan == 1) {
                            $particulars = 'AID PLAN';
                        }

                        // Add item description if available
                        if ($itemCharge->item_description) {
                            $particulars .= $itemCharge->item_description;
                        }

                        $itemManagementItem = (object) [
                            'tuitiondetailid' => 'ITEM_MGMT_' . $itemCharge->item_management_id,
                            'classid' => $itemCharge->classid,
                            'itemid' => $itemCharge->itemid,
                            'particulars' => $particulars,
                            'priority' => 998, // Just before lab fees (999)
                            'amount' => (float) $itemCharge->amount,
                            'pschemeid' => null, // No payment schedule for item management
                            'istuition' => 0,
                            'persubj' => 0,
                            'permop' => 0,
                            'permopid' => null,
                            'item_management_id' => $itemCharge->item_management_id,
                            'is_item_management' => true
                        ];

                        // Add to tuition fees collection
                        $tuitionFees->push($itemManagementItem);
                    }
                }

                // Get debit adjustments (charges that increase the fee amount)
                // This needs to be done EARLY so we can exclude adjustment payments from the payment queue
                \Log::debug('[DEBIT-ADJUSTMENTS-START] Query params - studid: ' . $studid . ', schoolYear: ' . $schoolYear . ', semester: ' . $semester . ', levelid: ' . $student->levelid);

                $debitQuery = DB::table('adjustmentdetails as ad')
                    ->join('adjustments as a', 'ad.headerid', '=', 'a.id')
                    ->leftJoin('itemclassification as ic', 'a.classid', '=', 'ic.id')
                    ->select(
                        'a.classid',
                        DB::raw('MAX(a.mop) as mop'),
                        DB::raw('MAX(ad.id) as adjustmentdetail_id'),
                        DB::raw("CONCAT('ADJUSTMENT: ', COALESCE(MAX(a.description), MAX(ic.description), 'Debit Adjustment')) as particulars"),
                        DB::raw('SUM(a.amount) as total_adjustment')
                    )
                    ->where('ad.studid', $studid)
                    ->where('a.syid', $schoolYear)
                    ->where('a.semid', $semester)
                    ->where('a.isdebit', 1)
                    ->where('a.amount', '>', 0)
                    ->where('ad.deleted', 0)
                    ->where('a.deleted', 0)
                    ->groupBy('a.classid');

                $debitAdjustments = $debitQuery->get()->keyBy('classid');

                \Log::debug('[DEBIT-ADJUSTMENTS] Query parameters: studid=' . $studid . ', schoolYear=' . $schoolYear . ', semester=' . $semester);
                \Log::debug('[DEBIT-ADJUSTMENTS] Found debit adjustments count: ' . count($debitAdjustments) . ', data: ' . json_encode($debitAdjustments));

                // Check if query found anything (debug logging)
                if (count($debitAdjustments) == 0) {
                    \Log::debug('[DEBIT-ADJUSTMENTS] No debit adjustments found, checking raw query...');
                    $rawQuery = DB::table('adjustmentdetails as ad')
                        ->join('adjustments as a', 'ad.headerid', '=', 'a.id')
                        ->where('ad.studid', $studid)
                        ->where('a.syid', $schoolYear)
                        ->where('a.semid', $semester)
                        ->where('a.isdebit', 1)
                        ->where('a.amount', '>', 0)
                        ->where('ad.deleted', 0)
                        ->where('a.deleted', 0)
                        ->select('a.*', 'ad.*')
                        ->get();
                    \Log::debug('[DEBIT-ADJUSTMENTS] Raw query result count: ' . count($rawQuery));
                    if (count($rawQuery) > 0) {
                        \Log::debug('[DEBIT-ADJUSTMENTS] Raw query data: ' . json_encode($rawQuery));
                    }
                }

                // Get book titles from bookentries first to determine which payments should be excluded
                // Only exclude payments that match specific book titles from bookentries
                $bookTitlesFromEntries = DB::table('bookentries as be')
                    ->leftJoin('items as i', 'be.bookid', '=', 'i.id')
                    ->select(DB::raw('COALESCE(i.description, CONCAT("Book #", be.bookid)) as title'))
                    ->where('be.studid', $studid)
                    ->where('be.syid', $schoolYear)
                    ->where(function ($q) use ($semester, $student) {
                        if ($student->levelid >= 17 && $student->levelid <= 25) {
                            $q->where('be.semid', $semester);
                        }
                    })
                    ->where('be.deleted', 0)
                    ->whereIn('be.bestatus', ['DRAFT', 'POSTED', 'APPROVED'])
                    ->pluck('title')
                    ->toArray();

                // Get detailed payments - in chronological order for sequential distribution
                // For laboratory fees, also get items from chrngtransitems to match by itemids
                $studentPaymentsRaw = DB::table('chrngtrans as ct')
                    ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
                    ->leftJoin('chrngtransitems as cti', function ($join) {
                        $join->on('ct.transno', '=', 'cti.chrngtransid')
                            ->where('cti.deleted', 0);
                    })
                    ->select(
                        'ct.ornum',
                        'ct.transdate',
                        'ct.transno',
                        'ct.totalamount',
                        'ct.amountpaid',
                        'ct.change_amount',
                        'cct.classid',
                        'cct.item_management_id',
                        'cct.amount',
                        'cct.particulars',
                        'cct.paymentsetupdetail_id',
                        'cct.syid as payment_syid',
                        DB::raw('GROUP_CONCAT(DISTINCT cti.itemid ORDER BY cti.itemid SEPARATOR ",") as itemids')
                    )
                    ->where('ct.studid', $studid)
                    ->where('ct.syid', $schoolYear)
                    // Only include payments recorded for the same school year as the active schedule;
                    // this prevents old-account payments (with a different syid) from being mixed into the active year.
                    ->where('cct.syid', $schoolYear)
                    ->where('cct.studid', $studid) // Filter chrngcashtrans by student ID
                    // For basic (level < 14), ignore semid; for SHS/College, match semid but allow NULL fallback
                    ->when(!is_null($semester) && $student->levelid >= 14, function ($q) use ($semester) {
                        $q->where(function ($q) use ($semester) {
                            $q->where('cct.semid', $semester)
                                ->orWhereNull('cct.semid');
                        });
                        $q->where(function ($q) use ($semester) {
                            $q->where('ct.semid', $semester)
                                ->orWhereNull('ct.semid');
                        });
                    })
                    ->where('ct.cancelled', 0)
                    ->where('cct.deleted', 0)
                    ->where('cct.amount', '>', 0)
                    ->groupBy(
                        'ct.ornum',
                        'ct.transdate',
                        'ct.transno',
                        'ct.totalamount',
                        'ct.amountpaid',
                        'ct.change_amount',
                        'cct.classid',
                        'cct.item_management_id',
                        'cct.amount',
                        'cct.particulars',
                        'cct.paymentsetupdetail_id'
                    )
                    ->orderBy('ct.transdate')
                    ->orderBy('ct.ornum')
                    ->get();

                // Extract adjustment descriptions from debit adjustments
                // This is done BEFORE building payment queue to exclude adjustment payments
                $adjustmentDescriptionsFromDebitAdjustments = [];
                foreach ($debitAdjustments as $adjustment) {
                    $adjustmentDesc = str_replace('ADJUSTMENT: ', '', $adjustment->particulars ?? '');
                    if ($adjustmentDesc) {
                        $adjustmentDescriptionsFromDebitAdjustments[] = $adjustmentDesc;
                    }
                }

                // Group payments by transaction to calculate overpayment per transaction
                $paymentsByTransaction = [];
                foreach ($studentPaymentsRaw as $payment) {
                    // Defensive guard: skip any payment rows whose recorded syid does not match the target school year
                    if (isset($payment->payment_syid) && (int) $payment->payment_syid !== (int) $schoolYear) {
                        continue;
                    }
                    $transno = $payment->transno;
                    if (!isset($paymentsByTransaction[$transno])) {
                        $paymentsByTransaction[$transno] = [
                            'ornum' => $payment->ornum,
                            'transdate' => $payment->transdate,
                            // Initialize with zero; we will recompute totals from the filtered items
                            'totalamount' => 0.0,
                            'amountpaid' => (float) ($payment->amountpaid ?? 0),
                            'change_amount' => (float) ($payment->change_amount ?? 0),
                            'items' => []
                        ];
                    } else {
                        // Track the max amountpaid across items in the same transaction
                        $paymentsByTransaction[$transno]['amountpaid'] = max(
                            $paymentsByTransaction[$transno]['amountpaid'],
                            (float) ($payment->amountpaid ?? 0)
                        );
                    }
                    $paymentsByTransaction[$transno]['items'][] = $payment;
                }

                // Calculate overpayment per transaction and distribute to first item
                $processedPayments = [];
                foreach ($paymentsByTransaction as $transno => $transaction) {
                    // Recompute totals only from the filtered items to avoid mixing other SY/SEM items
                    $includedItemsTotal = 0;
                    foreach ($transaction['items'] as $item) {
                        $includedItemsTotal += (float) ($item->amount ?? 0);
                    }
                    $totalAmount = $includedItemsTotal;
                    $amountPaid = (float) ($transaction['amountpaid'] ?? 0);
                    $changeAmount = $transaction['change_amount'];

                    // Calculate overpayment: actual amount paid minus the included items and change
                    $overpayment = max(0, $amountPaid - $includedItemsTotal - $changeAmount);

                    // Store overpayment for the first item in this transaction
                    $firstItemProcessed = false;
                    foreach ($transaction['items'] as $item) {
                        $itemAmount = (float) $item->amount;

                        // Add overpayment to the first item
                        if (!$firstItemProcessed && $overpayment > 0) {
                            $itemAmount += $overpayment;
                            $firstItemProcessed = true;
                        }

                        $processedPayments[] = [
                            'payment' => $item,
                            'adjusted_amount' => $itemAmount
                        ];
                    }
                }

                // Create payment queue by classification for sequential distribution
                // ONLY EXCLUDE book payments that match specific book titles from bookentries
                // AND EXCLUDE payments that match adjustment descriptions from debit adjustments
                // Generic "BOOKS" payments should flow through normal classid-based matching
                $paymentQueueByClass = [];
                foreach ($processedPayments as $processedPayment) {
                    $payment = $processedPayment['payment'];
                    $adjustedAmount = $processedPayment['adjusted_amount'];
                    $particulars = $payment->particulars ?? '';

                    // Check if this payment matches any specific book title from bookentries
                    $matchesSpecificBook = false;
                    foreach ($bookTitlesFromEntries as $bookTitle) {
                        // Only exclude if payment particulars contain a specific book title
                        if ($bookTitle && stripos($particulars, $bookTitle) !== false) {
                            $matchesSpecificBook = true;
                            break;
                        }
                    }

                    // Check if this payment matches any adjustment description
                    // This prevents adjustment payments from being matched to regular fees with same classid
                    $matchesAdjustment = false;
                    foreach ($adjustmentDescriptionsFromDebitAdjustments as $adjDesc) {
                        if ($adjDesc && stripos($particulars, $adjDesc) !== false) {
                            $matchesAdjustment = true;
                            break;
                        }
                    }

                    // Skip if it matches a specific book from bookentries OR an adjustment
                    if ($matchesSpecificBook || $matchesAdjustment) {
                        continue; // Skip this payment - it's for a specific book entry or adjustment
                    }

                    $classid = $payment->classid;
                    $paymentsetupdetailId = $payment->paymentsetupdetail_id ?? null;

                    if (!isset($paymentQueueByClass[$classid])) {
                        $paymentQueueByClass[$classid] = [];
                    }
                    $paymentQueueByClass[$classid][] = [
                        'ornum' => $payment->ornum,
                        'transdate' => $payment->transdate,
                        'transno' => $payment->transno,
                        'amount' => $adjustedAmount,
                        'remaining' => $adjustedAmount,
                        'paymentsetupdetail_id' => $paymentsetupdetailId,
                        'particulars' => $particulars,
                        'item_management_id' => $payment->item_management_id ?? null,
                        'itemids' => $payment->itemids ?? null // Include itemids from chrngtransitems for laboratory fee matching
                    ];

                    // Debug logging for laboratory fee payments
                    if ($classid == 18 && $paymentsetupdetailId) {
                        \Log::debug('[LAB-FEE-QUEUE-ADD] Added laboratory fee payment to queue', [
                            'classid' => $classid,
                            'paymentsetupdetail_id' => $paymentsetupdetailId,
                            'particulars' => $particulars,
                            'itemids' => $payment->itemids ?? null,
                            'amount' => $adjustedAmount
                        ]);
                    }
                }

                // Get all adjustment descriptions to identify adjustment payments
                $adjustmentDescriptions = DB::table('adjustments as a')
                    ->join('adjustmentdetails as ad', 'a.id', '=', 'ad.headerid')
                    ->select('a.description')
                    ->where('ad.studid', $studid)
                    ->where('a.syid', $schoolYear)
                    ->where('a.isdebit', 1)
                    ->where('a.deleted', 0)
                    ->where('ad.deleted', 0)
                    ->when($student->levelid >= 14 && $student->levelid <= 25, function ($q) use ($semester) {
                        $q->where('a.semid', $semester);
                    })
                    ->distinct()
                    ->pluck('description')
                    ->toArray();

                // Get adjustment payments (matched by particulars)
                // Store all payment particulars for matching with adjustments later
                // This needs to be done BEFORE building the general payments collection
                $adjustmentPaymentsByParticulars = DB::table('chrngcashtrans as cct')
                    ->join('chrngtrans as ct', 'cct.transno', '=', 'ct.transno')
                    ->select('cct.particulars', DB::raw('SUM(cct.amount) as total_paid'))
                    ->where('cct.studid', $studid)
                    ->where('ct.studid', $studid) // Also filter chrngtrans by student ID to avoid matching wrong transactions with same transno
                    ->where('cct.syid', $schoolYear)
                    ->where('cct.syid', $schoolYear)
                    ->where(function ($q) use ($semester) {
                        $q->where('cct.semid', $semester)
                            ->orWhereNull('cct.semid');
                    })
                    ->when(!is_null($semester) && $student->levelid >= 14, function ($q) use ($semester) {
                        $q->where(function ($subQ) use ($semester) {
                            $subQ->where('ct.semid', $semester)
                                ->orWhereNull('ct.semid');
                        });
                    })
                    ->where('cct.deleted', 0)
                    ->where('ct.cancelled', 0)
                    ->groupBy('cct.particulars')
                    ->get()
                    ->keyBy('particulars');

                // Get payments from chrngcashtrans (only non-cancelled transactions)
                // Exclude specific book entries and adjustment payments to prevent double counting
                // Generic "BOOKS" payments are now included (not excluded) to match with BOOKS classification
                // Now also group by paymentsetupdetail_id for more accurate payment matching
                $payments = DB::table('chrngcashtrans as cct')
                    ->join('chrngtrans as ct', 'cct.transno', '=', 'ct.transno')
                    ->select('cct.classid', 'cct.paymentsetupdetail_id', DB::raw('SUM(cct.amount) as total_paid'))
                    ->where('cct.studid', $studid)
                    ->where('ct.studid', $studid) // Also filter chrngtrans by student ID to avoid matching wrong transactions with same transno
                    ->where('cct.syid', $schoolYear)
                    ->where(function ($q) use ($semester) {
                        $q->where('cct.semid', $semester)
                            ->orWhereNull('cct.semid');
                    })
                    ->when(!is_null($semester) && $student->levelid >= 14, function ($q) use ($semester) {
                        $q->where(function ($subQ) use ($semester) {
                            $subQ->where('ct.semid', $semester)
                                ->orWhereNull('ct.semid');
                        });
                    })
                    ->where('cct.deleted', 0)
                    ->where('ct.cancelled', 0)
                    ->where(function ($q) use ($adjustmentDescriptions, $bookTitlesFromEntries, $adjustmentDescriptionsFromDebitAdjustments) {
                        // Only exclude payments that match specific book titles from bookentries
                        // Generic "BOOKS" payments should NOT be excluded
                        foreach ($bookTitlesFromEntries as $bookTitle) {
                            if ($bookTitle) {
                                $q->where('cct.particulars', 'NOT LIKE', '%' . $bookTitle . '%');
                            }
                        }

                        // Exclude adjustment payments (from adjustments table - deprecated approach)
                        foreach ($adjustmentDescriptions as $adjDesc) {
                            if ($adjDesc) {
                                $q->where('cct.particulars', 'NOT LIKE', '%' . $adjDesc . '%');
                            }
                        }

                        // Exclude payments that match adjustment descriptions from debit adjustments
                        // This prevents "PE UNIFORM" payment from being matched to "OTHER FEES" with same classid
                        foreach ($adjustmentDescriptionsFromDebitAdjustments as $adjDesc) {
                            if ($adjDesc) {
                                $q->where('cct.particulars', 'NOT LIKE', '%' . $adjDesc . '%');
                            }
                        }
                    })
                    ->groupBy('cct.classid', 'cct.paymentsetupdetail_id')
                    ->get();

                // Create a map for payments: classid => [paymentsetupdetail_id => total_paid]
                // This allows matching by both classid and paymentsetupdetail_id
                $paymentsByClassAndSchedule = [];
                foreach ($payments as $payment) {
                    $classid = $payment->classid;
                    $paymentsetupdetailId = $payment->paymentsetupdetail_id;

                    if (!isset($paymentsByClassAndSchedule[$classid])) {
                        $paymentsByClassAndSchedule[$classid] = [];
                    }

                    // Store payment by paymentsetupdetail_id, or use null key for payments without paymentsetupdetail_id
                    $key = $paymentsetupdetailId ?? 'no_schedule';
                    if (!isset($paymentsByClassAndSchedule[$classid][$key])) {
                        $paymentsByClassAndSchedule[$classid][$key] = 0;
                    }
                    $paymentsByClassAndSchedule[$classid][$key] += (float) $payment->total_paid;
                }

                // Also maintain backward compatibility: total by classid (for items without paymentsetupdetail_id)
                $payments = collect($payments)->groupBy('classid')->map(function ($group) {
                    return (object) [
                        'classid' => $group->first()->classid,
                        'total_paid' => $group->sum('total_paid')
                    ];
                })->keyBy('classid');

                // Get book payments (matched by particulars using LIKE for book titles)
                // ONLY include payments that match specific book titles from bookentries
                // Generic "BOOKS" payments are now handled by the normal payment queue
                $bookPaymentsByParticulars = collect();
                if (!empty($bookTitlesFromEntries)) {
                    $bookPaymentsByParticulars = DB::table('chrngcashtrans as cct')
                        ->join('chrngtrans as ct', 'cct.transno', '=', 'ct.transno')
                        ->select('cct.particulars', DB::raw('SUM(cct.amount) as total_paid'))
                        ->where('cct.studid', $studid)
                        ->where('ct.studid', $studid) // Also filter chrngtrans by student ID
                        ->where('cct.syid', $schoolYear)
                        ->where(function ($q) use ($semester) {
                            $q->where('cct.semid', $semester)
                                ->orWhereNull('cct.semid');
                        })
                        ->where('cct.deleted', 0)
                        ->where('ct.cancelled', 0)
                        ->where(function ($q) use ($bookTitlesFromEntries) {
                            // Only match payments with specific book titles from bookentries
                            foreach ($bookTitlesFromEntries as $bookTitle) {
                                if ($bookTitle) {
                                    $q->orWhere('cct.particulars', 'LIKE', '%' . $bookTitle . '%');
                                }
                            }
                        })
                        ->groupBy('cct.particulars')
                        ->get();
                }

                // Get discounts (they reduce the principal amount)
                // Only include POSTED discounts - unposted discounts don't affect student fees
                $discounts = DB::table('studdiscounts')
                    ->select('classid', DB::raw('SUM(discamount) as total_discount'))
                    ->where('studid', $studid)
                    ->where('syid', $schoolYear)
                    ->where(function ($q) use ($semester, $student) {
                        if ($student->levelid >= 17 && $student->levelid <= 25) {
                            $q->where('semid', $semester);
                        }
                    })
                    ->where('deleted', 0)
                    ->where('posted', 1)
                    ->groupBy('classid')
                    ->get()
                    ->keyBy('classid');

                // Initialize remaining discounts by class for priority-filled items
                $remainingDiscountsByClass = [];
                foreach ($discounts as $classid => $discount) {
                    $remainingDiscountsByClass[$classid] = (float) $discount->total_discount;
                }
                // Always initialize overpayment key to 0 to ensure fresh calculation
                $remainingDiscountsByClass['overpayment'] = 0;

                // NOTE: $debitAdjustments was already loaded earlier (after units calculation)
                // to allow exclusion of adjustment payments from the payment queue

                // Get credit adjustments (credits that decrease the fee amount)
                $creditAdjustments = DB::table('adjustmentdetails as ad')
                    ->join('adjustments as a', 'ad.headerid', '=', 'a.id')
                    ->leftJoin('itemclassification as ic', 'a.classid', '=', 'ic.id')
                    ->select(
                        'a.classid',
                        DB::raw("CONCAT('CREDIT ADJUSTMENT: ', COALESCE(MAX(a.description), MAX(ic.description), 'Credit Adjustment')) as particulars"),
                        DB::raw('SUM(a.amount) as total_adjustment')
                    )
                    ->where('ad.studid', $studid)
                    ->where('a.syid', $schoolYear)
                    ->where('a.semid', $semester)
                    ->where('a.iscredit', 1)
                    ->where('a.amount', '>', 0)
                    ->where('ad.deleted', 0)
                    ->where('a.deleted', 0)
                    ->groupBy('a.classid')
                    ->get()
                    ->keyBy('classid');

                // Track remaining credit adjustment per classification for sequential distribution
                // Credit adjustments are distributed priority-based (earliest due dates first)
                $remainingCreditAdjustmentsByClass = [];
                foreach ($creditAdjustments as $classid => $creditAdj) {
                    $remainingCreditAdjustmentsByClass[$classid] = (float) $creditAdj->total_adjustment;
                }

                // Build payment schedule for each fee item
                $studentSchedule = [];

                // Track which adjustment classids have been matched with fees
                $matchedAdjustmentClassids = [];

                // Group fees by payment setup (pschemeid) for sequential priority-based filling
                $feesByPaymentSetup = [];
                foreach ($tuitionFees as $fee) {
                    $pschemeid = $fee->pschemeid ?? 'no_schedule';
                    if (!isset($feesByPaymentSetup[$pschemeid])) {
                        $feesByPaymentSetup[$pschemeid] = [];
                    }
                    $feesByPaymentSetup[$pschemeid][] = $fee;
                }

                // Process each payment setup group
                foreach ($feesByPaymentSetup as $pschemeid => $feesInGroup) {
                    if ($studid == 9) {
                        \Log::info('[STUDENT-9-DEBUG] Processing pschemeid=' . $pschemeid, [
                            'fees_count' => count($feesInGroup),
                            'fees' => array_map(function ($f) {
                                return [
                                    'classid' => $f->classid,
                                    'particulars' => $f->particulars,
                                    'amount' => $f->amount,
                                    'tuitiondetailid' => $f->tuitiondetailid ?? null
                                ];
                            }, $feesInGroup)
                        ]);
                    }

                    // Get labFeeClassId for laboratory fee matching (used in both multi-schedule and one-time payment logic)
                    $labFeeSetup = DB::table('labfeesetup')
                        ->where('deleted', 0)
                        ->first();
                    $labFeeClassId = $labFeeSetup ? $labFeeSetup->classid : null;
                    $labFeeItemId = $labFeeSetup ? $labFeeSetup->itemid : null;

                    // Get payment schedule for this group
                    $paymentSchedule = collect();
                    $hasEmptyPercentAmount = false; // Initialize flag

                    if ($pschemeid !== 'no_schedule' && $pschemeid) {
                        $paymentSchedule = DB::table('paymentsetupdetail')
                            ->select('id as paymentsetupdetail_id', 'paymentno', 'duedate', 'percentamount', 'description')
                            ->where('paymentid', $pschemeid)
                            ->where('deleted', 0)
                            ->orderBy('paymentno')
                            ->get();

                        // Check if any schedule items have null/empty percentamount - this triggers custom payments lookup
                        // We'll check per payment_no when processing each schedule item
                        $hasEmptyPercentAmount = $paymentSchedule->contains(function ($schedule) {
                            return empty($schedule->percentamount) || is_null($schedule->percentamount);
                        });

                        \Log::debug('[PAYMENT-SCHEDULE] hasEmptyPercentAmount=' . ($hasEmptyPercentAmount ? 'yes' : 'no') . ' for pschemeid=' . $pschemeid);

                        // DEBUG: Log payment schedule for this fee
                        \Log::debug('[PAYMENT-SCHEDULE] pschemeid=' . $pschemeid . ', classid=' . ($fee->classid ?? 'none') . ', count=' . $paymentSchedule->count() . ', schedules: ' . json_encode($paymentSchedule->toArray()));

                        // Also add to debug output for easy viewing
                        if (!isset($debugInfo['payment_schedule_from_db'])) {
                            $debugInfo['payment_schedule_from_db'] = [];
                        }
                        $debugInfo['payment_schedule_from_db'][] = [
                            'pschemeid' => $pschemeid,
                            'classid' => $fee->classid ?? 'none',
                            'count' => $paymentSchedule->count(),
                            'schedules' => $paymentSchedule->toArray()
                        ];
                    }

                    $scheduleCount = $paymentSchedule->count();
                    $hasMultipleSchedules = $scheduleCount > 1;

                    // If fees have a multi-month payment setup, use sequential priority-based filling
                    // NOTE: Temporarily forcing this logic for ALL multi-month schedules to debug
                    if ($hasMultipleSchedules) {
                        // Sort fees by priority (classcode) - lower number = higher priority
                        usort($feesInGroup, function ($a, $b) {
                            $priorityA = $a->priority ?? 999;
                            $priorityB = $b->priority ?? 999;
                            return $priorityA <=> $priorityB;
                        });

                        // Calculate total amounts for each fee with adjustments
                        $feeItems = [];
                        $totalGroupAmount = 0;

                        // First, consolidate fees by classid + tuitiondetailid (to prevent merging distinct lab fees)
                        // For laboratory fees, each has a unique tuitiondetailid (e.g., LAB_FEE_9, LAB_FEE_10)
                        // even though they share the same classid
                        $consolidatedFees = [];
                        foreach ($feesInGroup as $fee) {
                            $classid = $fee->classid;
                            $tuitiondetailid = $fee->tuitiondetailid ?? '';
                            // Create unique key: for laboratory fees, use tuitiondetailid; for others, use classid
                            $consolidationKey = $tuitiondetailid ?: $classid;
                            if (!isset($consolidatedFees[$consolidationKey])) {
                                $consolidatedFees[$consolidationKey] = $fee;
                            }
                        }

                        if ($studid == 9) {
                            \Log::info('[STUDENT-9-DEBUG] After consolidation', [
                                'pschemeid' => $pschemeid,
                                'consolidated_count' => count($consolidatedFees),
                                'consolidated_fees' => array_map(function ($f) {
                                    return [
                                        'classid' => $f->classid,
                                        'particulars' => $f->particulars,
                                        'amount' => $f->amount,
                                        'tuitiondetailid' => $f->tuitiondetailid ?? null
                                    ];
                                }, $consolidatedFees)
                            ]);
                        }

                        // Fetch custom payments for all tuition details in this group
                        // Only fetch if paymentsetupdetail has null/empty percentamount (triggers custom payments)
                        // Key: "tuition_detail_id|payment_no" => custom payment data
                        $customPaymentsMap = [];
                        if ($feesId && $hasEmptyPercentAmount) {
                            $tuitionDetailIds = collect($consolidatedFees)->pluck('tuitiondetailid')->filter()->toArray();
                            if (!empty($tuitionDetailIds)) {
                                $customPayments = DB::table('custom_payments')
                                    ->select('id', 'tuition_header_id', 'tuition_detail_id', 'payment_no', 'amount', 'due_date')
                                    ->where('tuition_header_id', $feesId)
                                    ->whereIn('tuition_detail_id', $tuitionDetailIds)
                                    ->get();

                                foreach ($customPayments as $customPayment) {
                                    // payment_no in custom_payments table stores paymentsetupdetail_id, not paymentno
                                    $key = $customPayment->tuition_detail_id . '|' . $customPayment->payment_no;
                                    $customPaymentsMap[$key] = [
                                        'amount' => (float) $customPayment->amount,
                                        'due_date' => $customPayment->due_date,
                                        'id' => $customPayment->id
                                    ];
                                }

                                \Log::debug('[CUSTOM-PAYMENTS] Found ' . count($customPayments) . ' custom payments for feesId=' . $feesId . ', tuitionDetailIds=' . json_encode($tuitionDetailIds) . ' (triggered by empty percentamount)');
                                \Log::debug('[CUSTOM-PAYMENTS] Map keys: ' . json_encode(array_keys($customPaymentsMap)));
                            }
                        }

                        foreach ($consolidatedFees as $fee) {
                            $baseAmount = (float) $fee->amount;
                            $classid = $fee->classid;
                            $particulars = $fee->particulars;

                            // Calculate total amount based on fee type (following resetv3_generatefees logic)
                            $totalAmount = $baseAmount;

                            // For college students (level 17-25)
                            if ($student->levelid >= 17 && $student->levelid <= 25) {
                                // If perunit=1 or istuition=1, multiply by units
                                // perunit indicates the fee should be multiplied by units
                                if (($fee->perunit == 1 || $fee->istuition == 1) && $units > 0) {
                                    $totalAmount = $baseAmount * $units;
                                    $particulars .= ' | ' . $units . ' Units';
                                } elseif (($fee->perunit == 1 || $fee->istuition == 1) && $units == 0) {
                                    // If perunit/istuition but no units, set amount to 0 (student has no enrolled units)
                                    $totalAmount = 0;
                                }

                                // If persubj=1, multiply by number of subjects
                                if ($fee->persubj == 1) {
                                    $subjectCount = DB::table('college_studsched as css')
                                        ->join('college_classsched as cs', 'css.schedid', '=', 'cs.id')
                                        ->where('css.studid', $studid)
                                        ->where('cs.syid', $schoolYear)
                                        ->where('cs.semesterID', $semester)
                                        ->where('css.deleted', 0)
                                        ->where('css.dropped', 0)
                                        ->where('cs.deleted', 0)
                                        ->distinct('cs.subjectID')
                                        ->count('cs.subjectID');

                                    if ($subjectCount > 0) {
                                        $totalAmount *= $subjectCount;
                                    }
                                }

                                // If permop=1, multiply by number of payment months
                                if ($fee->permop == 1 && $fee->permopid) {
                                    $paymentSetup = DB::table('paymentsetup')
                                        ->where('id', $fee->permopid)
                                        ->first();

                                    if ($paymentSetup && $paymentSetup->noofpayment) {
                                        $totalAmount *= $paymentSetup->noofpayment;
                                    }
                                }
                            }

                            // Get discount for this classid
                            $totalDiscount = $discounts->has($classid) ? (float) $discounts->get($classid)->total_discount : 0;

                            // Debit adjustments are now processed as standalone items, not merged with fees
                            $adjustmentAmount = 0;

                            // Get credit adjustment for this classid (will be subtracted from the amount)
                            $creditAdjustmentAmount = 0;
                            if ($creditAdjustments->has($classid)) {
                                $creditAdjustmentAmount = (float) $creditAdjustments->get($classid)->total_adjustment;
                                $matchedAdjustmentClassids[] = $classid;
                            }

                            // Add debit adjustment and subtract credit adjustment from total amount
                            $totalAmount += $adjustmentAmount;
                            $totalAmount -= $creditAdjustmentAmount;

                            // Store fee item details for sequential filling
                            $feeItems[] = [
                                'classid' => $classid,
                                'particulars' => $particulars,
                                'total_amount' => $totalAmount,
                                'adjustment' => 0, // Debit adjustments are now standalone
                                'credit_adjustment' => $creditAdjustmentAmount,
                                'discount' => $totalDiscount,
                                'remaining' => $totalAmount, // Track remaining amount to be filled
                                'tuitiondetailid' => $fee->tuitiondetailid ?? null, // Include tuitiondetail_id
                                'laboratory_fee_id' => $fee->laboratory_fee_id ?? null, // Include laboratory_fee_id for laboratory fees
                                'istuition' => $fee->istuition ?? 0, // Include istuition flag to identify tuition fees
                                'item_management_id' => $fee->item_management_id ?? null, // Keep item management linkage
                            ];

                            $totalGroupAmount += $totalAmount;
                        }

                        if ($studid == 9) {
                            \Log::info('[STUDENT-9-DEBUG] Fee items built', [
                                'pschemeid' => $pschemeid,
                                'fee_items_count' => count($feeItems),
                                'total_group_amount' => $totalGroupAmount,
                                'fee_items' => array_map(function ($item) {
                                    return [
                                        'classid' => $item['classid'],
                                        'particulars' => $item['particulars'],
                                        'total_amount' => $item['total_amount'],
                                        'remaining' => $item['remaining'],
                                        'tuitiondetailid' => $item['tuitiondetailid'] ?? null
                                    ];
                                }, $feeItems)
                            ]);
                        }

                        // Check if we have custom payments for all fees
                        // If custom payments exist, we'll use them instead of equal distribution
                        $hasCustomPayments = !empty($customPaymentsMap);

                        // Calculate monthly quota - divide equally among all months (same as getMonthlyAssessment)
                        // This will be used as fallback if custom payments don't cover all fees
                        $monthlyQuota = $scheduleCount > 0 ? $totalGroupAmount / $scheduleCount : 0;

                        // Now fill each payment month sequentially with items in priority order
                        // DEBUG: Log before priority-based loop
                        \Log::debug('[PRIORITY-LOOP] pschemeid=' . $pschemeid . ', scheduleCount=' . $scheduleCount . ', hasCustomPayments=' . ($hasCustomPayments ? 'yes' : 'no') . ', paymentSchedule items: ' . json_encode($paymentSchedule->toArray()));

                        $loopIteration = 0;
                        foreach ($paymentSchedule as $schedule) {
                            $loopIteration++;
                            // DEBUG: Log each iteration
                            \Log::debug('[PRIORITY-LOOP] Iteration ' . $loopIteration . ': paymentno=' . $schedule->paymentno . ', duedate=' . $schedule->duedate . ', id=' . ($schedule->paymentsetupdetail_id ?? $schedule->id ?? 'none'));

                            // Check if this schedule item has null/empty percentamount (triggers custom payments)
                            $scheduleHasEmptyPercentAmount = empty($schedule->percentamount) || is_null($schedule->percentamount);
                            $paymentsetupdetailId = $schedule->paymentsetupdetail_id ?? null;

                            // Determine if we should use custom payments or equal distribution
                            // Use custom payments if available for this payment_no, otherwise use equal distribution
                            $monthQuota = $monthlyQuota; // Default to equal distribution
                            $customDueDate = $schedule->duedate; // Default to schedule due date
                            $monthItems = []; // Items included in this month

                            // NEW LOGIC: For custom_payments (empty percentamount), collect ALL classifications with custom payments
                            // Instead of priority-based filling, get the amount for each classification from custom_payments
                            $customPaymentsAppliedThisSchedule = false; // Track if any custom payments were applied

                            if ($scheduleHasEmptyPercentAmount && $hasCustomPayments) {
                                // For custom_payments, loop through ALL feeItems and get their custom payment amounts
                                foreach ($feeItems as &$feeItem) {
                                    $tuitionDetailId = $feeItem['tuitiondetailid'] ?? null;

                                    // Check if there's a custom payment for this fee and paymentsetupdetail_id
                                    // Note: custom_payments.payment_no stores paymentsetupdetail_id, not paymentno
                                    $customPaymentKey = $tuitionDetailId && $paymentsetupdetailId ? ($tuitionDetailId . '|' . $paymentsetupdetailId) : null;
                                    $hasCustomPaymentForThisFee = $customPaymentKey && isset($customPaymentsMap[$customPaymentKey]);

                                    if ($hasCustomPaymentForThisFee) {
                                        $customPaymentsAppliedThisSchedule = true;
                                        // Use custom amount for this specific fee and payment_no
                                        $customPaymentData = $customPaymentsMap[$customPaymentKey] ?? null;

                                        // Safety check: ensure custom payment data exists
                                        if (!$customPaymentData) {
                                            \Log::warning('[CUSTOM-PAYMENT] Custom payment key not found: ' . $customPaymentKey);
                                            continue; // Skip this fee item if custom payment data is missing
                                        }

                                        $customAmount = $customPaymentData['amount'] ?? 0;
                                        $customDueDate = $customPaymentData['due_date'] ?? $schedule->duedate;

                                        // Store original amount for logging
                                        $originalCustomAmount = $customAmount;

                                        // For college students with tuition fees (istuition=1), multiply custom amount by units
                                        // The custom_amount in custom_payments is per unit, so we need to multiply by units
                                        if ($student->levelid >= 17 && $student->levelid <= 25 && ($feeItem['istuition'] ?? 0) == 1 && $units > 0) {
                                            $customAmount = $customAmount * $units;
                                            \Log::debug('[CUSTOM-PAYMENT] Tuition fee detected - multiplying custom amount by units: ' . $originalCustomAmount . ' * ' . $units . ' = ' . $customAmount);
                                        }

                                        // Use custom amount, but don't exceed remaining amount
                                        $amountToFill = min($feeItem['remaining'], $customAmount);

                                        \Log::debug('[CUSTOM-PAYMENT] Using custom payment for tuition_detail_id=' . $tuitionDetailId . ', paymentsetupdetail_id=' . $paymentsetupdetailId . ', paymentno=' . $schedule->paymentno . ', custom_amount=' . $customAmount . ', amount_to_fill=' . $amountToFill . ' (percentamount is empty)');

                                        if ($amountToFill > 0) {
                                            // Store custom due date if available for this fee and payment_no
                                            $itemDueDate = $customDueDate;

                                            $monthItems[] = [
                                                'classid' => $feeItem['classid'],
                                                'particulars' => $feeItem['particulars'],
                                                'pschemeid' => $pschemeid,
                                                'amount' => $amountToFill,
                                                'adjustment' => 0, // Debit adjustments are now standalone
                                                'credit_adjustment' => $feeItem['credit_adjustment'],
                                                'discount' => $feeItem['discount'],
                                                'tuitiondetailid' => $feeItem['tuitiondetailid'] ?? null, // Include tuitiondetail_id
                                                'laboratory_fee_id' => $feeItem['laboratory_fee_id'] ?? null, // Include laboratory_fee_id for laboratory fees
                                                'item_management_id' => $feeItem['item_management_id'] ?? null, // Preserve item management ID
                                                'due_date' => $itemDueDate, // Store custom due date if available
                                            ];

                                            $feeItem['remaining'] -= $amountToFill;
                                        }
                                    }
                                }
                                unset($feeItem); // Break reference
                            }

                            // FALLBACK: If custom payments exist but none were applied to this schedule item,
                            // use equal distribution instead (for fees that don't have matching custom payments)
                            if ($scheduleHasEmptyPercentAmount && $hasCustomPayments && !$customPaymentsAppliedThisSchedule) {
                                // Use equal distribution for all fees since no custom payments matched this schedule item
                                foreach ($feeItems as &$feeItem) {
                                    $tuitionDetailId = $feeItem['tuitiondetailid'] ?? null;

                                    if ($feeItem['remaining'] > 0 && $monthQuota > 0) {
                                        $amountToFill = min($feeItem['remaining'], $monthQuota);
                                    } else {
                                        continue; // Skip if no remaining amount or quota
                                    }

                                    if ($amountToFill > 0) {
                                        $monthItems[] = [
                                            'classid' => $feeItem['classid'],
                                            'particulars' => $feeItem['particulars'],
                                            'pschemeid' => $pschemeid,
                                            'amount' => $amountToFill,
                                            'adjustment' => 0,
                                            'credit_adjustment' => $feeItem['credit_adjustment'],
                                            'discount' => $feeItem['discount'],
                                            'tuitiondetailid' => $feeItem['tuitiondetailid'] ?? null,
                                            'laboratory_fee_id' => $feeItem['laboratory_fee_id'] ?? null,
                                            'item_management_id' => $feeItem['item_management_id'] ?? null,
                                            'due_date' => $schedule->duedate,
                                        ];

                                        $feeItem['remaining'] -= $amountToFill;
                                        $monthQuota -= $amountToFill;
                                    }
                                }
                                unset($feeItem); // Break reference
                            } else if (!$scheduleHasEmptyPercentAmount || !$hasCustomPayments) {
                                // ORIGINAL LOGIC: Fill this month with items in priority order (for percentage-based or equal distribution)
                                foreach ($feeItems as &$feeItem) {
                                    $tuitionDetailId = $feeItem['tuitiondetailid'] ?? null;

                                    // Use equal distribution (original logic) or percentage-based if percentamount exists
                                    if ($feeItem['remaining'] > 0 && $monthQuota > 0) {
                                        // If percentamount exists, use percentage-based calculation
                                        if (!$scheduleHasEmptyPercentAmount && $schedule->percentamount > 0) {
                                            // Calculate amount based on percentage
                                            $percentageAmount = ($feeItem['total_amount'] * $schedule->percentamount) / 100;
                                            $amountToFill = min($feeItem['remaining'], $percentageAmount);
                                            \Log::debug('[PERCENTAGE-PAYMENT] Using percentage for tuition_detail_id=' . $tuitionDetailId . ', payment_no=' . $schedule->paymentno . ', percentamount=' . $schedule->percentamount . ', percentage_amount=' . $percentageAmount);
                                        } else {
                                            // Use equal distribution
                                            $amountToFill = min($feeItem['remaining'], $monthQuota);
                                        }
                                    } else {
                                        continue; // Skip if no remaining amount or quota
                                    }

                                    if ($amountToFill > 0) {
                                        if ($feeItem['classid'] == 7) {
                                            \Log::debug('[MONTH-FILL-ROBOTICS] Month ' . $schedule->paymentno . ': filling ROBOTICS with ' . $amountToFill . ', remaining before: ' . $feeItem['remaining']);
                                        }

                                        // Store custom due date if available for this fee and payment_no
                                        $itemDueDate = $schedule->duedate; // Default to schedule due date

                                        $monthItems[] = [
                                            'classid' => $feeItem['classid'],
                                            'particulars' => $feeItem['particulars'],
                                            'pschemeid' => $pschemeid,
                                            'amount' => $amountToFill,
                                            'adjustment' => 0, // Debit adjustments are now standalone
                                            'credit_adjustment' => $feeItem['credit_adjustment'],
                                            'discount' => $feeItem['discount'],
                                            'tuitiondetailid' => $feeItem['tuitiondetailid'] ?? null, // Include tuitiondetail_id
                                            'laboratory_fee_id' => $feeItem['laboratory_fee_id'] ?? null, // Include laboratory_fee_id for laboratory fees
                                            'item_management_id' => $feeItem['item_management_id'] ?? null, // Preserve item management ID
                                            'due_date' => $itemDueDate, // Store custom due date if available
                                        ];

                                        $feeItem['remaining'] -= $amountToFill;
                                        $monthQuota -= $amountToFill;
                                    }
                                }
                                unset($feeItem); // Break reference
                            }

                            // Calculate payments and payment_details for this due date
                            $totalPaymentsForDueDate = 0;

                            // Create separate payment schedule rows for each particular included in this month
                            foreach ($monthItems as $monthItem) {
                                // Reset payment details for this specific month item to avoid mixing payments from different fees
                                $paymentDetails = [];

                                $classid = $monthItem['classid'];
                                $classificationAmount = $monthItem['amount'];

                                // Get debit adjustment for this classification (should be proportional to the amount)
                                $adjustmentForClass = 0;
                                if (isset($monthItem['adjustment']) && $monthItem['adjustment'] > 0) {
                                    // Calculate proportional adjustment based on the amount filled
                                    $totalFeeAmount = 0;
                                    foreach ($feeItems as $item) {
                                        if ($item['classid'] == $classid) {
                                            $totalFeeAmount = $item['total_amount'] + $item['adjustment'] - $item['credit_adjustment'];
                                            break;
                                        }
                                    }
                                    if ($totalFeeAmount > 0) {
                                        $adjustmentForClass = ($classificationAmount / $totalFeeAmount) * $monthItem['adjustment'];
                                    }
                                }

                                // Apply payments sequentially from the payment queue
                                // Prioritize payments with matching paymentsetupdetail_id
                                // For laboratory fees, also match by particulars and itemids
                                $actualPaymentForClass = 0;
                                $appliedOrNumbers = [];
                                $schedulePaymentsetupdetailId = $schedule->paymentsetupdetail_id ?? null;
                                $scheduleItemMgmtId = $monthItem['item_management_id'] ?? null;
                                $isItemManagement = !empty($scheduleItemMgmtId);

                                // Check if this is a laboratory fee (classid matches labFeeClassId)
                                $isLaboratoryFee = ($labFeeClassId && $classid == $labFeeClassId);
                                $monthItemParticulars = $monthItem['particulars'] ?? '';
                                // For laboratory fees, remove suffix from particulars for matching
                                $monthItemParticularsBase = $isLaboratoryFee ? preg_replace('/\s*-\s*(DOWNPAYMENT|1ST\s+MONTH|2ND\s+MONTH|3RD\s+MONTH|4TH\s+MONTH|5TH\s+MONTH|6TH\s+MONTH|7TH\s+MONTH|8TH\s+MONTH|9TH\s+MONTH|10TH\s+MONTH|FINAL\s+PAYMENT|\d+(ST|ND|RD|TH)\s+PAYMENT|\d+(ST|ND|RD|TH)\s+MONTH|ONE\s+TIME\s+PAYMENT|NO\s+DUE\s+DATE|Due\s+\w+\s+\d+,\s+\d{4}|January|February|March|April|May|June|July|August|September|October|November|December)$/i', '', $monthItemParticulars) : $monthItemParticulars;
                                $monthItemParticularsBase = trim($monthItemParticularsBase);

                                if ($studid == 2 && $isLaboratoryFee) {
                                    if (!isset($GLOBALS['student2_month_items'])) {
                                        $GLOBALS['student2_month_items'] = [];
                                    }
                                    $GLOBALS['student2_month_items'][] = [
                                        'particulars' => $monthItemParticularsBase,
                                        'schedule_detail_id' => $schedulePaymentsetupdetailId,
                                        'has_payment_queue' => isset($paymentQueueByClass[$classid]),
                                        'queue_count' => isset($paymentQueueByClass[$classid]) ? count($paymentQueueByClass[$classid]) : 0
                                    ];
                                }

                                \Log::debug('[LAB-FEE-MONTH-ITEM] Processing month item', [
                                    'classid' => $classid,
                                    'is_laboratory_fee' => $isLaboratoryFee,
                                    'month_item_particulars' => $monthItemParticulars,
                                    'month_item_particulars_base' => $monthItemParticularsBase,
                                    'laboratory_fee_id' => $monthItem['laboratory_fee_id'] ?? null,
                                    'schedule_paymentsetupdetail_id' => $schedulePaymentsetupdetailId
                                ]);

                                // Get itemids from nested items for laboratory fee matching
                                $monthItemItemids = [];
                                if ($isLaboratoryFee && isset($monthItem['laboratory_fee_id'])) {
                                    // Get itemids from laboratory_fee_items for this laboratory_fee_id
                                    $labFeeItemids = DB::table('laboratory_fee_items as lfi')
                                        ->join('labfees as lf', 'lfi.laboratory_fee_id', '=', 'lf.id')
                                        ->where('lf.id', $monthItem['laboratory_fee_id'])
                                        ->where('lfi.deleted', 0)
                                        ->where('lf.deleted', 0)
                                        ->pluck('lfi.item_id')
                                        ->toArray();

                                    // Also add the lab fee itemid from labfeesetup
                                    if ($labFeeItemId) {
                                        $monthItemItemids[] = $labFeeItemId;
                                    }
                                    $monthItemItemids = array_merge($monthItemItemids, $labFeeItemids);
                                    $monthItemItemids = array_unique($monthItemItemids);
                                    sort($monthItemItemids);

                                    \Log::debug('[LAB-FEE-ITEMIDS-BUILD] Built itemids for month item', [
                                        'laboratory_fee_id' => $monthItem['laboratory_fee_id'],
                                        'labFeeItemId_from_setup' => $labFeeItemId,
                                        'labFeeItemids_from_table' => $labFeeItemids,
                                        'final_month_item_itemids' => $monthItemItemids
                                    ]);
                                } else {
                                    \Log::debug('[LAB-FEE-ITEMIDS-SKIP] Skipping itemids build', [
                                        'is_laboratory_fee' => $isLaboratoryFee,
                                        'has_laboratory_fee_id' => isset($monthItem['laboratory_fee_id']),
                                        'laboratory_fee_id_value' => $monthItem['laboratory_fee_id'] ?? 'NOT SET'
                                    ]);
                                }

                                if (isset($paymentQueueByClass[$classid]) && !empty($paymentQueueByClass[$classid])) {
                                    $amountDueForClass = $classificationAmount + $adjustmentForClass;
                                    $remainingDue = $amountDueForClass;

                                    \Log::debug('[LAB-FEE-PAYMENT-QUEUE] Payment queue for classid=' . $classid, [
                                        'queue_count' => count($paymentQueueByClass[$classid]),
                                        'queue_items' => array_map(function ($p) {
                                            return [
                                                'paymentsetupdetail_id' => $p['paymentsetupdetail_id'] ?? null,
                                                'particulars' => $p['particulars'] ?? null,
                                                'itemids' => $p['itemids'] ?? null,
                                                'amount' => $p['amount'] ?? null,
                                                'remaining' => $p['remaining'] ?? null
                                            ];
                                        }, $paymentQueueByClass[$classid])
                                    ]);

                                    // First, try to match payments with the same paymentsetupdetail_id
                                    // For laboratory fees, also match by particulars and itemids
                                    $matchedPaymentIndices = [];
                                    $unmatchedPaymentIndices = [];

                                    foreach ($paymentQueueByClass[$classid] as $index => $payment) {
                                        if ($payment['remaining'] > 0) {
                                            $paymentMatches = false;

                                            // Restrict payments to matching item_management_id when applicable
                                            $paymentItemMgmtId = $payment['item_management_id'] ?? null;
                                            if ($isItemManagement) {
                                                if ($paymentItemMgmtId != $scheduleItemMgmtId) {
                                                    // Payment is for a different item management entry
                                                    continue;
                                                }
                                            } else {
                                                // Non item-management fees should not consume item-management-tagged payments
                                                if (!empty($paymentItemMgmtId)) {
                                                    continue;
                                                }
                                            }

                                            if (
                                                $schedulePaymentsetupdetailId &&
                                                isset($payment['paymentsetupdetail_id']) &&
                                                $payment['paymentsetupdetail_id'] == $schedulePaymentsetupdetailId
                                            ) {
                                                // For laboratory fees, also check particulars and itemids
                                                if ($isLaboratoryFee) {
                                                    $paymentParticulars = $payment['particulars'] ?? '';
                                                    $paymentParticularsBase = preg_replace('/\s*-\s*(DOWNPAYMENT|1ST\s+MONTH|2ND\s+MONTH|3RD\s+MONTH|4TH\s+MONTH|5TH\s+MONTH|6TH\s+MONTH|7TH\s+MONTH|8TH\s+MONTH|9TH\s+MONTH|10TH\s+MONTH|FINAL\s+PAYMENT|\d+(ST|ND|RD|TH)\s+PAYMENT|\d+(ST|ND|RD|TH)\s+MONTH|ONE\s+TIME\s+PAYMENT|NO\s+DUE\s+DATE|Due\s+\w+\s+\d+,\s+\d{4}|January|February|March|April|May|June|July|August|September|October|November|December)$/i', '', $paymentParticulars);
                                                    $paymentParticularsBase = trim($paymentParticularsBase);

                                                    // Check particulars match
                                                    $particularsMatch = ($paymentParticularsBase === $monthItemParticularsBase);

                                                    // Debug logging for IT 102 issue
                                                    if ($studid == 2 && $classid == 5) {
                                                        if (!isset($GLOBALS['student2_lab_debug'])) {
                                                            $GLOBALS['student2_lab_debug'] = [];
                                                        }
                                                        $GLOBALS['student2_lab_debug'][] = [
                                                            'payment_particulars' => $paymentParticularsBase,
                                                            'month_item_particulars' => $monthItemParticularsBase,
                                                            'match' => $particularsMatch ? 'YES' : 'NO',
                                                            'payment_amt' => $payment['amount']
                                                        ];
                                                    }

                                                    // Check itemids match
                                                    // For laboratory fees, accept PARTIAL matches (payment itemids must be subset of expected itemids)
                                                    // This allows paying for individual nested items (e.g., only RLE or only LAB FEE)
                                                    $itemidsMatch = false;
                                                    if (!empty($monthItemItemids) && !empty($payment['itemids']) && $payment['itemids'] !== '') {
                                                        $paymentItemids = explode(',', $payment['itemids']);
                                                        $paymentItemids = array_map('intval', array_filter($paymentItemids));
                                                        sort($paymentItemids);

                                                        // Check if payment itemids are a subset of month item itemids
                                                        // This allows partial payments (paying only some of the items)
                                                        $itemidsMatch = empty(array_diff($paymentItemids, $monthItemItemids));

                                                        // Debug logging for laboratory fee matching
                                                        \Log::debug('[LAB-FEE-PAYMENT-MATCH] Checking payment match', [
                                                            'payment_particulars' => $paymentParticulars,
                                                            'payment_particulars_base' => $paymentParticularsBase,
                                                            'month_item_particulars_base' => $monthItemParticularsBase,
                                                            'particulars_match' => $particularsMatch,
                                                            'payment_itemids' => $paymentItemids,
                                                            'month_item_itemids' => $monthItemItemids,
                                                            'itemids_match' => $itemidsMatch,
                                                            'is_subset' => empty(array_diff($paymentItemids, $monthItemItemids)),
                                                            'paymentsetupdetail_id' => $schedulePaymentsetupdetailId,
                                                            'payment_paymentsetupdetail_id' => $payment['paymentsetupdetail_id']
                                                        ]);
                                                    } elseif (empty($monthItemItemids) && (empty($payment['itemids']) || $payment['itemids'] === '')) {
                                                        // If both are empty, consider it a match (no itemids to check)
                                                        $itemidsMatch = true;

                                                        // Debug logging
                                                        \Log::debug('[LAB-FEE-PAYMENT-MATCH] No itemids to check, matching by particulars only', [
                                                            'payment_particulars_base' => $paymentParticularsBase,
                                                            'month_item_particulars_base' => $monthItemParticularsBase,
                                                            'particulars_match' => $particularsMatch
                                                        ]);
                                                    }

                                                    // For laboratory fees, require particulars match and itemids match (if both available)
                                                    // If itemids are not available on either side, just match by particulars
                                                    \Log::debug('[LAB-FEE-PAYMENT-MATCH] Checking laboratory fee match', [
                                                        'paymentsetupdetail_id' => $payment['paymentsetupdetail_id'] ?? null,
                                                        'payment_particulars' => $paymentParticulars,
                                                        'payment_particulars_base' => $paymentParticularsBase,
                                                        'month_item_particulars' => $monthItemParticulars,
                                                        'month_item_particulars_base' => $monthItemParticularsBase,
                                                        'particulars_match' => $particularsMatch
                                                    ]);

                                                    // For laboratory fees, REQUIRE particulars to match
                                                    // The particulars uniquely identify each laboratory fee by subject
                                                    if ($particularsMatch) {
                                                        // Particulars match - this is the correct laboratory fee
                                                        $paymentMatches = true;
                                                        \Log::debug('[LAB-FEE-PAYMENT-MATCH] Payment matched by particulars', [
                                                            'payment_particulars_base' => $paymentParticularsBase,
                                                            'month_item_particulars_base' => $monthItemParticularsBase
                                                        ]);
                                                    } else {
                                                        // Particulars don't match - this payment is for a different laboratory fee
                                                        $paymentMatches = false;
                                                        \Log::debug('[LAB-FEE-PAYMENT-MATCH] Payment NOT matched - particulars do not match', [
                                                            'payment_particulars_base' => $paymentParticularsBase,
                                                            'month_item_particulars_base' => $monthItemParticularsBase
                                                        ]);
                                                    }
                                                } else {
                                                    // For non-laboratory fees, just check paymentsetupdetail_id
                                                    $paymentMatches = true;
                                                }
                                            }

                                            if ($paymentMatches) {
                                                $matchedPaymentIndices[] = $index;
                                            } else {
                                                $unmatchedPaymentIndices[] = $index;
                                            }
                                        }
                                    }

                                    // Apply matched payments first (with same paymentsetupdetail_id)
                                    foreach ($matchedPaymentIndices as $index) {
                                        if ($remainingDue > 0) {
                                            $payment = &$paymentQueueByClass[$classid][$index];
                                            $amountToApply = min($payment['remaining'], $remainingDue);
                                            $actualPaymentForClass += $amountToApply;
                                            $payment['remaining'] -= $amountToApply;
                                            $remainingDue -= $amountToApply;

                                            if (!in_array($payment['ornum'], $appliedOrNumbers)) {
                                                $appliedOrNumbers[] = $payment['ornum'];
                                            }

                                            if ($remainingDue <= 0) {
                                                break;
                                            }
                                        }
                                    }
                                    unset($payment); // Break reference

                                    // If still remaining due, apply unmatched payments (fallback for backward compatibility)
                                    // IMPORTANT: For laboratory fees, do NOT apply unmatched payments sequentially
                                    // Laboratory fees must match by paymentsetupdetail_id + particulars + itemids
                                    // For tuition fees, only apply unmatched payments that don't have a paymentsetupdetail_id
                                    // (for backward compatibility with old payments). Payments with a different paymentsetupdetail_id
                                    // should NOT be applied here - they should only be applied to their matching term.
                                    if ($remainingDue > 0 && !$isLaboratoryFee) {
                                        foreach ($unmatchedPaymentIndices as $index) {
                                            if ($remainingDue > 0) {
                                                $payment = &$paymentQueueByClass[$classid][$index];
                                                // Only apply unmatched payments that don't have a paymentsetupdetail_id
                                                // This ensures payments with a specific paymentsetupdetail_id are only applied to their matching term
                                                if ($payment['remaining'] > 0 && empty($payment['paymentsetupdetail_id'])) {
                                                    $amountToApply = min($payment['remaining'], $remainingDue);
                                                    $actualPaymentForClass += $amountToApply;
                                                    $payment['remaining'] -= $amountToApply;
                                                    $remainingDue -= $amountToApply;

                                                    if (!in_array($payment['ornum'], $appliedOrNumbers)) {
                                                        $appliedOrNumbers[] = $payment['ornum'];
                                                    }

                                                    if ($remainingDue <= 0) {
                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                        unset($payment); // Break reference
                                    }
                                }

                                if ($actualPaymentForClass > 0) {
                                    $totalPaymentsForDueDate += $actualPaymentForClass;
                                    $paymentDetails[] = [
                                        'classid' => $classid,
                                        'amount' => $actualPaymentForClass,
                                        'or_numbers' => implode(',', $appliedOrNumbers)
                                    ];
                                }

                                // Calculate balance before credit adjustment
                                $balanceBeforeCredit = $classificationAmount + $adjustmentForClass - $actualPaymentForClass;

                                // Apply credit adjustment sequentially (priority-based - earliest due dates first)
                                // Credit adjustments exhaust the balance of earlier months before moving to next month
                                $creditAdjustmentForClass = 0;
                                if ($balanceBeforeCredit > 0 && isset($remainingCreditAdjustmentsByClass[$classid]) && $remainingCreditAdjustmentsByClass[$classid] > 0) {
                                    // Apply credit adjustment only up to the remaining balance
                                    $creditToApply = min($remainingCreditAdjustmentsByClass[$classid], $balanceBeforeCredit);

                                    if ($creditToApply > 0) {
                                        $creditAdjustmentForClass = $creditToApply;

                                        // Deduct from remaining credit adjustment
                                        $remainingCreditAdjustmentsByClass[$classid] -= $creditToApply;
                                    }
                                }

                                // Don't apply discounts here during schedule building
                                // Let ALL discount application be handled by cascadeDiscountsWithinClassification
                                // and cascadeRemainingDiscounts after the full schedule is built
                                // This ensures discounts cascade properly to other fees when the original fee is already paid
                                $discountForClass = 0;

                                // Calculate final balance (no discount applied yet)
                                $balance = $balanceBeforeCredit - $creditAdjustmentForClass;

                                // Payment amount (no discount included yet)
                                $totalPaymentForClass = $actualPaymentForClass;

                                // DEBUG: Log before adding to schedule
                                \Log::debug('[PRIORITY-LOOP] Adding to schedule - paymentno=' . $schedule->paymentno . ', duedate=' . $schedule->duedate . ', classid=' . $monthItem['classid']);

                                // Use custom due date if available, otherwise use schedule due date
                                $finalDueDate = $monthItem['due_date'] ?? $schedule->duedate;

                                $studentSchedule[] = [
                                    'paymentno' => $schedule->paymentno,
                                    'paymentsetupdetail_id' => $schedule->paymentsetupdetail_id ?? null,
                                    'duedate' => $finalDueDate, // Use custom due date if available
                                    'classid' => $monthItem['classid'],
                                    'particulars' => $monthItem['particulars'],
                                    'pschemeid' => $monthItem['pschemeid'] ?? null,
                                    'amount' => round($monthItem['amount'], 2),
                                    'amountpay' => round($actualPaymentForClass, 2),
                                    'balance' => round($balance, 2),
                                    'overpayment' => 0,
                                    'laboratory_fee_id' => $monthItem['laboratory_fee_id'] ?? null, // Include laboratory_fee_id for laboratory fees
                                    'discount' => round($discountForClass, 2),
                                    'debug_payment_match' => ($studid == 2 && $isLaboratoryFee) ? ['particulars' => $monthItemParticularsBase, 'payment' => $actualPaymentForClass, 'or_count' => count($appliedOrNumbers)] : null,
                                    'adjustment' => 0, // Debit adjustments are now standalone
                                    'credit_adjustment' => round($creditAdjustmentForClass, 2),
                                    'is_one_time' => false,
                                    'is_priority_filled' => true,
                                    'payment_details' => $actualPaymentForClass > 0 ? $paymentDetails : [],
                                    'tuitiondetail_id' => $monthItem['tuitiondetailid'] ?? null, // Include tuitiondetail_id
                                    'item_management_id' => $monthItem['item_management_id'] ?? null, // Keep item management linkage
                                ];
                            }
                        }

                        // DEBUG: Check $studentSchedule right after priority loop ends
                        \Log::debug('[AFTER-PRIORITY-LOOP] Total items in schedule: ' . count($studentSchedule));
                        for ($i = 0; $i < min(4, count($studentSchedule)); $i++) {
                            \Log::debug('[AFTER-PRIORITY-LOOP] Item #' . $i . ': paymentno=' . ($studentSchedule[$i]['paymentno'] ?? 'null') . ', duedate=' . ($studentSchedule[$i]['duedate'] ?? 'null'));
                        }

                        // Add one-time fees (old accounts, OTHER FEES, etc.) from studledger BEFORE cascading so discounts can reach them
                        // Get both legacy "old accounts forwarded" items AND current one-time fees like OTHER FEES
                        $otherCharges = DB::table('studledger')
                            ->select('classid', 'particulars', DB::raw('SUM(amount) as total_amount'))
                            ->where('studid', $studid)
                            ->where('syid', $schoolYear)
                            ->where(function ($q) use ($semester) {
                                $q->where('semid', $semester)
                                    ->orWhereNull('semid');
                            })
                            ->where('deleted', 0)
                            ->where(function ($q) {
                                $q->where('payment', 0)
                                    ->orWhereNull('payment');
                            })
                            ->whereNotIn('classid', $tuitionFees->pluck('classid')->toArray())
                            // Include old account patterns AND any classid that's not in tuitiondetail (like OTHER FEES classid 3)
                            ->where(function ($q) {
                                // Legacy old account patterns
                                $q->where('particulars', 'LIKE', '%OLD ACCOUNTS FORWARDED%')
                                    ->orWhere('particulars', 'LIKE', '%BALANCE FORWARDED%')
                                    ->orWhere('particulars', 'LIKE', '%OLD ACCOUNT%')
                                    // Also include OTHER FEES and similar one-time charges
                                    ->orWhere('particulars', 'LIKE', '%OTHER FEES%')
                                    ->orWhere('classid', 3);  // OTHER FEES classid
                            })
                            // Exclude all adjustments and other items
                            ->where('particulars', 'NOT LIKE', '%BOOK%')  // Exclude books - handled by bookentries table
                            ->where('particulars', 'NOT LIKE', '%ADJUSTMENT%')  // Exclude adjustments - handled by adjustments table
                            ->where('particulars', 'NOT LIKE', 'ADJ:%')  // Exclude ADJ: prefix adjustments - handled by adjustments table
                            ->where('particulars', 'NOT LIKE', '%BACK ACCOUNTS%')  // Exclude BACK ACCOUNTS - handled by adjustments table
                            ->groupBy('classid', 'particulars')
                            ->get();

                        foreach ($otherCharges as $charge) {
                            $classid = $charge->classid;
                            $totalAmount = (float) $charge->total_amount;
                            $totalPaid = $payments->has($classid) ? (float) $payments->get($classid)->total_paid : 0;

                            $balance = $totalAmount - $totalPaid;
                            $overpayment = $balance < 0 ? abs($balance) : 0;

                            // Get discount for this classid
                            $discountAmount = $discounts->has($classid) ? (float) $discounts->get($classid)->total_discount : 0;

                            // Include discount in total payment amount
                            $totalPaymentWithDiscount = $totalPaid + $discountAmount;

                            $studentSchedule[] = [
                                'paymentno' => null,
                                'duedate' => null,
                                'classid' => $classid,
                                'particulars' => $charge->particulars,
                                'amount' => $totalAmount,
                                'amountpay' => $totalPaymentWithDiscount,
                                'balance' => max(0, $balance),
                                'overpayment' => $overpayment,
                                'discount' => $discountAmount,
                                'is_one_time' => false,
                                'payment_details' => [],
                            ];
                        }

                    } else {
                        // Single fee or one-time payment - use original logic
                        // First, consolidate fees by classid + tuitiondetailid (to prevent merging distinct lab fees)
                        // For laboratory fees, each has a unique tuitiondetailid (e.g., LAB_FEE_9, LAB_FEE_10)
                        // even though they share the same classid
                        $consolidatedFees = [];
                        foreach ($feesInGroup as $fee) {
                            $classid = $fee->classid;
                            $tuitiondetailid = $fee->tuitiondetailid ?? '';
                            // Create unique key: for laboratory fees, use tuitiondetailid; for others, use classid
                            $consolidationKey = $tuitiondetailid ?: $classid;
                            if (!isset($consolidatedFees[$consolidationKey])) {
                                $consolidatedFees[$consolidationKey] = clone $fee;
                                $consolidatedFees[$consolidationKey]->amount = 0;
                            }
                            $consolidatedFees[$consolidationKey]->amount += $fee->amount;
                        }

                        foreach ($consolidatedFees as $fee) {
                            $baseAmount = (float) $fee->amount;
                            $classid = $fee->classid;

                            // Calculate total amount based on fee type (following resetv3_generatefees logic)
                            $totalAmount = $baseAmount;
                            $particulars = $fee->particulars;

                            // For college students (level 17-25)
                            if ($student->levelid >= 17 && $student->levelid <= 25) {
                                // If istuition=1, multiply by units
                                if ($fee->istuition == 1 && $units > 0) {
                                    $totalAmount = $baseAmount * $units;
                                    $particulars .= ' | ' . $units . ' Units';
                                }

                                // If persubj=1, multiply by number of subjects
                                if ($fee->persubj == 1) {
                                    $subjectCount = DB::table('college_studsched as css')
                                        ->join('college_classsched as cs', 'css.schedid', '=', 'cs.id')
                                        ->where('css.studid', $studid)
                                        ->where('cs.syid', $schoolYear)
                                        ->where('cs.semesterID', $semester)
                                        ->where('css.deleted', 0)
                                        ->where('css.dropped', 0)
                                        ->where('cs.deleted', 0)
                                        ->distinct('cs.subjectID')
                                        ->count('cs.subjectID');

                                    if ($subjectCount > 0) {
                                        $totalAmount *= $subjectCount;
                                    }
                                }

                                // If permop=1, multiply by number of payment months
                                if ($fee->permop == 1 && $fee->permopid) {
                                    $paymentSetup = DB::table('paymentsetup')
                                        ->where('id', $fee->permopid)
                                        ->first();

                                    if ($paymentSetup && $paymentSetup->noofpayment) {
                                        $totalAmount *= $paymentSetup->noofpayment;
                                    }
                                }
                            }

                            // Get discount for this classid (will be applied priority-based later, not subtracted upfront)
                            $totalDiscount = $discounts->has($classid) ? (float) $discounts->get($classid)->total_discount : 0;

                            // Debit adjustments are now standalone - DO NOT add them to regular fees
                            // This prevents fees from getting adjustment amounts that belong to separate adjustment items
                            // But still track them to prevent duplication
                            $adjustmentAmount = 0;
                            if ($debitAdjustments->has($classid)) {
                                $matchedAdjustmentClassids[] = $classid;
                            }

                            // Get credit adjustment for this classid (will be subtracted from the amount)
                            $creditAdjustmentAmount = 0;
                            if ($creditAdjustments->has($classid)) {
                                $creditAdjustmentAmount = (float) $creditAdjustments->get($classid)->total_adjustment;
                                $matchedAdjustmentClassids[] = $classid;
                            }

                            // Subtract credit adjustment from total amount
                            $totalAmount -= $creditAdjustmentAmount;

                            // For laboratory fees, don't use classid-level payment aggregate
                            // because multiple lab fees share the same classid
                            // Instead, let the payment queue matching determine the actual payments
                            $labFeeSetupForCheck = DB::table('labfeesetup')->where('deleted', 0)->first();
                            $isLabFeeForPaymentCalc = ($labFeeSetupForCheck && $classid == $labFeeSetupForCheck->classid);

                            if ($isLabFeeForPaymentCalc) {
                                // For lab fees, set totalPaid to 0 - actual payments will be determined by queue matching
                                $totalPaid = 0;
                            } else {
                                // For regular fees, use classid-level aggregate
                                $totalPaid = $payments->has($classid) ? (float) $payments->get($classid)->total_paid : 0;
                            }

                            // Check if it's a one-time payment (only 1 row in paymentsetupdetail)
                            $isOneTime = $paymentSchedule->count() === 1;

                            if ($paymentSchedule->isEmpty()) {
                                // No payment schedule - single payment
                                // Apply payments sequentially from the payment queue
                                $actualPaymentForClass = 0;
                                $appliedOrNumbers = [];
                                $paymentDetails = [];

                                // Check if this is a laboratory fee (need to match by particulars)
                                $isLaboratoryFee = ($labFeeClassId && $classid == $labFeeClassId);
                                $feeParticularsBase = $particulars;
                                if ($isLaboratoryFee) {
                                    // Remove suffix from particulars for matching
                                    $feeParticularsBase = preg_replace('/\s*\|\s*\d+\s+Units$/i', '', $feeParticularsBase);
                                    $feeParticularsBase = trim($feeParticularsBase);
                                }

                                if (isset($paymentQueueByClass[$classid]) && !empty($paymentQueueByClass[$classid])) {
                                    $remainingDue = $totalAmount;

                                    // Apply payments from queue sequentially until due is satisfied
                                    foreach ($paymentQueueByClass[$classid] as &$payment) {
                                        if ($payment['remaining'] > 0 && $remainingDue > 0) {
                                            // For laboratory fees, check if payment particulars match the fee
                                            $paymentMatches = true;
                                            if ($isLaboratoryFee) {
                                                $paymentParticulars = $payment['particulars'] ?? '';
                                                $paymentParticularsBase = preg_replace('/\s*-\s*(DOWNPAYMENT|1ST\s+MONTH|2ND\s+MONTH|3RD\s+MONTH|4TH\s+MONTH|5TH\s+MONTH|6TH\s+MONTH|7TH\s+MONTH|8TH\s+MONTH|9TH\s+MONTH|10TH\s+MONTH|FINAL\s+PAYMENT|\d+(ST|ND|RD|TH)\s+PAYMENT|\d+(ST|ND|RD|TH)\s+MONTH|ONE\s+TIME\s+PAYMENT|NO\s+DUE\s+DATE|Due\s+\w+\s+\d+,\s+\d{4}|January|February|March|April|May|June|July|August|September|October|November|December)$/i', '', $paymentParticulars);
                                                $paymentParticularsBase = trim($paymentParticularsBase);

                                                // Match by particulars
                                                $paymentMatches = ($paymentParticularsBase === $feeParticularsBase);

                                                \Log::debug('[NO-SCHEDULE-LAB-FEE-PAYMENT] Checking payment match', [
                                                    'fee_particulars_base' => $feeParticularsBase,
                                                    'payment_particulars_base' => $paymentParticularsBase,
                                                    'match' => $paymentMatches ? 'YES' : 'NO',
                                                    'payment_remaining' => $payment['remaining'],
                                                    'studid' => $studid
                                                ]);
                                            }

                                            if (!$paymentMatches) {
                                                continue; // Skip this payment, it's for a different laboratory fee
                                            }

                                            $amountToApply = min($payment['remaining'], $remainingDue);
                                            $actualPaymentForClass += $amountToApply;
                                            $payment['remaining'] -= $amountToApply;
                                            $remainingDue -= $amountToApply;

                                            if (!in_array($payment['ornum'], $appliedOrNumbers)) {
                                                $appliedOrNumbers[] = $payment['ornum'];
                                            }

                                            if ($remainingDue <= 0) {
                                                break;
                                            }
                                        }
                                    }
                                    unset($payment); // Break reference
                                }

                                if ($actualPaymentForClass > 0) {
                                    $paymentDetails[] = [
                                        'classid' => $classid,
                                        'amount' => $actualPaymentForClass,
                                        'or_numbers' => implode(',', $appliedOrNumbers)
                                    ];
                                }

                                $balance = $totalAmount - $actualPaymentForClass;
                                $overpayment = $balance < 0 ? abs($balance) : 0;

                                $studentSchedule[] = [
                                    'paymentno' => null,
                                    'duedate' => null,
                                    'classid' => $classid,
                                    'particulars' => $particulars,
                                    'amount' => $totalAmount,
                                    'amountpay' => $actualPaymentForClass,
                                    'balance' => max(0, $balance),
                                    'overpayment' => $overpayment,
                                    'discount' => $totalDiscount,
                                    'adjustment' => 0, // Debit adjustments are now standalone
                                    'credit_adjustment' => $creditAdjustmentAmount,
                                    'is_one_time' => false,
                                    'payment_details' => $actualPaymentForClass > 0 ? $paymentDetails : [],
                                    'laboratory_fee_id' => $fee->laboratory_fee_id ?? null, // Include laboratory_fee_id for lab fees
                                    'item_management_id' => $fee->item_management_id ?? null, // Include item_management_id for item management items
                                    'is_item_management' => isset($fee->is_item_management) && $fee->is_item_management ? true : false,
                                ];
                            } else {
                                // Distribute across payment schedule
                                // If multiple due dates, divide the amount by percentamount
                                $remainingPaid = $totalPaid;
                                $remainingDiscount = $totalDiscount; // Track remaining discount for priority-based distribution
                                $remainingCreditAdjustment = $creditAdjustmentAmount; // Track remaining credit adjustment for priority-based distribution
                                $singleScheduleCount = $paymentSchedule->count();
                                $scheduleIndex = 0;

                                // DEBUG: Log payment schedule before loop
                                \Log::debug('[LOOP-DEBUG] Before loop - pschemeid=' . $pschemeid . ', classid=' . $classid . ', count=' . $singleScheduleCount);
                                \Log::debug('[LOOP-DEBUG] Payment schedule items: ' . json_encode($paymentSchedule->toArray()));

                                foreach ($paymentSchedule as $schedule) {
                                    // DEBUG: Log each iteration
                                    \Log::debug('[LOOP-DEBUG] Iteration ' . ($scheduleIndex + 1) . ': paymentno=' . $schedule->paymentno . ', duedate=' . $schedule->duedate . ', percentamount=' . ($schedule->percentamount ?? 'null'));
                                    // Calculate due amount for this installment
                                    if ($schedule->percentamount && $schedule->percentamount > 0) {
                                        // Use percentage if available
                                        $dueAmount = $totalAmount * ($schedule->percentamount / 100);
                                    } else {
                                        // Otherwise divide equally
                                        $dueAmount = $totalAmount / $singleScheduleCount;
                                    }

                                    // Apply payments sequentially from the payment queue
                                    $actualPaymentForClass = 0;
                                    $appliedOrNumbers = [];
                                    $paymentDetails = [];

                                    // Check if this is a laboratory fee (need to match by particulars)
                                    $isLaboratoryFee = ($labFeeClassId && $classid == $labFeeClassId);
                                    $feeParticularsBase = $particulars;
                                    if ($isLaboratoryFee) {
                                        // Remove suffix from particulars for matching
                                        $feeParticularsBase = preg_replace('/\s*\|\s*\d+\s+Units$/i', '', $feeParticularsBase);
                                        $feeParticularsBase = trim($feeParticularsBase);

                                        // Debug log for student 2
                                        if ($studid == 2) {
                                            if (!isset($GLOBALS['student2_one_time_lab'])) {
                                                $GLOBALS['student2_one_time_lab'] = [];
                                            }
                                            $GLOBALS['student2_one_time_lab'][] = [
                                                'fee_particulars' => $particulars,
                                                'fee_particulars_base' => $feeParticularsBase,
                                                'total_amount' => $totalAmount,
                                                'payment_queue_count' => isset($paymentQueueByClass[$classid]) ? count($paymentQueueByClass[$classid]) : 0,
                                                'payment_queue_state_before' => isset($paymentQueueByClass[$classid]) ? array_map(function ($p) {
                                                    return [
                                                        'particulars' => substr($p['particulars'] ?? 'N/A', 0, 50),
                                                        'amount' => $p['amount'] ?? 0,
                                                        'remaining' => $p['remaining'] ?? 0
                                                    ];
                                                }, $paymentQueueByClass[$classid]) : []
                                            ];

                                            \Log::info('[ONE-TIME-LAB-FEE-DEBUG] Processing laboratory fee', [
                                                'fee_particulars' => $particulars,
                                                'fee_particulars_base' => $feeParticularsBase,
                                                'total_amount' => $totalAmount,
                                                'payment_queue_count' => isset($paymentQueueByClass[$classid]) ? count($paymentQueueByClass[$classid]) : 0
                                            ]);
                                        }
                                    }

                                    if (isset($paymentQueueByClass[$classid]) && !empty($paymentQueueByClass[$classid])) {
                                        $remainingDue = $dueAmount;

                                        // Debug payment queue state before processing
                                        if ($studid == 2 && $isLaboratoryFee) {
                                            \Log::info('[ONE-TIME-LAB-QUEUE-STATE] Payment queue before processing', [
                                                'fee_particulars_base' => $feeParticularsBase,
                                                'queue_state' => array_map(function ($p) {
                                                    return [
                                                        'particulars' => $p['particulars'] ?? 'N/A',
                                                        'amount' => $p['amount'] ?? 0,
                                                        'remaining' => $p['remaining'] ?? 0
                                                    ];
                                                }, $paymentQueueByClass[$classid])
                                            ]);
                                        }

                                        // Apply payments from queue sequentially until due is satisfied
                                        foreach ($paymentQueueByClass[$classid] as $paymentIndex => &$payment) {
                                            if ($payment['remaining'] > 0 && $remainingDue > 0) {
                                                // For laboratory fees, check if payment particulars match the fee
                                                $paymentMatches = true;
                                                if ($isLaboratoryFee) {
                                                    $paymentParticulars = $payment['particulars'] ?? '';
                                                    $paymentParticularsBase = preg_replace('/\s*-\s*(DOWNPAYMENT|1ST\s+MONTH|2ND\s+MONTH|3RD\s+MONTH|4TH\s+MONTH|5TH\s+MONTH|6TH\s+MONTH|7TH\s+MONTH|8TH\s+MONTH|9TH\s+MONTH|10TH\s+MONTH|FINAL\s+PAYMENT|\d+(ST|ND|RD|TH)\s+PAYMENT|\d+(ST|ND|RD|TH)\s+MONTH|ONE\s+TIME\s+PAYMENT|NO\s+DUE\s+DATE|Due\s+\w+\s+\d+,\s+\d{4}|January|February|March|April|May|June|July|August|September|October|November|December)$/i', '', $paymentParticulars);
                                                    $paymentParticularsBase = trim($paymentParticularsBase);

                                                    // Match by particulars
                                                    $paymentMatches = ($paymentParticularsBase === $feeParticularsBase);

                                                    if ($studid == 2) {
                                                        \Log::info('[ONE-TIME-LAB-FEE-PAYMENT] Student 2 payment check', [
                                                            'payment_index' => $paymentIndex,
                                                            'fee_particulars_base' => $feeParticularsBase,
                                                            'payment_particulars' => $paymentParticulars,
                                                            'payment_particulars_base' => $paymentParticularsBase,
                                                            'match' => $paymentMatches ? 'YES' : 'NO',
                                                            'payment_remaining' => $payment['remaining'],
                                                            'payment_amount' => $payment['amount']
                                                        ]);
                                                    }
                                                }

                                                if (!$paymentMatches) {
                                                    if ($studid == 2) {
                                                        if (!isset($GLOBALS['student2_one_time_lab_skipped'])) {
                                                            $GLOBALS['student2_one_time_lab_skipped'] = [];
                                                        }
                                                        $GLOBALS['student2_one_time_lab_skipped'][] = [
                                                            'fee_particulars_base' => $feeParticularsBase,
                                                            'payment_particulars_base' => $paymentParticularsBase,
                                                            'payment_remaining' => $payment['remaining']
                                                        ];
                                                    }
                                                    continue; // Skip this payment, it's for a different laboratory fee
                                                }

                                                $amountToApply = min($payment['remaining'], $remainingDue);

                                                if ($studid == 2 && $isLaboratoryFee) {
                                                    if (!isset($GLOBALS['student2_one_time_lab_applied'])) {
                                                        $GLOBALS['student2_one_time_lab_applied'] = [];
                                                    }
                                                    $GLOBALS['student2_one_time_lab_applied'][] = [
                                                        'fee_particulars_base' => $feeParticularsBase,
                                                        'payment_particulars_base' => $paymentParticularsBase,
                                                        'amount_to_apply' => $amountToApply,
                                                        'payment_remaining_before' => $payment['remaining']
                                                    ];
                                                }

                                                $actualPaymentForClass += $amountToApply;
                                                $payment['remaining'] -= $amountToApply;
                                                $remainingDue -= $amountToApply;

                                                if (!in_array($payment['ornum'], $appliedOrNumbers)) {
                                                    $appliedOrNumbers[] = $payment['ornum'];
                                                }

                                                if ($remainingDue <= 0) {
                                                    break;
                                                }
                                            }
                                        }
                                        unset($payment); // Break reference

                                        // Debug final payment amount for student 2 lab fees
                                        if ($studid == 2 && $isLaboratoryFee) {
                                            \Log::info('[ONE-TIME-LAB-FEE-RESULT] Final payment amount', [
                                                'fee_particulars_base' => $feeParticularsBase,
                                                'actual_payment' => $actualPaymentForClass,
                                                'due_amount' => $dueAmount
                                            ]);
                                        }
                                    }

                                    if ($actualPaymentForClass > 0) {
                                        $paymentDetails[] = [
                                            'classid' => $classid,
                                            'amount' => $actualPaymentForClass,
                                            'or_numbers' => implode(',', $appliedOrNumbers)
                                        ];
                                    }

                                    $paidForThisDue = $actualPaymentForClass;
                                    $remainingPaid -= $paidForThisDue;

                                    // Distribute debit adjustment proportionally across schedules
                                    $adjustmentForThisSchedule = 0;
                                    if ($adjustmentAmount > 0) {
                                        if ($schedule->percentamount && $schedule->percentamount > 0) {
                                            $adjustmentForThisSchedule = $adjustmentAmount * ($schedule->percentamount / 100);
                                        } else {
                                            $adjustmentForThisSchedule = $adjustmentAmount / $singleScheduleCount;
                                        }
                                    }

                                    // Calculate balance before credit adjustment and discount
                                    $balanceBeforeCreditAndDiscount = $dueAmount + $adjustmentForThisSchedule - $paidForThisDue;

                                    // Apply credit adjustment sequentially (priority-based - earliest due dates first)
                                    // Only apply credit adjustment if there's a balance remaining after payment
                                    $creditAdjustmentForThisSchedule = 0;
                                    if ($remainingCreditAdjustment > 0 && $balanceBeforeCreditAndDiscount > 0) {
                                        // Apply credit adjustment only up to the remaining balance
                                        $creditToApply = min($remainingCreditAdjustment, $balanceBeforeCreditAndDiscount);
                                        $creditAdjustmentForThisSchedule = $creditToApply;
                                        $remainingCreditAdjustment -= $creditToApply;
                                    }

                                    // Calculate balance after credit adjustment, before discount
                                    $balanceBeforeDiscount = $balanceBeforeCreditAndDiscount - $creditAdjustmentForThisSchedule;

                                    // Apply discount priority-based (earliest due dates first)
                                    // Distribute discount across due dates until exhausted
                                    $discountForThisSchedule = 0;
                                    if ($remainingDiscount > 0) {
                                        // Apply discount up to the due amount for this schedule
                                        $discountForThisSchedule = min($remainingDiscount, $dueAmount);
                                        $remainingDiscount -= $discountForThisSchedule;

                                        // NOTE: Excess discount cascading is now handled by cascadeRemainingDiscounts()
                                        // which runs after all fees have been added to the schedule.
                                        // That ensures book entries and standalone adjustments can receive cascaded discounts.
                                    }

                                    $balance = $balanceBeforeDiscount - $discountForThisSchedule;
                                    $overpayment = 0;

                                    // Check if this is the last schedule and there's remaining paid (overpayment)
                                    $scheduleIndex++;
                                    if ($scheduleIndex === $singleScheduleCount && $remainingPaid > 0) {
                                        $overpayment = $remainingPaid;
                                        $paidForThisDue += $remainingPaid;
                                        $balance = max(0, $balance - $remainingPaid);
                                    }

                                    $scheduleItem = [
                                        'paymentno' => $schedule->paymentno,
                                        'paymentsetupdetail_id' => $schedule->paymentsetupdetail_id ?? null,
                                        'duedate' => $schedule->duedate,
                                        'classid' => $classid,
                                        'particulars' => $particulars,
                                        'amount' => round($dueAmount, 2),
                                        'amountpay' => round($paidForThisDue, 2),
                                        'balance' => round(max(0, $balance), 2),
                                        'overpayment' => round($overpayment, 2),
                                        'discount' => round($discountForThisSchedule, 2),
                                        'adjustment' => round($adjustmentForThisSchedule, 2),
                                        'credit_adjustment' => round($creditAdjustmentForThisSchedule, 2),
                                        'is_one_time' => $isOneTime,
                                        'payment_details' => $actualPaymentForClass > 0 ? $paymentDetails : [],
                                        'tuitiondetail_id' => $fee->tuitiondetailid ?? null, // Include tuitiondetail_id
                                        'laboratory_fee_id' => $fee->laboratory_fee_id ?? null, // Include laboratory_fee_id for lab fees
                                    ];

                                    // Debug for student 2 lab fees
                                    if ($studid == 2 && $isLaboratoryFee) {
                                        if (!isset($GLOBALS['student2_schedule_items'])) {
                                            $GLOBALS['student2_schedule_items'] = [];
                                        }
                                        $GLOBALS['student2_schedule_items'][] = [
                                            'particulars' => substr($particulars, 0, 50),
                                            'laboratory_fee_id' => $fee->laboratory_fee_id ?? 'NOT SET',
                                            'amountpay' => round($paidForThisDue, 2),
                                            'balance' => round(max(0, $balance), 2),
                                            'actual_payment' => $actualPaymentForClass
                                        ];
                                    }

                                    $studentSchedule[] = $scheduleItem;
                                }
                            }
                        }
                    }
                }

                // Add book entries from bookentries table
                // Join with items table to get book titles
                $bookEntries = DB::table('bookentries as be')
                    ->leftJoin('items as i', 'be.bookid', '=', 'i.id')
                    ->select(
                        'be.id',
                        'be.mopid',
                        'be.amount',
                        'be.bookid',
                        DB::raw('be.classid as classid'),
                        DB::raw('be.classid as classification_id'),
                        DB::raw('COALESCE(i.description, CONCAT("Book #", be.bookid)) as title'),
                        DB::raw('COALESCE(i.itemcode, "") as code')
                    )
                    ->where('be.studid', $studid)
                    ->where('be.syid', $schoolYear)
                    ->where(function ($q) use ($semester, $student) {
                        if ($student->levelid >= 17 && $student->levelid <= 25) {
                            $q->where('be.semid', $semester);
                        }
                    })
                    ->where('be.deleted', 0)
                    ->whereIn('be.bestatus', ['DRAFT', 'POSTED', 'APPROVED'])
                    ->get();

                // Preload payments per book entry (matched by itemid) to avoid double counting by title
                $bookEntryPayments = [];
                if ($bookEntries->count() > 0) {
                    $bookEntryPayments = DB::table('chrngtrans as ct')
                        ->join('chrngtransitems as cti', 'ct.id', '=', 'cti.chrngtransid')
                        ->select('cti.itemid', DB::raw('SUM(cti.amount) as total_paid'))
                        ->where('ct.studid', $studid)
                        ->where('ct.syid', $schoolYear)
                        ->where('ct.cancelled', 0)
                        ->where('cti.deleted', 0)
                        ->whereIn('cti.itemid', $bookEntries->pluck('id'))
                        ->when($student->levelid >= 14 && $student->levelid <= 25, function ($q) use ($semester) {
                            $q->where(function ($subQ) use ($semester) {
                                $subQ->where('cti.semid', $semester)->orWhereNull('cti.semid');
                            });
                        })
                        ->groupBy('cti.itemid')
                        ->get()
                        ->mapWithKeys(function ($row) {
                            return [$row->itemid => (float) $row->total_paid];
                        })
                        ->toArray();
                }

                foreach ($bookEntries as $book) {
                    // Use unique identifier for book entries instead of incorrect classid
                    $classid = 'BOOK_' . $book->id; // Unique identifier for book entries
                    $totalAmount = (float) $book->amount;

                    // Match payments using itemid to prevent pulling in other book titles
                    $totalPaid = $bookEntryPayments[$book->id] ?? 0;

                    // Get discount for this classid
                    $discountAmount = $discounts->has($classid) ? (float) $discounts->get($classid)->total_discount : 0;

                    // Apply discount
                    $totalAmount -= $discountAmount;

                    // Get payment schedule based on mopid
                    $paymentSchedule = collect();
                    if ($book->mopid) {
                        $paymentSchedule = DB::table('paymentsetupdetail')
                            ->select('paymentno', 'duedate', 'percentamount', 'description')
                            ->where('paymentid', $book->mopid)
                            ->where('deleted', 0)
                            ->orderBy('paymentno')
                            ->get();
                    }

                    $particulars = $book->title . ' (' . $book->code . ')';
                    $isOneTime = $paymentSchedule->count() === 1;

                    if ($paymentSchedule->isEmpty()) {
                        // No payment schedule - single payment
                        $balance = $totalAmount - $totalPaid;
                        $overpayment = $balance < 0 ? abs($balance) : 0;

                        $studentSchedule[] = [
                            'paymentno' => null,
                            'duedate' => null,
                            'classid' => $classid,
                            'particulars' => $particulars,
                            'amount' => $totalAmount,
                            'amountpay' => $totalPaid,
                            'balance' => max(0, $balance),
                            'overpayment' => $overpayment,
                            'discount' => $discountAmount,
                            'is_one_time' => false,
                            'is_book_entry' => true,
                            'payment_details' => [],
                        ];
                    } else {
                        // Distribute across payment schedule
                        $remainingPaid = $totalPaid;
                        $scheduleCount = $paymentSchedule->count();
                        $scheduleIndex = 0;

                        foreach ($paymentSchedule as $schedule) {
                            // Calculate due amount for this installment
                            if ($schedule->percentamount && $schedule->percentamount > 0) {
                                $dueAmount = $totalAmount * ($schedule->percentamount / 100);
                            } else {
                                $dueAmount = $totalAmount / $scheduleCount;
                            }

                            // Allocate payment
                            $paidForThisDue = min($dueAmount, $remainingPaid);
                            $remainingPaid -= $paidForThisDue;

                            $balance = $dueAmount - $paidForThisDue;
                            $overpayment = 0;

                            $scheduleIndex++;
                            if ($scheduleIndex === $scheduleCount && $remainingPaid > 0) {
                                $overpayment = $remainingPaid;
                                $balance = 0;
                            }

                            // Distribute discount proportionally
                            $discountForThisSchedule = 0;
                            if ($discountAmount > 0) {
                                if ($schedule->percentamount && $schedule->percentamount > 0) {
                                    $discountForThisSchedule = $discountAmount * ($schedule->percentamount / 100);
                                } else {
                                    $discountForThisSchedule = $discountAmount / $scheduleCount;
                                }
                            }

                            $studentSchedule[] = [
                                'paymentno' => $schedule->paymentno,
                                'duedate' => $schedule->duedate,
                                'classid' => $classid,
                                'particulars' => $particulars,
                                'amount' => round($dueAmount, 2),
                                'amountpay' => round($paidForThisDue, 2),
                                'balance' => round(max(0, $balance), 2),
                                'overpayment' => round($overpayment, 2),
                                'discount' => round($discountForThisSchedule, 2),
                                'is_one_time' => $isOneTime,
                                'is_book_entry' => true,
                                'payment_details' => [],
                            ];
                        }
                    }
                }

                // Track standalone adjustments to prevent duplicates
                $addedStandaloneAdjustments = [];
                // Track all adjustments added to schedule to prevent any duplicates
                $addedAdjustmentsToSchedule = [];

                // Add standalone debit adjustments
                // Display all debit adjustments as separate items, even if their classid matches other fees
                \Log::debug('[DEBIT-ADJUST-LOOP] Starting debit adjustment loop, count: ' . count($debitAdjustments));
                \Log::debug('[DEBIT-ADJUST-LOOP] Debit adjustments keys: ' . json_encode(array_keys($debitAdjustments->toArray())));

                if (count($debitAdjustments) == 0) {
                    \Log::debug('[DEBIT-ADJUST-LOOP] No debit adjustments to process, skipping standalone processing');
                }

                try {
                    \Log::debug('[DEBIT-ADJUST-LOOP] About to enter foreach loop');
                    foreach ($debitAdjustments as $originalClassid => $adjustment) {
                        \Log::debug('[DEBIT-ADJUST-LOOP] Processing adjustment - originalClassid: ' . $originalClassid . ', particulars: ' . ($adjustment->particulars ?? 'null') . ', amount: ' . ($adjustment->total_adjustment ?? 'null'));

                        // Keep numeric classid for adjustments so they match with payments in chrngcashtrans
                        // Use is_standalone_adjustment flag to distinguish from regular fees
                        $classid = $originalClassid;
                        \Log::debug('[DEBIT-ADJUST-LOOP] Using classid: ' . $classid);

                        // Create unique key to prevent duplicate standalone adjustments
                        $adjustmentKey = $classid . '::' . ($adjustment->particulars ?? '');
                        if (isset($addedStandaloneAdjustments[$adjustmentKey])) {
                            \Log::debug('[DEBIT-ADJUST-LOOP] Skipping duplicate: ' . $adjustmentKey);
                            continue;
                        }
                        $addedStandaloneAdjustments[$adjustmentKey] = true;

                        // Additional check to prevent any duplicate adjustments in schedule
                        $scheduleKey = $classid . '::' . ($adjustment->particulars ?? '') . '::' . $studid;
                        if (isset($addedAdjustmentsToSchedule[$scheduleKey])) {
                            continue;
                        }
                        $addedAdjustmentsToSchedule[$scheduleKey] = true;

                        $adjustmentAmount = (float) $adjustment->total_adjustment;

                        // Match payments by BOTH classid AND particulars
                        // This prevents matching payments from different classids that have similar descriptions
                        // Extract the description from "ADJUSTMENT: description" and strip schedule/month suffix
                        $adjustmentDesc = str_replace('ADJUSTMENT: ', '', $adjustment->particulars);
                        $adjustmentDesc = preg_replace('/\s*-\s*(January|February|March|April|May|June|July|August|September|October|November|December|Prelims?|Midterms?|Finals?|Pre[-\s]?Finals?|Pelims?)$/i', '', $adjustmentDesc);
                        $adjustmentDesc = trim($adjustmentDesc);

                        // Query payments that match BOTH the classid AND the description
                        // Get individual payments with their paymentsetupdetail_id for proper matching
                        $totalPaid = 0;
                        $paymentsBySchedule = [];
                        $adjustmentPayments = DB::table('chrngcashtrans as cct')
                            ->join('chrngtrans as ct', 'cct.transno', '=', 'ct.transno')
                            ->select('cct.amount', 'cct.paymentsetupdetail_id')
                            ->where('cct.studid', $studid)
                            ->where('ct.studid', $studid)
                            ->where('cct.syid', $schoolYear)
                            ->where('cct.classid', $classid) // Match by classid
                            ->where('cct.particulars', 'LIKE', '%' . $adjustmentDesc . '%') // Match by description
                            ->where(function ($q) use ($semester) {
                                $q->where('cct.semid', $semester)
                                    ->orWhereNull('cct.semid');
                            })
                            ->where('cct.deleted', 0)
                            ->where('ct.cancelled', 0)
                            ->get();

                        // Group payments by paymentsetupdetail_id and calculate total
                        foreach ($adjustmentPayments as $payment) {
                            $psdId = $payment->paymentsetupdetail_id;
                            $amount = (float) $payment->amount;
                            $totalPaid += $amount;

                            if ($psdId) {
                                if (!isset($paymentsBySchedule[$psdId])) {
                                    $paymentsBySchedule[$psdId] = 0;
                                }
                                $paymentsBySchedule[$psdId] += $amount;
                            }
                        }

                        $balance = $adjustmentAmount - $totalPaid;
                        $overpayment = $balance < 0 ? abs($balance) : 0;

                        // Get payment schedule for this adjustment if it has mop
                        $mop = $adjustment->mop ?? null;
                        $paymentSchedule = [];

                        if ($mop) {
                            $paymentSchedule = DB::table('paymentsetupdetail')
                                ->select('id', 'paymentno', 'duedate', 'percentamount', 'description')
                                ->where('paymentid', $mop)
                                ->where('deleted', 0)
                                ->orderBy('paymentno')
                                ->get();
                            \Log::debug('[DEBIT-ADJUST-LOOP] Payment schedule for mop=' . $mop . ', count: ' . count($paymentSchedule));
                            if (count($paymentSchedule) == 0) {
                                \Log::debug('[DEBIT-ADJUST-LOOP] No payment schedule found for mop=' . $mop . ', will treat as one-time payment');
                            }
                        }

                        // If has payment schedule, create entries for each due date
                        if ($paymentSchedule->isNotEmpty()) {
                            \Log::debug('[DEBIT-ADJUST-LOOP] Processing payment schedule with ' . count($paymentSchedule) . ' installments');
                            foreach ($paymentSchedule as $schedule) {
                                $scheduleAmount = ($adjustmentAmount * $schedule->percentamount) / 100;

                                // Match payment by paymentsetupdetail_id instead of sequential distribution
                                $schedulePaid = 0;
                                if (isset($paymentsBySchedule[$schedule->id])) {
                                    $schedulePaid = $paymentsBySchedule[$schedule->id];
                                }

                                $scheduleBalance = $scheduleAmount - $schedulePaid;

                                $scheduledItem = [
                                    'paymentno' => $schedule->paymentno,
                                    'duedate' => $schedule->duedate,
                                    'classid' => $classid,
                                    'particulars' => $adjustment->particulars . ($schedule->description ? ' - ' . $schedule->description : ''),
                                    'amount' => $scheduleAmount,
                                    'amountpay' => $schedulePaid,
                                    'balance' => max(0, $scheduleBalance),
                                    'overpayment' => 0,
                                    'discount' => 0,  // Will be filled by cascading logic
                                    'adjustment' => 0,
                                    'credit_adjustment' => 0,
                                    'is_one_time' => false,
                                    'is_priority_filled' => false,
                                    'is_standalone_adjustment' => true,
                                    'adjustmentdetail_id' => $adjustment->adjustmentdetail_id ?? null,
                                    'paymentsetupdetail_id' => $schedule->id ?? null,
                                    'payment_details' => [],
                                ];

                                \Log::debug('[DEBIT-ADJUST-LOOP] Adding scheduled standalone adjustment: ' . json_encode($scheduledItem));
                                $studentSchedule[] = $scheduledItem;
                                \Log::debug('[DEBIT-ADJUST-LOOP] Successfully added scheduled adjustment to schedule');

                                // Add to debug response
                                if (!isset($grouped[$studid]['debug']['added_items'])) {
                                    $grouped[$studid]['debug']['added_items'] = [];
                                }
                                $grouped[$studid]['debug']['added_items'][] = [
                                    'type' => 'scheduled_adjustment',
                                    'classid' => $scheduledItem['classid'],
                                    'particulars' => $scheduledItem['particulars'],
                                    'schedule_count' => count($studentSchedule)
                                ];
                            }
                        } else {
                            // No payment schedule - one-time payment
                            \Log::debug('[DEBIT-ADJUST-LOOP] No payment schedule found, processing as one-time payment');
                            $newItem = [
                                'paymentno' => null,
                                'duedate' => null,
                                'classid' => $classid,
                                'particulars' => $adjustment->particulars,
                                'amount' => $adjustmentAmount,
                                'amountpay' => min($adjustmentAmount, $totalPaid),
                                'balance' => max(0, $balance),
                                'overpayment' => $overpayment,
                                'discount' => 0,  // Will be filled by cascading logic
                                'adjustment' => 0,
                                'credit_adjustment' => 0,
                                'is_one_time' => true,
                                'is_priority_filled' => false,
                                'is_standalone_adjustment' => true,
                                'adjustmentdetail_id' => $adjustment->adjustmentdetail_id ?? null,
                                'payment_details' => [],
                            ];

                            \Log::debug('[DEBIT-ADJUST-LOOP] Adding one-time standalone adjustment: ' . json_encode($newItem));
                            $studentSchedule[] = $newItem;
                            \Log::debug('[DEBIT-ADJUST-LOOP] Successfully added one-time adjustment to schedule');

                            // Add to debug response
                            if (!isset($grouped[$studid]['debug']['added_items'])) {
                                $grouped[$studid]['debug']['added_items'] = [];
                            }
                            $grouped[$studid]['debug']['added_items'][] = [
                                'type' => 'one_time_adjustment',
                                'classid' => $newItem['classid'],
                                'particulars' => $newItem['particulars'],
                                'schedule_count' => count($studentSchedule)
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('[DEBIT-ADJUST-LOOP] Exception: ' . $e->getMessage());
                    \Log::error('[DEBIT-ADJUST-LOOP] Stack: ' . $e->getTraceAsString());
                }

                // Check items in schedule right after standalone adjustments
                $adjItemsAfterAdd = [];
                foreach ($studentSchedule as $item) {
                    if (isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment'] === true) {
                        $adjItemsAfterAdd[] = [
                            'classid' => $item['classid'],
                            'particulars' => $item['particulars'] ?? 'no-particulars',
                            'is_standalone_adjustment' => $item['is_standalone_adjustment'] ?? false
                        ];
                    }
                }
                \Log::debug('[AFTER-STANDALONE-ADD] Schedule count: ' . count($studentSchedule) . ', ADJ items found: ' . count($adjItemsAfterAdd));

                // NOW cascade discounts after ALL fees (including book entries and standalone adjustments) have been added
                // This ensures book entries and adjustments can receive cascaded discounts from other fee classifications

                \Log::debug('[DISCOUNT-CASCADE-START] remainingDiscountsByClass BEFORE cascading: ' . json_encode($remainingDiscountsByClass));

                // Save original excess discounts per classid BEFORE cascading (for overpayment display breakdown)
                $originalExcessDiscountsByClass = [];
                foreach ($remainingDiscountsByClass as $classid => $discountAmount) {
                    if ($classid !== 'overpayment' && is_numeric($classid) && $discountAmount > 0) {
                        $originalExcessDiscountsByClass[$classid] = (float) $discountAmount;
                    }
                }

                // Cascade discounts within same classification first
                \Log::debug('[DEBUG-CORRUPTION] BEFORE cascadeDiscountsWithinClassification - Item #3: paymentno=' . ($studentSchedule[3]['paymentno'] ?? 'null') . ', duedate=' . ($studentSchedule[3]['duedate'] ?? 'null'));
                self::cascadeDiscountsWithinClassification($studentSchedule, $remainingDiscountsByClass);
                \Log::debug('[DEBUG-CORRUPTION] AFTER cascadeDiscountsWithinClassification - Item #3: paymentno=' . ($studentSchedule[3]['paymentno'] ?? 'null') . ', duedate=' . ($studentSchedule[3]['duedate'] ?? 'null'));

                \Log::debug('[DISCOUNT-CASCADE-WITHIN] remainingDiscountsByClass AFTER within-class cascade: ' . json_encode($remainingDiscountsByClass));
                $adjAfterWithin = [];
                foreach ($studentSchedule as $item) {
                    if (isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment'] === true) {
                        $adjAfterWithin[] = $item['classid'];
                    }
                }
                \Log::debug('[AFTER-WITHIN-CASCADE] Schedule count: ' . count($studentSchedule) . ', ADJ items: ' . json_encode($adjAfterWithin));

                // Then cascade remaining discounts across different classifications
                \Log::debug('[DEBUG-CORRUPTION] BEFORE cascadeRemainingDiscounts - Item #3: paymentno=' . ($studentSchedule[3]['paymentno'] ?? 'null') . ', duedate=' . ($studentSchedule[3]['duedate'] ?? 'null'));
                self::cascadeRemainingDiscounts($remainingDiscountsByClass, $studentSchedule);
                \Log::debug('[DEBUG-CORRUPTION] AFTER cascadeRemainingDiscounts - Item #3: paymentno=' . ($studentSchedule[3]['paymentno'] ?? 'null') . ', duedate=' . ($studentSchedule[3]['duedate'] ?? 'null'));

                \Log::debug('[DISCOUNT-CASCADE-CROSS] remainingDiscountsByClass AFTER cross-class cascade: ' . json_encode($remainingDiscountsByClass));
                $adjAfterCross = [];
                foreach ($studentSchedule as $item) {
                    if (isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment'] === true) {
                        $adjAfterCross[] = $item['classid'];
                    }
                }
                \Log::debug('[AFTER-CROSS-CASCADE] Schedule count: ' . count($studentSchedule) . ', ADJ items: ' . json_encode($adjAfterCross));

                // Cascade credit adjustments within same classification
                self::cascadeCreditAdjustmentsWithinClassification($studentSchedule, $remainingCreditAdjustmentsByClass);

                \Log::debug('[CREDIT-CASCADE-WITHIN] remainingCreditAdjustmentsByClass AFTER within-class cascade: ' . json_encode($remainingCreditAdjustmentsByClass));

                // Then cascade remaining credit adjustments across different classifications
                self::cascadeRemainingCreditAdjustments($remainingCreditAdjustmentsByClass, $studentSchedule);

                \Log::debug('[CREDIT-CASCADE-CROSS] remainingCreditAdjustmentsByClass AFTER cross-class cascade: ' . json_encode($remainingCreditAdjustmentsByClass));

                // Save standalone adjustments before cascading (as a workaround)
                $savedAdjustments = [];
                foreach ($studentSchedule as $item) {
                    if (isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment'] === true) {
                        $savedAdjustments[] = $item;
                    }
                }
                \Log::debug('[BEFORE-PAYMENT-CASCADE] Saved ' . count($savedAdjustments) . ' adjustments');

                // DEBUG: Log all schedule items for student 1 before cascade
                if ($studid == 1) {
                    $scheduleByClass = [];
                    foreach ($studentSchedule as $item) {
                        $classid = $item['classid'];
                        if (!isset($scheduleByClass[$classid])) {
                            $scheduleByClass[$classid] = [];
                        }
                        $scheduleByClass[$classid][] = [
                            'particulars' => substr($item['particulars'] ?? '', 0, 40),
                            'balance' => $item['balance'] ?? 0,
                            'is_item_management' => $item['is_item_management'] ?? false,
                            'item_management_id' => $item['item_management_id'] ?? null,
                            'is_standalone_adjustment' => $item['is_standalone_adjustment'] ?? false,
                        ];
                    }
                    \Log::debug('[BEFORE-CASCADE-FULL-SCHEDULE] Student 1 schedule by class: ' . json_encode($scheduleByClass));
                }

                // Cascade excess payments to next priority fees with unpaid balances
                $excessPaymentResult = self::cascadeExcessPayments($studentSchedule, $studid, $schoolYear, $semester);
                $remainingExcessPayments = $excessPaymentResult['remaining'];
                $originalExcessPayments = $excessPaymentResult['original_excess'];

                // Restore standalone adjustments after cascading (workaround for items disappearing)
                foreach ($savedAdjustments as $savedAdj) {
                    // Check if this adjustment still exists in schedule
                    $exists = false;
                    foreach ($studentSchedule as $item) {
                        if (
                            isset($item['is_standalone_adjustment']) &&
                            $item['is_standalone_adjustment'] === true &&
                            $item['classid'] == $savedAdj['classid'] &&
                            $item['particulars'] == $savedAdj['particulars']
                        ) {
                            $exists = true;
                            break;
                        }
                    }
                    // If it disappeared, add it back
                    if (!$exists) {
                        \Log::debug('[RESTORE-ADJUSTMENT] Re-adding adjustment: classid=' . $savedAdj['classid'] . ', particulars=' . $savedAdj['particulars']);
                        $studentSchedule[] = $savedAdj;
                    }
                }

                \Log::debug('[PAYMENT-CASCADE] Remaining excess payments after cascade: ' . json_encode($remainingExcessPayments));
                \Log::debug('[PAYMENT-CASCADE] Original excess payments: ' . json_encode($originalExcessPayments));
                $adjAfterPayment = [];
                foreach ($studentSchedule as $item) {
                    if (isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment'] === true) {
                        $adjAfterPayment[] = $item['classid'];
                    }
                }
                \Log::debug('[AFTER-PAYMENT-CASCADE] Schedule count: ' . count($studentSchedule) . ', ADJ items: ' . json_encode($adjAfterPayment));

                // Calculate and display overpayment for each classification on its last payment
                \Log::debug('[DEBUG-CORRUPTION] BEFORE calculateOverpaymentPerClassification - Item #3: paymentno=' . ($studentSchedule[3]['paymentno'] ?? 'null') . ', duedate=' . ($studentSchedule[3]['duedate'] ?? 'null'));
                self::calculateOverpaymentPerClassification($studentSchedule);
                \Log::debug('[DEBUG-CORRUPTION] AFTER calculateOverpaymentPerClassification - Item #3: paymentno=' . ($studentSchedule[3]['paymentno'] ?? 'null') . ', duedate=' . ($studentSchedule[3]['duedate'] ?? 'null'));

                // IMPORTANT: Clear overpayment fields set by calculateOverpaymentPerClassification
                // because it calculates overpayment AFTER cascading, which is incorrect
                // Overpayment should only reflect REMAINING excess that couldn't be cascaded
                foreach ($studentSchedule as &$item) {
                    $item['overpayment'] = 0;
                }
                unset($item);

                // Apply remaining excess payments (that couldn't be cascaded) as overpayment on last payment of each classification
                foreach ($remainingExcessPayments as $classid => $overpaymentAmount) {
                    if ($overpaymentAmount > 0) {
                        // Find the last payment for this classification
                        $lastItemKey = null;
                        $maxPaymentNo = -1;

                        foreach ($studentSchedule as $key => $scheduleItem) {
                            if ($scheduleItem['classid'] == $classid) {
                                $paymentNo = $scheduleItem['paymentno'] ?? 0;
                                if ($paymentNo >= $maxPaymentNo) {
                                    $maxPaymentNo = $paymentNo;
                                    $lastItemKey = $key;
                                }
                            }
                        }

                        // Add overpayment to the last item
                        if ($lastItemKey !== null) {
                            $studentSchedule[$lastItemKey]['overpayment'] = round($overpaymentAmount, 2);
                            \Log::debug('[OVERPAYMENT-REMAINING] Class ' . $classid . ': overpayment ' . $overpaymentAmount . ' added to last payment (key=' . $lastItemKey . ')');
                        }
                    }
                }

                // Restore standalone adjustments after overpayment calculation (they may have been removed)
                foreach ($savedAdjustments as $savedAdj) {
                    $exists = false;
                    foreach ($studentSchedule as $item) {
                        if (
                            isset($item['is_standalone_adjustment']) &&
                            $item['is_standalone_adjustment'] === true &&
                            $item['classid'] == $savedAdj['classid'] &&
                            $item['particulars'] == $savedAdj['particulars']
                        ) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        \Log::debug('[RESTORE-ADJUSTMENT-AFTER-OVERPAY] Re-adding adjustment: classid=' . $savedAdj['classid'] . ', particulars=' . $savedAdj['particulars']);
                        $studentSchedule[] = $savedAdj;
                    }
                }

                $adjAfterOverpayment = [];
                foreach ($studentSchedule as $item) {
                    if (isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment'] === true) {
                        $adjAfterOverpayment[] = $item['classid'];
                    }
                }
                \Log::debug('[AFTER-OVERPAYMENT-CALC] Schedule count: ' . count($studentSchedule) . ', ADJ items: ' . json_encode($adjAfterOverpayment));

                // Remove any duplicate entries that may have been created during processing
                // Group by unique key: classid + paymentno + duedate + particulars
                \Log::debug('[DEDUPLICATION] Schedule count before deduplication: ' . count($studentSchedule));

                // DEBUG: Log ALL items right before deduplication with full details
                \Log::debug('[BEFORE-DEDUP-FULL] Total items: ' . count($studentSchedule));
                foreach ($studentSchedule as $idx => $item) {
                    \Log::debug('[BEFORE-DEDUP-FULL] Item #' . $idx . ': classid=' . ($item['classid'] ?? 'null') . ', paymentno=' . ($item['paymentno'] ?? 'null') . ', duedate=' . ($item['duedate'] ?? 'null') . ', particulars=' . ($item['particulars'] ?? 'null') . ', is_priority_filled=' . ($item['is_priority_filled'] ?? 'false'));
                }

                // Debug: Log all items before deduplication
                $beforeDedup = [];
                foreach ($studentSchedule as $item) {
                    $beforeDedup[] = [
                        'classid' => $item['classid'] ?? '',
                        'paymentno' => $item['paymentno'] ?? 'null',
                        'duedate' => $item['duedate'] ?? 'null',
                        'particulars' => $item['particulars'] ?? '',
                        'amount' => $item['amount'] ?? 0,
                        'balance' => $item['balance'] ?? 0
                    ];
                }
                \Log::debug('[DEDUPLICATION] Items before dedup: ' . json_encode($beforeDedup));

                // TEMPORARY: Add before-dedup data to debug response
                $debugInfo['items_before_dedup'] = $beforeDedup;

                $uniqueSchedule = [];
                $seenKeys = [];
                $removedDuplicates = [];
                foreach ($studentSchedule as $item) {
                    $key = ($item['classid'] ?? '') . '_' .
                        ($item['paymentno'] ?? 'null') . '_' .
                        ($item['duedate'] ?? 'null') . '_' .
                        ($item['particulars'] ?? '');

                    if (!isset($seenKeys[$key])) {
                        $uniqueSchedule[] = $item;
                        $seenKeys[$key] = true;
                    } else {
                        $removedDuplicates[] = [
                            'classid' => $item['classid'] ?? '',
                            'paymentno' => $item['paymentno'] ?? 'null',
                            'duedate' => $item['duedate'] ?? 'null',
                            'particulars' => $item['particulars'] ?? '',
                            'key' => $key
                        ];
                    }
                }
                $studentSchedule = $uniqueSchedule;
                \Log::debug('[DEDUPLICATION] Removed duplicates: ' . json_encode($removedDuplicates));
                \Log::debug('[DEDUPLICATION] Schedule count after deduplication: ' . count($studentSchedule));

                // Restore standalone adjustments after deduplication (ensure they weren't removed as duplicates)
                foreach ($savedAdjustments as $savedAdj) {
                    $exists = false;
                    foreach ($studentSchedule as $item) {
                        if (
                            isset($item['is_standalone_adjustment']) &&
                            $item['is_standalone_adjustment'] === true &&
                            $item['classid'] == $savedAdj['classid'] &&
                            $item['particulars'] == $savedAdj['particulars']
                        ) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        \Log::debug('[RESTORE-ADJUSTMENT-AFTER-DEDUP] Re-adding adjustment: classid=' . $savedAdj['classid'] . ', particulars=' . $savedAdj['particulars']);
                        $studentSchedule[] = $savedAdj;
                    }
                }

                // Calculate total allocated discount (from database) for display purposes
                $totalAllocatedDiscount = 0;
                foreach ($discounts as $discount) {
                    $totalAllocatedDiscount += (float) $discount->total_discount;
                }

                // Get total excess discount that couldn't be applied
                $totalExcessDiscount = $remainingDiscountsByClass['overpayment'] ?? 0;

                \Log::debug('[OVERPAYMENT-CALC] totalExcessDiscount: ' . $totalExcessDiscount);
                \Log::debug('[OVERPAYMENT-CALC] remainingDiscountsByClass: ' . json_encode($remainingDiscountsByClass));

                // Get outside fees (classid = 0) from chrngcashtrans
                $outsideFees = DB::table('chrngcashtrans as cct')
                    ->join('chrngtrans as ct', 'cct.transno', '=', 'ct.transno')
                    ->select(
                        'ct.ornum',
                        'ct.transdate',
                        'cct.particulars',
                        'cct.amount',
                        'ct.paytype'
                    )
                    ->where('cct.studid', $studid)
                    ->where('ct.studid', $studid)
                    ->where('ct.syid', $schoolYear)
                    ->where(function ($q) use ($semester) {
                        $q->where('ct.semid', $semester)
                            ->orWhereNull('ct.semid');
                    })
                    ->where('cct.classid', 0) // Outside fees
                    ->where('cct.deleted', 0)
                    ->where('ct.cancelled', 0)
                    ->orderBy('ct.transdate', 'desc')
                    ->get()
                    ->map(function ($fee) {
                        // For outside fees, the amount paid IS the amount (since these are additional charges that are immediately paid)
                        // There's no "due" amount for outside fees - they're paid in full when recorded
                        return [
                            'or_number' => $fee->ornum,
                            'date' => $fee->transdate,
                            'particulars' => $fee->particulars ?? 'Outside Fee',
                            'amount' => (float) $fee->amount, // Amount due
                            'amountpay' => (float) $fee->amount, // Amount paid (same as amount since it's paid when recorded)
                            'balance' => 0, // Balance is always 0 for outside fees
                            'payment_type' => $fee->paytype
                        ];
                    });

                // Calculate total excess payment (sum of all remaining excess payments that couldn't be applied)
                // This is the AFTER-CASCADING amount
                $totalExcessPayment = 0;
                if (!empty($remainingExcessPayments)) {
                    $totalExcessPayment = array_sum($remainingExcessPayments);
                }

                // Get total excess discount (after cascading)
                // Sum all remaining discounts from all classids (including 'overpayment' key if it exists)
                $totalExcessDiscount = 0;
                foreach ($remainingDiscountsByClass as $classid => $remainingDiscount) {
                    if ($remainingDiscount > 0) {
                        $totalExcessDiscount += $remainingDiscount;
                    }
                }

                // Get total excess credit adjustment (after cascading)
                $totalExcessCreditAdjustment = 0;
                foreach ($remainingCreditAdjustmentsByClass as $classid => $remainingCredit) {
                    if ($remainingCredit > 0) {
                        $totalExcessCreditAdjustment += $remainingCredit;
                    }
                }

                // Log for debugging footer overpayment
                \Log::debug('[OVERPAY-FOOTER] totalExcessDiscount (after cascading): ' . $totalExcessDiscount);
                \Log::debug('[OVERPAY-FOOTER] totalExcessCreditAdjustment (after cascading): ' . $totalExcessCreditAdjustment);
                \Log::debug('[OVERPAY-FOOTER] totalExcessPayment (after cascading): ' . $totalExcessPayment);
                \Log::debug('[OVERPAY-FOOTER] remainingExcessPayments array: ' . json_encode($remainingExcessPayments));
                \Log::debug('[OVERPAY-FOOTER] remainingDiscountsByClass[overpayment]: ' . ($remainingDiscountsByClass['overpayment'] ?? 'NOT SET'));

                // Combine excess discount, excess credit adjustment, and excess payment into total overpayment
                // This is the FOOTER OVERPAY - what's left after all cascading
                $totalOverpayment = $totalExcessDiscount + $totalExcessCreditAdjustment + $totalExcessPayment;

                // Store original excess credit adjustments by class (before cascading)
                $originalExcessCreditAdjustmentsByClass = [];
                foreach ($remainingCreditAdjustmentsByClass as $classid => $remainingCredit) {
                    if ($remainingCredit > 0) {
                        $originalExcessCreditAdjustmentsByClass[$classid] = $remainingCredit;
                    }
                }

                // Build per-classid overpayment breakdown FIRST
                $overpaymentsPerClassid = [];
                foreach ($originalExcessPayments as $classid => $excessPayment) {
                    if ($excessPayment > 0) {
                        // Get ORIGINAL excess discount for this classid (before cascading)
                        $excessDiscountForClass = $originalExcessDiscountsByClass[$classid] ?? 0;
                        // Get ORIGINAL excess credit adjustment for this classid (before cascading)
                        $excessCreditForClass = $originalExcessCreditAdjustmentsByClass[$classid] ?? 0;
                        $overpaymentsPerClassid[$classid] = [
                            'excess_discount' => $excessDiscountForClass,
                            'excess_credit_adjustment' => $excessCreditForClass,
                            'excess_payment' => $excessPayment,
                            'total' => $excessDiscountForClass + $excessCreditForClass + $excessPayment
                        ];
                    }
                }
                // Also add classids that only have credit adjustment excess
                foreach ($originalExcessCreditAdjustmentsByClass as $classid => $excessCredit) {
                    if (!isset($overpaymentsPerClassid[$classid]) && $excessCredit > 0) {
                        $excessDiscountForClass = $originalExcessDiscountsByClass[$classid] ?? 0;
                        $excessPaymentForClass = $originalExcessPayments[$classid] ?? 0;
                        $overpaymentsPerClassid[$classid] = [
                            'excess_discount' => $excessDiscountForClass,
                            'excess_credit_adjustment' => $excessCredit,
                            'excess_payment' => $excessPaymentForClass,
                            'total' => $excessDiscountForClass + $excessCredit + $excessPaymentForClass
                        ];
                    }
                }

                // Add original excess (per-classid overpayment) to schedule items for display
                // Also set the overpayment field based on REMAINING overpayment after cascading
                // Group items by classid to find last payment for each
                $itemsByClassid = [];
                foreach ($studentSchedule as $key => &$item) {
                    $classid = $item['classid'];
                    if (!isset($itemsByClassid[$classid])) {
                        $itemsByClassid[$classid] = [];
                    }
                    $itemsByClassid[$classid][] = ['key' => $key, 'item' => &$item];
                }
                unset($item);

                // For each classid, set overpayment on the LAST payment
                foreach ($itemsByClassid as $classid => $items) {
                    // Find the last item (highest payment number)
                    $lastItemRef = null;
                    $maxPaymentNo = -1;
                    foreach ($items as $itemRef) {
                        $paymentNo = $itemRef['item']['paymentno'] ?? 0;
                        if ($paymentNo >= $maxPaymentNo) {
                            $maxPaymentNo = $paymentNo;
                            $lastItemRef = &$itemRef;
                        }
                    }

                    if ($lastItemRef !== null) {
                        // Set original_excess for display in overpayment column
                        if (is_numeric($classid) && isset($overpaymentsPerClassid[$classid])) {
                            $lastItemRef['item']['original_excess'] = $overpaymentsPerClassid[$classid]['total'] ?? 0;

                            // Set overpayment field to the TOTAL overpayment (excess payment + excess discount + excess credit adjustment)
                            // This represents all amounts that remain after cascading
                            $totalOverpayForClass = $overpaymentsPerClassid[$classid]['total'] ?? 0;
                            if ($totalOverpayForClass > 0) {
                                $lastItemRef['item']['overpayment'] = round($totalOverpayForClass, 2);
                            }
                        }
                    }
                }

                // Count standalone adjustments in schedule
                $standaloneAdjCount = 0;
                $adjustmentClassids = [];
                foreach ($studentSchedule as $item) {
                    if (isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment']) {
                        $standaloneAdjCount++;
                        $adjustmentClassids[] = $item['classid'] . ':' . $item['particulars'];
                    }
                }
                \Log::debug('[RETURN-DATA] studentSchedule count: ' . count($studentSchedule) . ', standalone_adjustments: ' . $standaloneAdjCount . ', classids: ' . json_encode($adjustmentClassids));

                // Collect debug info
                $debugInfo = [];
                if (isset($grouped[$studid]['debug'])) {
                    $debugInfo = $grouped[$studid]['debug'];
                }
                $debugInfo['standalone_adjustments'] = [
                    'count' => $standaloneAdjCount,
                    'classids' => $adjustmentClassids,
                    'total_schedule_items' => count($studentSchedule),
                    'processed_adjustments' => count($addedStandaloneAdjustments),
                    'added_keys' => array_keys($addedStandaloneAdjustments)
                ];

                // Check what items have is_standalone_adjustment flag
                $standaloneItemsInSchedule = 0;
                $allFlagsInSchedule = [];
                foreach ($studentSchedule as $item) {
                    $flags = [];
                    if (isset($item['is_standalone_adjustment'])) {
                        $flags[] = 'is_standalone_adjustment: ' . ($item['is_standalone_adjustment'] ? 'true' : 'false');
                        if ($item['is_standalone_adjustment']) {
                            $standaloneItemsInSchedule++;
                        }
                    }
                    if (isset($item['is_book_entry'])) {
                        $flags[] = 'is_book_entry: ' . ($item['is_book_entry'] ? 'true' : 'false');
                    }
                    if (isset($item['is_priority_filled'])) {
                        $flags[] = 'is_priority_filled: ' . ($item['is_priority_filled'] ? 'true' : 'false');
                    }
                    if (isset($item['is_one_time'])) {
                        $flags[] = 'is_one_time: ' . ($item['is_one_time'] ? 'true' : 'false');
                    }

                    if (!empty($flags)) {
                        $allFlagsInSchedule[] = [
                            'classid' => $item['classid'] ?? 'no-classid',
                            'particulars' => $item['particulars'] ?? 'no-particulars',
                            'flags' => implode(', ', $flags)
                        ];
                    }
                }
                $debugInfo['standalone_adjustments']['items_in_schedule'] = $standaloneItemsInSchedule;

                // Debug: Check what classids are actually in the schedule
                $scheduleClassids = [];
                foreach ($studentSchedule as $item) {
                    $scheduleClassids[] = $item['classid'] ?? 'no-classid';
                }
                $debugInfo['schedule_classids'] = array_unique($scheduleClassids);

                // Check specifically for standalone adjustment items
                $adjItems = [];
                foreach ($studentSchedule as $item) {
                    if (isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment'] === true) {
                        $adjItems[] = [
                            'classid' => $item['classid'],
                            'particulars' => $item['particulars'] ?? 'no-particulars',
                            'is_standalone_adjustment' => $item['is_standalone_adjustment'] ?? false
                        ];
                    }
                }
                $debugInfo['adj_items_found'] = $adjItems;
                $debugInfo['removed_duplicates'] = $removedDuplicates;
                $debugInfo['all_flags_in_schedule'] = $allFlagsInSchedule;

                // If schedule is still empty here, create a synthetic schedule using tuition fees
                if (count($studentSchedule) === 0 && isset($tuitionFees) && $tuitionFees->count() > 0) {
                    $syntheticPaymentNo = 1;
                    foreach ($tuitionFees as $fee) {
                        $studentSchedule[] = [
                            'paymentno' => $syntheticPaymentNo++,
                            'duedate' => null,
                            'amount' => (float) ($fee->amount ?? 0),
                            'amountpay' => 0,
                            'balance' => (float) ($fee->amount ?? 0),
                            'classid' => $fee->classid ?? null,
                            'itemid' => $fee->itemid ?? null,
                            'particulars' => $fee->particulars ?? 'Fee Item',
                            'discount' => 0,
                            'payment_details' => [],
                            'is_synthetic' => true
                        ];
                    }
                    $debugInfo['synthetic_schedule_built'] = true;
                }

                // Final debug check
                $finalAdjItems = [];
                foreach ($studentSchedule as $item) {
                    if (isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment'] === true) {
                        $finalAdjItems[] = [
                            'classid' => $item['classid'],
                            'particulars' => $item['particulars'] ?? 'no-particulars',
                            'is_standalone_adjustment' => $item['is_standalone_adjustment'] ?? false
                        ];
                    }
                }
                $debugInfo['final_adj_items'] = $finalAdjItems;
                $debugInfo['final_schedule_count'] = count($studentSchedule);

                // Add items_before_dedup to debug (moved here to ensure it shows)
                if (isset($beforeDedup)) {
                    $debugInfo['items_before_dedup'] = $beforeDedup;
                }

                // Add cascade debug info
                $debugInfo['cascade_results'] = [
                    'adj_after_add' => count($adjItemsAfterAdd),
                    'adj_after_within' => count($adjAfterWithin),
                    'adj_after_cross' => count($adjAfterCross),
                    'adj_after_payment' => count($adjAfterPayment),
                    'adj_after_overpayment' => count($adjAfterOverpayment)
                ];

                // Final check - log all classids right before return
                $finalClassids = [];
                foreach ($studentSchedule as $item) {
                    $finalClassids[] = $item['classid'] ?? 'no-classid';
                }
                $debugInfo['final_classids_before_return'] = array_unique($finalClassids);

                // Item management rows should never carry discounts; clear any stray discount values
                foreach ($studentSchedule as &$scheduleItem) {
                    if (!empty($scheduleItem['item_management_id']) || (!empty($scheduleItem['is_item_management']))) {
                        $scheduleItem['discount'] = 0;
                        if (isset($scheduleItem['payment_details']) && is_array($scheduleItem['payment_details'])) {
                            // Remove discount-only payment details for item management entries
                            $scheduleItem['payment_details'] = array_values(array_filter(
                                $scheduleItem['payment_details'],
                                function ($pd) {
                                    $part = $pd['particulars'] ?? '';
                                    return stripos($part, 'discount') === false;
                                }
                            ));
                        }
                    }
                }
                unset($scheduleItem);

                $grouped[$studid] = [
                    'due_dates' => $studentSchedule,
                    'outside_fees' => $outsideFees->toArray(),
                    'total_allocated_discount' => $totalAllocatedDiscount,
                    'total_excess_discount' => $totalExcessDiscount,
                    'total_excess_credit_adjustment' => $totalExcessCreditAdjustment,
                    'total_excess_payment' => $totalExcessPayment,
                    'total_overpayment' => $totalOverpayment,
                    'overpayments_per_classid' => $overpaymentsPerClassid,
                    'debug' => $debugInfo
                ];
            } catch (\Exception $e) {
                // Log the error for this student but continue processing others
                \Log::error("Error processing student ID {$studid} in getStudentPaymentSchedules", [
                    'student_id' => $studid,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                // Skip this student and continue with others
                continue;
            }
        }

        return $grouped;
    }

    /**
     * Apply priority overrides to payment schedule if has_priorities = 1
     */
    private function applyPriorityOverrides($student, $schoolYear, $semester, $schedule)
    {
        if (empty($schedule)) {
            return $schedule;
        }

        // Check if tuition header has priorities
        $tuitionHeader = DB::table('tuitionheader')
            ->where('syid', $schoolYear)
            ->where('semid', $semester)
            ->where('levelid', $student->levelid)
            ->where('deleted', 0)
            ->first();

        // Check if tuition header exists and has priorities enabled
        // Note: has_priorities column may not exist in all databases
        if (!$tuitionHeader) {
            return $schedule;
        }

        // Safely check if has_priorities property exists and is enabled
        $hasPriorities = property_exists($tuitionHeader, 'has_priorities') && $tuitionHeader->has_priorities;
        if (!$hasPriorities) {
            return $schedule;
        }

        // Get priorities and priority items
        $priorities = DB::table('priorities as p')
            ->join('priority_items as pi', 'pi.priority_id', '=', 'p.id')
            ->where('p.deleted', 0)
            ->where('pi.deleted', 0)
            ->where('pi.header_id', $tuitionHeader->id)
            ->select([
                'p.priority_date',
                'p.override_date',
                'pi.detail_id',
                'pi.classid',
                'pi.amount as priority_amount'
            ])
            ->get();

        if ($priorities->isEmpty()) {
            return $schedule;
        }

        // Build priority map: [duedate][detail_id] => amount
        $priorityMap = [];
        foreach ($priorities as $priority) {
            $targetDate = $priority->override_date ?? $priority->priority_date;
            if (!isset($priorityMap[$targetDate])) {
                $priorityMap[$targetDate] = [];
            }
            $priorityMap[$targetDate][$priority->detail_id] = (float) $priority->priority_amount;
        }

        // Apply overrides to schedule
        foreach ($schedule as &$item) {
            // Safety check: ensure item is an array
            if (!is_array($item)) {
                continue;
            }

            $duedate = $item['duedate'] ?? null;
            $detailId = $item['tuitiondetailid'] ?? null;

            if ($duedate && $detailId && isset($priorityMap[$duedate][$detailId])) {
                $originalAmount = $item['amount'] ?? 0;
                $overrideAmount = $priorityMap[$duedate][$detailId];

                // Override amount
                $item['amount'] = $overrideAmount;

                // Recalculate balance
                $item['balance'] = $overrideAmount - ($item['amountpay'] ?? 0);

                // Mark as overridden (for debugging/display purposes)
                $item['is_overridden'] = true;
                $item['original_amount'] = $originalAmount;
            }
        }

        return $schedule;
    }

    /**
     * Calculate college student fees including units
     */
    private function calculateCollegeFees($student, $schoolYear, $semester)
    {
        if (!$student->courseid) {
            return 0;
        }

        // Get tuition fees setup
        $receivables = DB::table('tuitionheader')
            ->select(DB::raw('itemclassification.id as classid, itemclassification.description, amount, levelid, istuition, persubj, semid, courseid'))
            ->join('tuitiondetail', 'tuitionheader.id', '=', 'tuitiondetail.headerid')
            ->join('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
            ->where('syid', $schoolYear)
            ->where('semid', $semester)
            ->where('levelid', $student->levelid)
            ->where('courseid', $student->courseid)
            ->where('tuitionheader.deleted', 0)
            ->where('tuitiondetail.deleted', 0)
            ->where('itemclassification.deleted', 0)
            ->get();

        if ($receivables->isEmpty()) {
            return 0;
        }

        // Get student's enrolled units
        $studentUnits = DB::table('college_loadsubject')
            ->join('college_classsched', 'college_loadsubject.schedid', '=', 'college_classsched.id')
            ->join('college_prospectus', 'college_classsched.subjectID', '=', 'college_prospectus.id')
            ->where('college_loadsubject.studid', $student->id)
            ->where('college_loadsubject.syid', $schoolYear)
            ->where('college_loadsubject.semid', $semester)
            ->where('college_loadsubject.deleted', 0)
            ->select('college_prospectus.*')
            ->distinct()
            ->get();

        if ($studentUnits->isEmpty()) {
            return 0;
        }

        $subjectIds = $studentUnits->pluck('subjectID')->unique()->toArray();

        // Get assessment units with priority: courseid + subjid > subjid only
        // First try to get course-specific assessment units
        $assessmentUnits = DB::table('tuition_assessmentunit')
            ->select('subjid', 'assessmentunit', 'courseid', 'paymentschedid')
            ->where('tuition_assessmentunit.deleted', 0)
            ->where('tuition_assessmentunit.syid', $schoolYear)
            ->where('tuition_assessmentunit.semid', $semester)
            ->whereIn('tuition_assessmentunit.subjid', $subjectIds)
            ->where(function ($query) use ($student) {
                // Priority 1: Match both courseid and subjid
                $query->where('tuition_assessmentunit.courseid', $student->courseid)
                    // Priority 2: Match subjid only (courseid is null - applies to all courses)
                    ->orWhereNull('tuition_assessmentunit.courseid');
            })
            ->orderByRaw('CASE WHEN courseid IS NOT NULL THEN 1 ELSE 2 END') // Course-specific first
            ->get()
            ->unique('subjid') // Take first match per subject (course-specific wins)
            ->keyBy('subjid');

        // Get subject count
        $subjectCount = DB::table('college_loadsubject')
            ->join('college_prospectus', 'college_loadsubject.subjectID', '=', 'college_prospectus.id')
            ->where('college_loadsubject.studid', $student->id)
            ->where('college_loadsubject.syid', $schoolYear)
            ->where('college_loadsubject.semid', $semester)
            ->where('college_loadsubject.deleted', 0)
            ->count();

        // Calculate total units
        $totalUnits = 0;
        foreach ($studentUnits as $unit) {
            if ($assessmentUnits->has($unit->subjectID)) {
                // Use the assessment unit from the table (can be course-specific or general)
                $totalUnits += (float) $assessmentUnits->get($unit->subjectID)->assessmentunit;
            } else {
                // Use actual lecture + lab units
                $totalUnits += $unit->lecunits + $unit->labunits;
            }
        }

        // Calculate fee total
        $feeSum = 0;
        foreach ($receivables as $fee) {
            $amount = $fee->amount ?? 0;
            if ($fee->istuition == 1) {
                $feeSum += $amount * $totalUnits;
            } elseif ($fee->persubj == 1) {
                $feeSum += $amount * $subjectCount;
            } else {
                $feeSum += $amount;
            }
        }

        return $feeSum;
    }

    private function calculateTuitionFee($student, $schoolYear, $semester, $acadprogid, $levelArray)
    {
        if ($acadprogid == 6) {
            // College - calculate with units
            return $this->calculateCollegeFees($student, $schoolYear, $semester);
        } elseif ($acadprogid == 5) {
            // Senior High School
            return $this->getSHSTuiFees($student->id, $schoolYear, $semester, null, $acadprogid, $levelArray);
        } elseif (in_array($acadprogid, [2, 3, 4])) {
            // Basic Education
            return $this->getRegTuiFees($student->id, $schoolYear, $semester, null, $acadprogid, $levelArray);
        }
        return 0;
    }

    /**
     * Get applied filters for response
     */
    private function getAppliedFilters(Request $request)
    {
        return [
            'school_year' => $request->input('school_year'),
            'semester' => $request->input('semester'),
            'academic_program' => $request->input('academic_program'),
            'academic_level' => $request->input('academic_level'),
            'college' => $request->input('college'),
            'course' => $request->input('course'),
            'strand' => $request->input('strand'),
            'section' => $request->input('section'),
            'scholarship' => $request->input('scholarship'),
            'status' => $request->input('status'),
        ];
    }

    /**
     * Get transaction logs (payments, discounts, adjustments) for a student
     */
    /**
     * Get payment schedule for a student (server-side)
     */
    public function getPaymentSchedule(Request $request)
    {
        $data = $request->json()->all();

        $studid = $data['studid'] ?? null;
        $syid = $data['syid'] ?? null;
        $semid = $data['semid'] ?? null;

        if (!$studid || !$syid) {
            return response()->json([
                'success' => false,
                'message' => 'Student ID and School Year are required',
                'data' => []
            ], 400);
        }

        try {
            // Get payment schedules using the existing method
            $paymentSchedules = $this->getStudentPaymentSchedules([$studid], $syid, $semid);

            $schedule = $paymentSchedules[$studid] ?? [];

            return response()->json([
                'success' => true,
                'data' => $schedule
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching payment schedule: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    public function getTransactionLogs(Request $request)
    {
        $data = $request->json()->all();

        $studid = $data['studid'] ?? null;
        $syid = $data['syid'] ?? null;
        $semid = $data['semid'] ?? null;

        if (!$studid || !$syid) {
            return response()->json([
                'success' => false,
                'message' => 'Student ID and School Year are required',
                'data' => []
            ], 400);
        }

        try {
            $logs = [];

            // Get payment logs from chrngtrans - GROUP BY OR number to show one row per receipt
            $payments = DB::table('chrngtrans as ct')
                ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
                ->select(
                    DB::raw("'payment' as type"),
                    'ct.ornum',
                    'ct.transdate as transaction_date',
                    'ct.change_amount',
                    'ct.paymenttype_id',
                    'ct.other_paymenttype_ids',
                    DB::raw('SUM(cct.amount) as total_amount'),
                    DB::raw('COUNT(DISTINCT cct.classid) as item_count')
                )
                ->where('ct.studid', $studid)
                ->where('ct.syid', $syid)
                ->where('ct.cancelled', 0)
                ->where('cct.studid', $studid) // Filter chrngcashtrans by student ID
                ->where('cct.deleted', 0)
                ->where('cct.amount', '>', 0)
                ->groupBy('ct.ornum', 'ct.transdate', 'ct.change_amount', 'ct.paymenttype_id', 'ct.other_paymenttype_ids')
                ->orderBy('ct.transdate', 'desc')
                ->get();

            // Get payment type descriptions
            $paymentTypeIds = $payments->pluck('paymenttype_id')->unique()->filter()->toArray();

            // Also get other payment type IDs from JSON
            $otherPaymentTypeIds = [];
            foreach ($payments as $payment) {
                if ($payment->other_paymenttype_ids) {
                    $decoded = json_decode($payment->other_paymenttype_ids, true);
                    if (is_array($decoded)) {
                        $otherPaymentTypeIds = array_merge($otherPaymentTypeIds, $decoded);
                    }
                }
            }

            $allPaymentTypeIds = array_unique(array_merge($paymentTypeIds, $otherPaymentTypeIds));

            $paymentTypes = [];
            if (!empty($allPaymentTypeIds)) {
                $paymentTypes = DB::table('paymenttype')
                    ->select('id', 'description')
                    ->whereIn('id', $allPaymentTypeIds)
                    ->get()
                    ->keyBy('id');
            }

            foreach ($payments as $payment) {
                // Get primary payment type description
                $primaryPaymentType = $paymentTypes->get($payment->paymenttype_id);
                $primaryPaymentTypeName = $primaryPaymentType ? $primaryPaymentType->description : 'N/A';

                // Get other payment type descriptions
                $otherPaymentTypeNames = [];
                if ($payment->other_paymenttype_ids) {
                    $otherIds = json_decode($payment->other_paymenttype_ids, true);
                    if (is_array($otherIds)) {
                        foreach ($otherIds as $otherTypeId) {
                            $otherType = $paymentTypes->get($otherTypeId);
                            if ($otherType) {
                                $otherPaymentTypeNames[] = $otherType->description;
                            }
                        }
                    }
                }

                // Build payment method display
                $paymentMethods = [$primaryPaymentTypeName];
                if (!empty($otherPaymentTypeNames)) {
                    $paymentMethods = array_merge($paymentMethods, $otherPaymentTypeNames);
                }
                $paymentMethodsDisplay = implode(', ', $paymentMethods);

                $logs[] = [
                    'type' => 'payment',
                    'classid' => null, // Multiple items, no single classid
                    'particulars' => 'PAYMENT - OR: ' . $payment->ornum . ' (' . $payment->item_count . ' item' . ($payment->item_count > 1 ? 's' : '') . ')',
                    'amount' => (float) $payment->total_amount,
                    'reference' => $payment->ornum,
                    'transaction_date' => $payment->transaction_date,
                    'change_amount' => (float) ($payment->change_amount ?? 0),
                    'payment_method' => $paymentMethodsDisplay,
                    'paymenttype_id' => $payment->paymenttype_id,
                    'other_paymenttype_ids' => $payment->other_paymenttype_ids,
                ];
            }

            // Get discount logs from studdiscounts
            $discounts = DB::table('studdiscounts as sd')
                ->join('discounts as d', 'sd.discountid', '=', 'd.id')
                ->join('itemclassification as ic', 'sd.classid', '=', 'ic.id')
                ->select(
                    DB::raw("'discount' as type"),
                    'sd.classid',
                    DB::raw("CONCAT('DISCOUNT: ', d.particulars, ' - ', ic.description, ' ', sd.discount, IF(sd.percent = 1, '%', '')) as particulars"),
                    'sd.discamount as amount',
                    'sd.notes',
                    'sd.posted',
                    'sd.createddatetime as transaction_date'
                )
                ->where('sd.studid', $studid)
                ->where('sd.syid', $syid)
                ->where(function ($q) use ($semid) {
                    if ($semid) {
                        $q->where('sd.semid', $semid);
                    }
                })
                ->where('sd.deleted', 0)
                ->get();

            foreach ($discounts as $discount) {
                $logs[] = [
                    'type' => 'discount',
                    'classid' => $discount->classid,
                    'particulars' => $discount->particulars,
                    'amount' => (float) $discount->amount,
                    'reference' => $discount->posted ? 'Posted' : 'Unposted',
                    'transaction_date' => $discount->transaction_date,
                    'notes' => $discount->notes,
                ];
            }

            // Get adjustment logs from adjustments and adjustmentdetails
            $adjustments = DB::table('adjustmentdetails as ad')
                ->join('adjustments as a', 'ad.headerid', '=', 'a.id')
                ->leftJoin('itemclassification as ic', 'a.classid', '=', 'ic.id')
                ->select(
                    DB::raw("'adjustment' as type"),
                    'a.classid',
                    DB::raw("CONCAT('ADJUSTMENT: ', COALESCE(ic.description, 'Adjustment'), ' - ', IF(a.iscredit = 1, 'CREDIT', 'DEBIT')) as particulars"),
                    'a.amount',
                    'a.refnum',
                    'a.createddatetime as transaction_date'
                )
                ->where('ad.studid', $studid)
                ->where('a.syid', $syid)
                ->where(function ($q) use ($semid) {
                    if ($semid) {
                        $q->where('a.semid', $semid);
                    }
                })
                ->where('ad.deleted', 0)
                ->where('a.deleted', 0)
                ->orderBy('a.createddatetime', 'desc')
                ->get();

            foreach ($adjustments as $adjustment) {
                $logs[] = [
                    'type' => 'adjustment',
                    'classid' => $adjustment->classid,
                    'particulars' => $adjustment->particulars,
                    'amount' => (float) $adjustment->amount,
                    'reference' => $adjustment->refnum,
                    'transaction_date' => $adjustment->transaction_date,
                ];
            }

            // Get old account logs from studledger (classid 22 only)
            $oldAccountLogs = DB::table('studledger as sl')
                ->leftJoin('itemclassification as ic', 'sl.classid', '=', 'ic.id')
                ->select(
                    DB::raw("'old_account' as type"),
                    'sl.classid',
                    DB::raw("COALESCE(sl.particulars, ic.description, 'Old Account') as particulars"),
                    'sl.amount',
                    'sl.ornum',
                    'sl.createddatetime as transaction_date'
                )
                ->where('sl.studid', $studid)
                ->where('sl.syid', $syid)
                ->where('sl.classid', 22) // Only old accounts
                ->where(function ($q) use ($semid) {
                    if ($semid) {
                        $q->where('sl.semid', $semid)
                            ->orWhereNull('sl.semid');
                    }
                })
                ->where('sl.deleted', 0)
                ->where('sl.amount', '>', 0)
                ->orderBy('sl.createddatetime', 'desc')
                ->get();

            foreach ($oldAccountLogs as $oldAcct) {
                $logs[] = [
                    'type' => 'old_account',
                    'classid' => $oldAcct->classid,
                    'particulars' => $oldAcct->particulars,
                    'amount' => (float) $oldAcct->amount,
                    'reference' => $oldAcct->ornum ?? 'N/A',
                    'transaction_date' => $oldAcct->transaction_date,
                ];
            }

            // Get book entry logs from bookentries
            $bookEntries = DB::table('bookentries as be')
                ->leftJoin('items as i', 'be.bookid', '=', 'i.id')
                ->select(
                    DB::raw("'book_entry' as type"),
                    DB::raw('be.classid as classid'),
                    DB::raw("CONCAT('BOOK: ', COALESCE(i.description, CONCAT('Book #', be.bookid)), CASE WHEN i.itemcode IS NOT NULL AND i.itemcode != '' THEN CONCAT(' (', i.itemcode, ')') ELSE '' END) as particulars"),
                    'be.amount',
                    'be.bestatus',
                    'be.createddatetime as transaction_date'
                )
                ->where('be.studid', $studid)
                ->where('be.syid', $syid)
                ->where(function ($q) use ($semid) {
                    if ($semid) {
                        $q->where('be.semid', $semid);
                    }
                })
                ->where('be.deleted', 0)
                ->get();

            foreach ($bookEntries as $bookEntry) {
                $logs[] = [
                    'type' => 'book_entry',
                    'classid' => $bookEntry->classid,
                    'particulars' => $bookEntry->particulars,
                    'amount' => (float) $bookEntry->amount,
                    'reference' => $bookEntry->bestatus,
                    'transaction_date' => $bookEntry->transaction_date,
                ];
            }

            // Sort by transaction date (newest first)
            usort($logs, function ($a, $b) {
                return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
            });

            return response()->json([
                'success' => true,
                'data' => $logs
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get monthly assessment for a student
     * Uses the same priority-based sequential payment distribution logic as SchoolFeesController
     */
    public function getMonthlyAssessment(Request $request)
    {
        $data = $request->json()->all();

        $studid = $data['studid'] ?? null;
        $syid = $data['syid'] ?? null;
        $semid = $data['semid'] ?? null;

        if (!$studid || !$syid) {
            return response()->json([
                'success' => false,
                'message' => 'Student ID and School Year are required',
                'data' => []
            ], 400);
        }

        try {
            // Get student info to determine level and enrollment
            $student = DB::table('studinfo')
                ->where('id', $studid)
                ->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found',
                    'data' => []
                ], 404);
            }

            // Get tuitionheader ID for this student
            $tuitionHeader = $this->getStudentTuitionHeader($student, $syid, $semid);

            if (!$tuitionHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tuition header found for this student',
                    'data' => []
                ], 404);
            }

            // Get enrolled units for college students
            $units = 0;
            if ($student->levelid >= 17 && $student->levelid <= 25) {
                $unitsResult = DB::table('college_loadsubject as cls')
                    ->join('college_classsched as cs', 'cls.schedid', '=', 'cs.id')
                    ->join('college_prospectus as cp', 'cs.subjectID', '=', 'cp.id')
                    ->select(DB::raw('SUM(cp.lecunits + cp.labunits) as totalunits'))
                    ->where('cls.studid', $studid)
                    ->where('cls.syid', $syid)
                    ->where('cls.semid', $semid)
                    ->where('cls.deleted', 0)
                    ->where(function ($q) {
                        $q->where('cls.isDropped', 0)
                            ->orWhereNull('cls.isDropped');
                    })
                    ->first();

                $units = $unitsResult && $unitsResult->totalunits ? (float) $unitsResult->totalunits : 0;
            }

            // Get all classifications from tuitiondetail ordered by priority (creation time)
            $classifications = DB::table('tuitiondetail')
                ->leftJoin('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
                ->select([
                    'tuitiondetail.id as detail_id',
                    'tuitiondetail.classificationid as classid',
                    'tuitiondetail.description as detail_description',
                    'tuitiondetail.amount as detail_amount',
                    'tuitiondetail.pschemeid',
                    'tuitiondetail.perunit',
                    'tuitiondetail.persubj',
                    'tuitiondetail.istuition',
                    'tuitiondetail.createddatetime',
                    'itemclassification.description as classification_description'
                ])
                ->where('tuitiondetail.headerid', $tuitionHeader->id)
                ->where('tuitiondetail.deleted', 0)
                ->orderBy('tuitiondetail.createddatetime', 'asc')
                ->get();

            // Calculate amounts for each classification with subject/item breakdown
            $classificationData = [];
            $totalAmount = 0;

            // Track unique item/subject combinations to prevent duplicates
            $seenItemCombinations = [];

            foreach ($classifications as $classification) {
                // Get items with priority order - filter out items with 0 amount
                $items = DB::table('tuitionitems')
                    ->leftJoin('items', 'tuitionitems.itemid', '=', 'items.id')
                    ->select([
                        'tuitionitems.id as item_id',
                        'tuitionitems.itemid',
                        'items.description as item_description',
                        'tuitionitems.amount as item_amount',
                        'tuitionitems.createddatetime'
                    ])
                    ->where('tuitionitems.tuitiondetailid', $classification->detail_id)
                    ->where('tuitionitems.deleted', 0)
                    ->where('tuitionitems.amount', '>', 0)
                    ->orderBy('tuitionitems.createddatetime', 'asc')
                    ->get();

                // Get subjects with priority order - filter out subjects with 0 amount
                $subjects = DB::table('tuition_subjects')
                    ->leftJoin('subjects', 'tuition_subjects.subjid', '=', 'subjects.id')
                    ->leftJoin('sh_subjects', 'tuition_subjects.subjid', '=', 'sh_subjects.id')
                    ->leftJoin('college_prospectus', 'tuition_subjects.subjid', '=', 'college_prospectus.id')
                    ->select([
                        'tuition_subjects.id as subject_id',
                        'tuition_subjects.subjid as subjectid',
                        'tuition_subjects.amount as subject_amount',
                        DB::raw("COALESCE(subjects.subjdesc, sh_subjects.subjtitle, college_prospectus.subjDesc) as subject_name"),
                        'tuition_subjects.createddatetime'
                    ])
                    ->where('tuition_subjects.tuitiondetailid', $classification->detail_id)
                    ->where('tuition_subjects.deleted', 0)
                    ->where('tuition_subjects.amount', '>', 0)
                    ->orderBy('tuition_subjects.createddatetime', 'asc')
                    ->get();

                // Build sub-items array
                $subItems = [];

                // Determine if we need to multiply by units for college students
                $shouldMultiplyByUnits = false;
                if ($student->levelid >= 17 && $student->levelid <= 25) {
                    if ($classification->istuition == 1 && $units > 0) {
                        $shouldMultiplyByUnits = true;
                    }
                }

                foreach ($items as $item) {
                    $itemAmount = (float) $item->item_amount;

                    // Multiply by units if this is tuition for college students
                    if ($shouldMultiplyByUnits) {
                        $itemAmount *= $units;
                    }

                    $subItems[] = [
                        'type' => 'item',
                        'id' => $item->item_id,
                        'itemid' => $item->itemid,
                        'description' => $item->item_description,
                        'amount' => $itemAmount,
                        'remaining_amount' => $itemAmount,
                        'created_at' => $item->createddatetime
                    ];
                }

                foreach ($subjects as $subject) {
                    $subjectAmount = (float) $subject->subject_amount;

                    // Multiply by units if this is tuition for college students
                    if ($shouldMultiplyByUnits) {
                        $subjectAmount *= $units;
                    }

                    $subItems[] = [
                        'type' => 'subject',
                        'id' => $subject->subject_id,
                        'subjectid' => $subject->subjectid,
                        'description' => $subject->subject_name,
                        'amount' => $subjectAmount,
                        'remaining_amount' => $subjectAmount,
                        'created_at' => $subject->createddatetime
                    ];
                }

                // Calculate total for this classification
                $classificationTotal = (float) $classification->detail_amount;

                // Apply multipliers for college students
                if ($student->levelid >= 17 && $student->levelid <= 25) {
                    if ($classification->istuition == 1 && $units > 0) {
                        $classificationTotal *= $units;
                    }

                    if ($classification->persubj == 1) {
                        $subjectCount = DB::table('college_studsched as css')
                            ->join('college_classsched as cs', 'css.schedid', '=', 'cs.id')
                            ->where('css.studid', $studid)
                            ->where('cs.syid', $syid)
                            ->where('cs.semesterID', $semid)
                            ->where('css.deleted', 0)
                            ->where('css.dropped', 0)
                            ->where('cs.deleted', 0)
                            ->distinct('cs.subjectID')
                            ->count('cs.subjectID');

                        if ($subjectCount > 0) {
                            $classificationTotal *= $subjectCount;
                        }
                    }
                }

                // If perunit, use the base amount (already calculated)
                if ($classification->perunit != 1 && !empty($subItems)) {
                    // Recalculate from sub-items if not per-unit
                    $classificationTotal = 0;
                    foreach ($subItems as $subItem) {
                        $classificationTotal += $subItem['amount'];
                    }
                }

                // Skip classifications with 0 total amount AND no valid sub-items
                // This prevents duplicate entries for classifications with only 0-amount items
                if ($classificationTotal <= 0 && empty($subItems)) {
                    continue;
                }

                // Create a unique key based on classid and the actual items/subjects (using itemid/subjid)
                // This prevents duplicate classifications with the same items
                $itemKeys = [];
                foreach ($subItems as $subItem) {
                    if ($subItem['type'] === 'item' && isset($subItem['itemid'])) {
                        $itemKeys[] = 'item_' . $subItem['itemid'];
                    } elseif ($subItem['type'] === 'subject' && isset($subItem['subjectid'])) {
                        $itemKeys[] = 'subject_' . $subItem['subjectid'];
                    }
                }
                sort($itemKeys); // Sort to ensure consistent ordering
                $combinationKey = $classification->classid . '::' . implode('|', $itemKeys);

                // Skip if we've already seen this exact combination of classid and items/subjects
                if (isset($seenItemCombinations[$combinationKey])) {
                    continue;
                }
                $seenItemCombinations[$combinationKey] = true;

                // Get payment schedule details
                $scheduleDetails = DB::table('paymentsetupdetail')
                    ->where('paymentid', $classification->pschemeid)
                    ->where('deleted', 0)
                    ->orderBy('paymentno')
                    ->get();

                $noOfPayments = DB::table('paymentsetup')
                    ->where('id', $classification->pschemeid)
                    ->value('noofpayment') ?? 1;

                $classificationData[] = [
                    'detail_id' => $classification->detail_id,
                    'classid' => $classification->classid,
                    'description' => $classification->detail_description ?? $classification->classification_description,
                    'classification' => $classification->classification_description,
                    'total_amount' => $classificationTotal,
                    'remaining_amount' => $classificationTotal,
                    'sub_items' => $subItems,
                    'pschemeid' => $classification->pschemeid,
                    'schedule_details' => $scheduleDetails,
                    'no_of_payments' => $noOfPayments,
                    'created_at' => $classification->createddatetime
                ];

                $totalAmount += $classificationTotal;
            }

            // Add old accounts from studledger (classid 22 = BACK ACCOUNTS only)
            $oldAccountsQuery = DB::table('studledger')
                ->select('classid', DB::raw('SUM(amount) as total_amount'), DB::raw('MAX(particulars) as particulars'))
                ->where('studid', $studid)
                ->where('syid', $syid)
                ->where('deleted', 0)
                ->where(function ($q) {
                    $q->where('payment', 0)
                        ->orWhereNull('payment');
                })
                ->where('amount', '>', 0)
                ->where('classid', 22) // Only old accounts (BACK ACCOUNTS)
                ->groupBy('classid');

            // For college students, filter by semester
            if ($student->levelid >= 17 && $student->levelid <= 25) {
                $oldAccountsQuery->where('semid', $semid);
            }

            $oldAccounts = $oldAccountsQuery->get();

            // Only add old account if exists and not empty
            if ($oldAccounts && $oldAccounts->isNotEmpty()) {
                foreach ($oldAccounts as $oldAcct) {
                    $oldAcctAmount = (float) $oldAcct->total_amount;

                    // Skip if amount is zero
                    if ($oldAcctAmount <= 0) {
                        continue;
                    }

                    $classificationData[] = [
                        'detail_id' => 'oldaccount_' . $oldAcct->classid . '_' . $studid,
                        'classid' => $oldAcct->classid,
                        'description' => $oldAcct->particulars,
                        'classification' => 'Old Accounts',
                        'total_amount' => $oldAcctAmount,
                        'remaining_amount' => $oldAcctAmount,
                        'sub_items' => [],
                        'pschemeid' => null,
                        'schedule_details' => collect([]),
                        'no_of_payments' => 0,
                        'created_at' => null,
                        'is_old_account' => true
                    ];

                    $totalAmount += $oldAcctAmount;
                }
            }

            // Add book entries to classification data
            // Join with items table to get book titles
            $bookEntries = DB::table('bookentries as be')
                ->leftJoin('items as i', 'be.bookid', '=', 'i.id')
                ->select(
                    'be.id',
                    'be.mopid',
                    'be.amount',
                    'be.bookid',
                    DB::raw('be.classid as classid'),
                    DB::raw('be.classid as classification_id'),
                    DB::raw('COALESCE(i.description, CONCAT("Book #", be.bookid)) as title'),
                    DB::raw('COALESCE(i.itemcode, "") as code'),
                    'be.createddatetime'
                )
                ->where('be.studid', $studid)
                ->where('be.syid', $syid)
                ->where(function ($q) use ($semid, $student) {
                    if ($student->levelid >= 17 && $student->levelid <= 25) {
                        $q->where('be.semid', $semid);
                    }
                })
                ->where('be.deleted', 0)
                ->whereIn('be.bestatus', ['DRAFT', 'POSTED', 'APPROVED'])
                ->get();

            foreach ($bookEntries as $book) {
                $bookAmount = (float) $book->amount;

                // Get payment schedule for this book entry
                $scheduleDetails = DB::table('paymentsetupdetail')
                    ->where('paymentid', $book->mopid)
                    ->where('deleted', 0)
                    ->orderBy('paymentno')
                    ->get();

                $noOfPayments = DB::table('paymentsetup')
                    ->where('id', $book->mopid)
                    ->value('noofpayment') ?? 1;

                // Create sub-item for this book
                $bookDescription = $book->code ? $book->title . ' (' . $book->code . ')' : $book->title;
                $bookSubItem = [
                    'type' => 'book',
                    'id' => $book->id,
                    'bookid' => $book->bookid,
                    'description' => $bookDescription,
                    'amount' => $bookAmount,
                    'remaining_amount' => $bookAmount,
                    'created_at' => $book->createddatetime
                ];

                $classificationData[] = [
                    'detail_id' => 'book_' . $book->id,
                    'classid' => 'BOOK_' . $book->id, // Use unique identifier for book entries
                    'description' => 'BOOK-' . $book->title,
                    'classification' => 'BOOK-' . $book->title,
                    'total_amount' => $bookAmount,
                    'remaining_amount' => $bookAmount,
                    'sub_items' => [$bookSubItem],
                    'pschemeid' => $book->mopid,
                    'schedule_details' => $scheduleDetails,
                    'no_of_payments' => $noOfPayments,
                    'created_at' => $book->createddatetime,
                    'is_book_entry' => true,
                    'bookid' => $book->bookid
                ];

                $totalAmount += $bookAmount;
            }

            // Get laboratory fees for this student
            $labFeeResult = self::getLabFees($studid, $syid, $semid);
            $labFeeAmount = $labFeeResult['total'];
            if ($labFeeAmount > 0) {
                // Get lab fee payment schedule from lab fees that have a valid mode_of_payment
                // Priority: use the first lab fee with non-null mode_of_payment
                $labFeeData = DB::table('labfees')
                    ->where('syid', $syid)
                    ->where('semid', $semid)
                    ->where('deleted', 0)
                    ->whereNotNull('mode_of_payment')
                    ->first();

                $labFeePschemeid = $labFeeData ? $labFeeData->mode_of_payment : null;

                // Get payment schedule details if pschemeid exists
                $scheduleDetails = collect([]);
                $noOfPayments = 1;
                if ($labFeePschemeid) {
                    $scheduleDetails = DB::table('paymentsetupdetail')
                        ->where('paymentid', $labFeePschemeid)
                        ->where('deleted', 0)
                        ->orderBy('paymentno')
                        ->get();

                    $noOfPayments = DB::table('paymentsetup')
                        ->where('id', $labFeePschemeid)
                        ->value('noofpayment') ?? 1;
                }

                // Get classid and itemid from labfeesetup (always 1 row)
                $labFeeSetup = DB::table('labfeesetup')
                    ->where('deleted', 0)
                    ->first();
                $labFeeClassId = $labFeeSetup ? $labFeeSetup->classid : null;
                $labFeeItemId = $labFeeSetup ? $labFeeSetup->itemid : null;

                $classificationData[] = [
                    'detail_id' => 'LAB_FEE',
                    'classid' => $labFeeClassId, // Use classid from labfeesetup
                    'itemid' => $labFeeItemId, // Use itemid from labfeesetup
                    'description' => 'Laboratory Fee',
                    'classification' => 'Laboratory Fee',
                    'total_amount' => $labFeeAmount,
                    'remaining_amount' => $labFeeAmount,
                    'sub_items' => [],
                    'pschemeid' => $labFeePschemeid,
                    'schedule_details' => $scheduleDetails,
                    'no_of_payments' => $noOfPayments,
                    'created_at' => null,
                    'is_lab_fee' => true
                ];

                $totalAmount += $labFeeAmount;
            }

            // Get student discounts (these will be treated as advance payments)
            $studentDiscounts = DB::table('studdiscounts as sd')
                ->join('discounts as d', 'sd.discountid', '=', 'd.id')
                ->select('sd.classid', 'sd.discamount', 'd.particulars', 'sd.posted')
                ->where('sd.studid', $studid)
                ->where('sd.syid', $syid)
                ->where(function ($q) use ($semid, $student) {
                    if ($student->levelid >= 17 && $student->levelid <= 25) {
                        $q->where('sd.semid', $semid);
                    }
                })
                ->where('sd.deleted', 0)
                ->where('sd.posted', 1) // Only posted discounts
                ->get()
                ->groupBy('classid');

            // Track remaining discount per classification for sequential distribution
            $remainingDiscountsByClass = [];
            foreach ($studentDiscounts as $classid => $discounts) {
                $totalDiscount = 0;
                foreach ($discounts as $discount) {
                    $totalDiscount += (float) $discount->discamount;
                }
                $remainingDiscountsByClass[$classid] = $totalDiscount;
            }

            // Get actual payments made - in chronological order for sequential distribution
            $studentPaymentsRaw = DB::table('chrngtrans as ct')
                ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
                ->leftJoin('chrngtransitems as cti', function ($join) {
                    $join->on('ct.transno', '=', 'cti.chrngtransid')
                        ->where('cti.deleted', 0);
                })
                ->select(
                    'ct.ornum',
                    'ct.transdate',
                    'ct.transno',
                    'cct.classid',
                    'cct.amount',
                    'cct.particulars',
                    'cct.paymentsetupdetail_id',
                    DB::raw('GROUP_CONCAT(DISTINCT cti.itemid ORDER BY cti.itemid SEPARATOR ",") as itemids')
                )
                ->where('ct.studid', $studid)
                ->where('ct.syid', $syid)
                ->where('cct.studid', $studid) // Filter chrngcashtrans by student ID
                ->when($student->levelid >= 14 && $student->levelid <= 25 && $semid, function ($q) use ($semid) {
                    // For SHS/college students, filter by semester
                    $q->where(function ($subQ) use ($semid) {
                        $subQ->where('cct.semid', $semid)
                            ->orWhereNull('cct.semid');
                    });
                })
                ->where('ct.cancelled', 0)
                ->where('cct.deleted', 0)
                ->where('cct.amount', '>', 0)
                ->groupBy('ct.ornum', 'ct.transdate', 'ct.transno', 'cct.classid', 'cct.amount', 'cct.particulars', 'cct.paymentsetupdetail_id')
                ->orderBy('ct.transdate')
                ->orderBy('ct.ornum')
                ->get();

            // Get all adjustment descriptions to identify adjustment payments
            $adjustmentDescriptions = DB::table('adjustments as a')
                ->join('adjustmentdetails as ad', 'a.id', '=', 'ad.headerid')
                ->select('a.description')
                ->where('ad.studid', $studid)
                ->where('a.syid', $syid)
                ->where('a.isdebit', 1)
                ->where('a.deleted', 0)
                ->where('ad.deleted', 0)
                ->when($student->levelid >= 14 && $student->levelid <= 25, function ($q) use ($semid) {
                    $q->where('a.semid', $semid);
                })
                ->distinct()
                ->pluck('description')
                ->toArray();

            // Create payment queue by classification for sequential distribution
            // Exclude book and adjustment payments from classid-based queue to prevent double allocation
            $paymentQueueByClass = [];
            $paymentQueueByParticulars = [];

            foreach ($studentPaymentsRaw as $payment) {
                $classid = $payment->classid;
                $particulars = $payment->particulars;

                // Check if this payment is for a book or adjustment (should use particulars matching)
                $isBookPayment = stripos($particulars, 'BOOK') !== false;
                $isAdjustmentPayment = false;

                // Check if particulars match any adjustment descriptions
                foreach ($adjustmentDescriptions as $adjDesc) {
                    if ($adjDesc && stripos($particulars, $adjDesc) !== false) {
                        $isAdjustmentPayment = true;
                        break;
                    }
                }

                // Add to particulars queue if it's a book or adjustment payment
                if ($isBookPayment || $isAdjustmentPayment) {
                    if (!isset($paymentQueueByParticulars[$particulars])) {
                        $paymentQueueByParticulars[$particulars] = [];
                    }
                    $paymentQueueByParticulars[$particulars][] = [
                        'ornum' => $payment->ornum,
                        'transdate' => $payment->transdate,
                        'amount' => (float) $payment->amount,
                        'remaining' => (float) $payment->amount
                    ];
                } else {
                    // Add to classid queue for regular fees
                    if (!isset($paymentQueueByClass[$classid])) {
                        $paymentQueueByClass[$classid] = [];
                    }
                    $paymentQueueByClass[$classid][] = [
                        'ornum' => $payment->ornum,
                        'transdate' => $payment->transdate,
                        'amount' => (float) $payment->amount,
                        'remaining' => (float) $payment->amount,
                        'particulars' => $particulars,
                        'paymentsetupdetail_id' => $payment->paymentsetupdetail_id ?? null,
                        'itemids' => $payment->itemids ?? null
                    ];
                }
            }

            // Keep old format for compatibility (will calculate per-schedule later)
            $studentPayments = DB::table('chrngtrans as ct')
                ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
                ->select('cct.classid', DB::raw('SUM(cct.amount) as total_paid'), DB::raw('GROUP_CONCAT(ct.ornum) as or_numbers'))
                ->where('ct.studid', $studid)
                ->where('ct.syid', $syid)
                ->where('cct.syid', $syid)
                ->where(function ($q) use ($semid) {
                    $q->where('cct.semid', $semid)
                        ->orWhereNull('cct.semid');
                })
                ->where('ct.cancelled', 0)
                ->where('cct.deleted', 0)
                ->groupBy('cct.classid')
                ->get()
                ->keyBy('classid');

            // Get debit adjustments (these increase the amount due)
            $studentAdjustments = DB::table('adjustmentdetails as ad')
                ->join('adjustments as a', 'ad.headerid', '=', 'a.id')
                ->select('a.classid', DB::raw('SUM(a.amount) as total_adjustment'))
                ->where('ad.studid', $studid)
                ->where('a.syid', $syid)
                ->where('a.isdebit', 1)
                ->where('a.amount', '>', 0)
                ->where('ad.deleted', 0)
                ->where('a.deleted', 0)
                ->when($student->levelid >= 14 && $student->levelid <= 25, function ($q) use ($semid) {
                    $q->where('a.semid', $semid);
                })
                ->groupBy('a.classid')
                ->get()
                ->keyBy('classid');

            // Get credit adjustments (reduce payables)
            $studentCreditAdjustments = DB::table('adjustmentdetails as ad')
                ->join('adjustments as a', 'ad.headerid', '=', 'a.id')
                ->select('a.classid', DB::raw('SUM(a.amount) as total_adjustment'))
                ->where('ad.studid', $studid)
                ->where('a.syid', $syid)
                ->where('a.iscredit', 1)
                ->where('a.amount', '>', 0)
                ->where('ad.deleted', 0)
                ->where('a.deleted', 0)
                ->when($student->levelid >= 14 && $student->levelid <= 25, function ($q) use ($semid) {
                    $q->where('a.semid', $semid);
                })
                ->groupBy('a.classid')
                ->get()
                ->keyBy('classid');

            // Track remaining credit adjustment per classification for sequential distribution
            // Credit adjustments are distributed priority-based (earliest due dates first)
            $remainingCreditAdjustmentsByClass = [];
            foreach ($studentCreditAdjustments as $classid => $creditAdj) {
                $remainingCreditAdjustmentsByClass[$classid] = (float) $creditAdj->total_adjustment;
            }

            // Add standalone debit adjustments (adjustments with classids not in tuition setup)
            // Note: $matchedAdjustmentClassids will be populated during the monthly payables loop
            // For now, add all adjustments that don't have existing classifications
            $existingClassids = [];
            foreach ($classificationData as $classData) {
                $existingClassids[] = $classData['classid'];
            }

            foreach ($studentAdjustments as $classid => $adjustment) {
                // Skip if this classid already exists in classifications
                if (in_array($classid, $existingClassids)) {
                    continue;
                }

                $adjustmentAmount = (float) $adjustment->total_adjustment;

                // Get adjustment details
                $adjustmentInfo = DB::table('adjustments as a')
                    ->leftJoin('itemclassification as ic', 'a.classid', '=', 'ic.id')
                    ->select('a.mop', DB::raw("COALESCE(a.description, ic.description, 'Debit Adjustment') as description"))
                    ->join('adjustmentdetails as ad', 'a.id', '=', 'ad.headerid')
                    ->where('ad.studid', $studid)
                    ->where('a.syid', $syid)
                    ->where('a.classid', $classid)
                    ->where('a.isdebit', 1)
                    ->where('a.deleted', 0)
                    ->where('ad.deleted', 0)
                    ->when($student->levelid >= 14 && $student->levelid <= 25, function ($q) use ($semid) {
                        $q->where('a.semid', $semid);
                    })
                    ->first();

                if (!$adjustmentInfo) {
                    continue;
                }

                // Get payment schedule for this adjustment
                $scheduleDetails = DB::table('paymentsetupdetail')
                    ->where('paymentid', $adjustmentInfo->mop)
                    ->where('deleted', 0)
                    ->orderBy('paymentno')
                    ->get();

                $noOfPayments = DB::table('paymentsetup')
                    ->where('id', $adjustmentInfo->mop)
                    ->value('noofpayment') ?? 1;

                $classificationData[] = [
                    'detail_id' => 'adjustment_' . $classid . '_' . $studid,
                    'classid' => $classid,
                    'description' => 'ADJUSTMENT: ' . $adjustmentInfo->description,
                    'classification' => 'ADJUSTMENT: ' . $adjustmentInfo->description,
                    'total_amount' => $adjustmentAmount,
                    'remaining_amount' => $adjustmentAmount,
                    'sub_items' => [],
                    'pschemeid' => $adjustmentInfo->mop,
                    'schedule_details' => $scheduleDetails,
                    'no_of_payments' => $noOfPayments,
                    'created_at' => null,
                    'is_adjustment' => true
                ];

                $totalAmount += $adjustmentAmount;
            }

            // Check if tuition header has priorities enabled
            $hasPriorities = property_exists($tuitionHeader, 'has_priorities') && $tuitionHeader->has_priorities == 1;

            // If priorities are enabled, override the schedule with priority-based dates and amounts
            if ($hasPriorities) {
                // Get priorities and priority items for this tuition header
                $priorities = DB::table('priorities as p')
                    ->join('priority_items as pi', 'pi.priority_id', '=', 'p.id')
                    ->where('pi.header_id', $tuitionHeader->id)
                    ->where('p.deleted', 0)
                    ->where('pi.deleted', 0)
                    ->select([
                        'p.id as priority_id',
                        'p.priority_description',
                        'p.priority_date',
                        'p.override_date',
                        'pi.detail_id',
                        'pi.item_id',
                        'pi.subject_id',
                        'pi.amount as priority_amount'
                    ])
                    ->get()
                    ->groupBy(function ($item) {
                        // Group by the effective due date (override_date takes priority)
                        return $item->override_date ?? $item->priority_date;
                    });

                // Override classificationData schedule_details with priority dates and amounts
                foreach ($classificationData as &$classData) {
                    $detailId = $classData['detail_id'];

                    // Find priority items for this detail_id
                    $prioritySchedule = collect();
                    foreach ($priorities as $dueDate => $priorityGroup) {
                        foreach ($priorityGroup as $priorityItem) {
                            if ($priorityItem->detail_id == $detailId) {
                                $prioritySchedule->push((object) [
                                    'duedate' => $dueDate,
                                    'paymentno' => null, // Will be assigned later
                                    'percentamount' => null,
                                    'priority_amount' => $priorityItem->priority_amount,
                                    'item_id' => $priorityItem->item_id,
                                    'subject_id' => $priorityItem->subject_id
                                ]);
                            }
                        }
                    }

                    // If we found priority items for this classification, override the schedule
                    if ($prioritySchedule->isNotEmpty()) {
                        $classData['schedule_details'] = $prioritySchedule;
                        $classData['no_of_payments'] = $prioritySchedule->count();

                        // Recalculate total_amount based on priority amounts
                        $classData['total_amount'] = $prioritySchedule->sum('priority_amount');
                        $classData['remaining_amount'] = $classData['total_amount'];
                    }
                }
            }

            // PRE-CALCULATE: Group classifications by pschemeid for sequential priority-based filling
            $classificationsBySchedule = [];
            $sequentialGroupIndexes = []; // Track which indexes are part of sequential groups

            foreach ($classificationData as $idx => $classData) {
                $pschemeid = $classData['pschemeid'] ?? 'no_schedule';
                $noOfPayments = $classData['no_of_payments'] ?? 0;

                // Only group items with multi-month schedules
                if ($noOfPayments > 1 && $pschemeid !== 'no_schedule' && $pschemeid !== null) {
                    if (!isset($classificationsBySchedule[$pschemeid])) {
                        $classificationsBySchedule[$pschemeid] = [
                            'schedule_details' => $classData['schedule_details'],
                            'no_of_payments' => $noOfPayments,
                            'classifications' => []
                        ];
                    }
                    $classificationsBySchedule[$pschemeid]['classifications'][] = ['index' => $idx, 'data' => $classData];
                    $sequentialGroupIndexes[] = $idx; // Mark this index as part of a sequential group
                }
            }

            // Calculate sequential distribution for each schedule group
            $sequentialAmounts = []; // [classification_index][due_date] = amount
            foreach ($classificationsBySchedule as $pschemeid => $scheduleGroup) {
                $scheduleDetails = $scheduleGroup['schedule_details'];
                $classifications = $scheduleGroup['classifications'];
                $noOfPayments = $scheduleGroup['no_of_payments'];

                // Calculate total group amount and monthly quota
                $groupTotalAmount = 0;
                foreach ($classifications as $classInfo) {
                    $groupTotalAmount += $classInfo['data']['total_amount'];
                }
                $monthlyQuota = $noOfPayments > 0 ? $groupTotalAmount / $noOfPayments : 0;

                // Track remaining amounts
                $remainingAmounts = [];
                foreach ($classifications as $classInfo) {
                    $remainingAmounts[$classInfo['index']] = $classInfo['data']['total_amount'];
                }

                // Fill each month sequentially
                foreach ($scheduleDetails as $schedule) {
                    $dueDate = $schedule->duedate;
                    if ($dueDate === null || $dueDate === '')
                        continue;

                    $monthQuota = $monthlyQuota;

                    foreach ($classifications as $classInfo) {
                        $idx = $classInfo['index'];
                        if ($remainingAmounts[$idx] <= 0 || $monthQuota <= 0)
                            continue;

                        $amountForThisMonth = min($remainingAmounts[$idx], $monthQuota);
                        if (!isset($sequentialAmounts[$idx])) {
                            $sequentialAmounts[$idx] = [];
                        }
                        $sequentialAmounts[$idx][$dueDate] = $amountForThisMonth;

                        $remainingAmounts[$idx] -= $amountForThisMonth;
                        $monthQuota -= $amountForThisMonth;
                    }
                }
            }

            // Get all unique due dates with their descriptions (exclude NULL/empty dates)
            $allDueDates = [];
            $dueDateDescriptions = []; // Map due dates to their descriptions
            foreach ($classificationData as $classData) {
                foreach ($classData['schedule_details'] as $schedule) {
                    // Only include valid due dates (not NULL or empty)
                    if ($schedule->duedate !== null && $schedule->duedate !== '' && !in_array($schedule->duedate, $allDueDates)) {
                        $allDueDates[] = $schedule->duedate;
                        // Store the description for this due date (use first description found for each date)
                        if (!isset($dueDateDescriptions[$schedule->duedate])) {
                            $dueDateDescriptions[$schedule->duedate] = $schedule->description ?? null;
                        }
                    }
                }
            }
            sort($allDueDates);

            // Small amount threshold
            $smallAmountThreshold = 10;

            // Track which adjustment classids have been matched with classifications
            // This will be used later to add standalone adjustments
            $matchedAdjustmentClassids = [];

            // Priority-based sequential payment distribution
            $monthlyPayables = [];
            $paymentCounter = 1;

            foreach ($allDueDates as $dueDate) {
                $paymentAmount = 0;
                $classificationsForPayment = [];

                foreach ($classificationData as $classIdx => &$classData) {
                    if ($classData['remaining_amount'] <= 0) {
                        continue;
                    }

                    $scheduleMatch = collect($classData['schedule_details'])->first(function ($schedule) use ($dueDate) {
                        return $schedule->duedate === $dueDate;
                    });

                    if (!$scheduleMatch) {
                        continue;
                    }

                    // Check if this classification is part of a sequential group
                    $isSequentialGroup = in_array($classIdx, $sequentialGroupIndexes);

                    // If part of sequential group and has no amount for this due date, skip it
                    if ($isSequentialGroup && !isset($sequentialAmounts[$classIdx][$dueDate])) {
                        continue;
                    }

                    $classificationPaymentAmount = 0;
                    $subItemsForPayment = [];

                    // Check if we should use sequential amount, priority amount, or calculate from sub-items
                    if (isset($sequentialAmounts[$classIdx][$dueDate])) {
                        // Use pre-calculated sequential amount for this classification and due date
                        $classificationMonthlyAmount = $sequentialAmounts[$classIdx][$dueDate];
                    } elseif ($hasPriorities && property_exists($scheduleMatch, 'priority_amount')) {
                        $classificationMonthlyAmount = $scheduleMatch->priority_amount;
                    } else {
                        $classificationMonthlyAmount = $classData['total_amount'] / $classData['no_of_payments'];
                    }
                    $remainingQuota = $classificationMonthlyAmount;

                    // Check if this is a standalone adjustment or classification without sub-items
                    $isStandaloneItem = empty($classData['sub_items']);

                    if ($isStandaloneItem) {
                        // For standalone items (adjustments, etc.), use the classification monthly amount directly
                        $classificationPaymentAmount = $classificationMonthlyAmount;
                        $subItemsForPayment[] = [
                            'type' => 'adjustment',
                            'id' => $classData['detail_id'],
                            'description' => $classData['description'],
                            'amount' => $classificationMonthlyAmount
                        ];
                        $classData['remaining_amount'] -= $classificationMonthlyAmount;
                    } else {
                        // Process sub-items normally
                        foreach ($classData['sub_items'] as &$subItem) {
                            if ($subItem['remaining_amount'] <= 0) {
                                continue;
                            }

                            $subItemMonthlyIfSplit = $subItem['amount'] / $classData['no_of_payments'];
                            $isSmallAmount = $subItemMonthlyIfSplit < $smallAmountThreshold;

                            if ($remainingQuota <= 0 && !$isSmallAmount) {
                                continue;
                            }

                            if ($isSmallAmount) {
                                $lastSchedule = collect($classData['schedule_details'])->last();
                                if ($scheduleMatch->duedate === $lastSchedule->duedate) {
                                    $amountToPay = $subItem['remaining_amount'];
                                } else {
                                    continue;
                                }
                            } else {
                                $amountToPay = min($subItem['remaining_amount'], $remainingQuota);
                            }

                            $amountToPay = round($amountToPay, 2);

                            if ($amountToPay > 0) {
                                $subItemsForPayment[] = [
                                    'type' => $subItem['type'],
                                    'id' => $subItem['id'],
                                    'description' => $subItem['description'],
                                    'amount' => $amountToPay
                                ];

                                $classificationPaymentAmount += $amountToPay;
                                $subItem['remaining_amount'] -= $amountToPay;

                                if (!$isSmallAmount) {
                                    $remainingQuota -= $amountToPay;
                                }
                            }
                        }
                    }

                    if ($classificationPaymentAmount > 0) {
                        $classificationsForPayment[] = [
                            'detail_id' => $classData['detail_id'],
                            'classid' => $classData['classid'],
                            'description' => $classData['description'],
                            'classification' => $classData['classification'],
                            'amount' => round($classificationPaymentAmount, 2),
                            'payment_no' => $paymentCounter,
                            'sub_items' => $subItemsForPayment,
                            'is_adjustment' => $classData['is_adjustment'] ?? false,
                            'is_book_entry' => $classData['is_book_entry'] ?? false
                        ];

                        $paymentAmount += $classificationPaymentAmount;
                        $classData['remaining_amount'] -= $classificationPaymentAmount;
                    }
                }

                if (!empty($classificationsForPayment)) {
                    // Calculate total discounts, payments, and adjustments for this due date
                    // Using sequential discount distribution (earliest payments first)
                    $totalDiscountsForDueDate = 0;
                    $totalPaymentsForDueDate = 0;
                    $totalAdjustmentsForDueDate = 0;
                    $totalCreditAdjustmentsForDueDate = 0;
                    $discountDetails = [];
                    $paymentDetails = [];
                    $adjustmentDetails = [];
                    $creditAdjustmentDetails = [];

                    foreach ($classificationsForPayment as $classif) {
                        $classid = $classif['classid'];
                        $classificationAmount = $classif['amount'];
                        $isStandaloneAdjustment = $classif['is_adjustment'] ?? false;

                        // Get debit adjustment for this classification
                        // Skip if this is already a standalone adjustment (it's already in classifications array)
                        $adjustmentForClass = 0;
                        if (!$isStandaloneAdjustment && $studentAdjustments->has($classid)) {
                            $adjustment = $studentAdjustments->get($classid);
                            $adjustmentForClass = (float) $adjustment->total_adjustment;
                            $totalAdjustmentsForDueDate += $adjustmentForClass;
                            $adjustmentDetails[] = [
                                'classid' => $classid,
                                'amount' => $adjustmentForClass
                            ];

                            // Track this classid as matched
                            if (!in_array($classid, $matchedAdjustmentClassids)) {
                                $matchedAdjustmentClassids[] = $classid;
                            }
                        } elseif ($isStandaloneAdjustment) {
                            // For standalone adjustments, don't add to adjustment_details
                            // They're already displayed as separate classification items
                            // Just track as matched to prevent adding them again later
                            if (!in_array($classid, $matchedAdjustmentClassids)) {
                                $matchedAdjustmentClassids[] = $classid;
                            }
                        }

                        // Apply payments sequentially from the payment queue
                        $actualPaymentForClass = 0;
                        $appliedOrNumbers = [];

                        // Check if this is a book entry or standalone adjustment that needs particulars matching
                        $isBookEntry = $classif['is_book_entry'] ?? false;
                        $isStandaloneAdjustmentForPayment = $isStandaloneAdjustment;

                        if ($isBookEntry || $isStandaloneAdjustmentForPayment) {
                            // For book entries and standalone adjustments, match payments by particulars
                            $amountDueForClass = $classificationAmount + $adjustmentForClass;
                            $remainingDue = $amountDueForClass;

                            // Extract the particulars to match against
                            $particularsToMatch = '';
                            if ($isBookEntry) {
                                // For books, extract book title from description (format: "BOOK-{title}")
                                $particularsToMatch = str_replace('BOOK-', '', $classif['description']);

                                // Special handling: if book title is generic (like "Book #93"),
                                // it means the book ledger is missing, so match ANY "BOOK" payment
                                if (strpos($particularsToMatch, 'Book #') === 0) {
                                    $particularsToMatch = 'BOOK';
                                }
                            } elseif ($isStandaloneAdjustmentForPayment) {
                                // For adjustments, extract description (format: "ADJUSTMENT: {description}")
                                $particularsToMatch = str_replace('ADJUSTMENT: ', '', $classif['description']);
                            }

                            // Match payments by checking if payment particulars contain the book title or adjustment description
                            foreach ($paymentQueueByParticulars as $paymentParticulars => &$paymentsForParticulars) {
                                if (stripos($paymentParticulars, $particularsToMatch) !== false) {
                                    // This payment matches, apply it
                                    foreach ($paymentsForParticulars as &$payment) {
                                        if ($payment['remaining'] > 0 && $remainingDue > 0) {
                                            $amountToApply = min($payment['remaining'], $remainingDue);
                                            $actualPaymentForClass += $amountToApply;
                                            $payment['remaining'] -= $amountToApply;
                                            $remainingDue -= $amountToApply;

                                            if (!in_array($payment['ornum'], $appliedOrNumbers)) {
                                                $appliedOrNumbers[] = $payment['ornum'];
                                            }

                                            if ($remainingDue <= 0) {
                                                break 2; // Break both loops
                                            }
                                        }
                                    }
                                }
                            }
                            unset($payment, $paymentsForParticulars); // Break references
                        } else {
                            // For regular fees, match payments by classid and paymentsetupdetail_id
                            if (isset($paymentQueueByClass[$classid]) && !empty($paymentQueueByClass[$classid])) {
                                $amountDueForClass = $classificationAmount + $adjustmentForClass;
                                $remainingDue = $amountDueForClass;

                                // Get paymentsetupdetail_id from scheduleMatch if available
                                $schedulePaymentsetupdetailId = null;
                                if (isset($scheduleMatch) && property_exists($scheduleMatch, 'id')) {
                                    $schedulePaymentsetupdetailId = $scheduleMatch->id;
                                }

                                // Check if this is a laboratory fee
                                $isLaboratoryFee = ($classData['is_lab_fee'] ?? false);

                                // For laboratory fees OR when paymentsetupdetail_id is available,
                                // first try to match by paymentsetupdetail_id
                                $matchedPayments = [];
                                $unmatchedPayments = [];

                                if ($schedulePaymentsetupdetailId) {
                                    // Get expected itemids for laboratory fees
                                    $expectedItemids = [];
                                    if ($isLaboratoryFee && isset($classData['itemid'])) {
                                        $labFeeItemId = $classData['itemid']; // LAB FEE item from labfeesetup
                                        $expectedItemids[] = $labFeeItemId;

                                        // Get additional items from laboratory_fee_items
                                        // We need to get the laboratory_fee_id from labfees table
                                        $labFeeInfo = DB::table('labfees')
                                            ->where('syid', $syid)
                                            ->where('semid', $semid)
                                            ->where('deleted', 0)
                                            ->first();

                                        if ($labFeeInfo) {
                                            $additionalItems = DB::table('laboratory_fee_items')
                                                ->where('laboratory_fee_id', $labFeeInfo->id)
                                                ->where('deleted', 0)
                                                ->pluck('item_id')
                                                ->toArray();
                                            $expectedItemids = array_merge($expectedItemids, $additionalItems);
                                            $expectedItemids = array_unique($expectedItemids);
                                            sort($expectedItemids);
                                        }
                                    }

                                    foreach ($paymentQueueByClass[$classid] as $idx => &$payment) {
                                        if ($payment['remaining'] > 0) {
                                            $paymentMatches = false;

                                            if (
                                                isset($payment['paymentsetupdetail_id']) &&
                                                $payment['paymentsetupdetail_id'] == $schedulePaymentsetupdetailId
                                            ) {

                                                // For laboratory fees, also check itemids
                                                if ($isLaboratoryFee && !empty($expectedItemids)) {
                                                    if (!empty($payment['itemids']) && $payment['itemids'] !== '') {
                                                        $paymentItemids = explode(',', $payment['itemids']);
                                                        $paymentItemids = array_map('intval', array_filter($paymentItemids));
                                                        sort($paymentItemids);

                                                        // Check if payment itemids are a subset of expected itemids
                                                        $itemidsMatch = empty(array_diff($paymentItemids, $expectedItemids));
                                                        $paymentMatches = $itemidsMatch;
                                                    }
                                                } else {
                                                    // For non-laboratory fees, just match by paymentsetupdetail_id
                                                    $paymentMatches = true;
                                                }
                                            }

                                            if ($paymentMatches) {
                                                $matchedPayments[] = $idx;
                                            } else {
                                                $unmatchedPayments[] = $idx;
                                            }
                                        }
                                    }
                                    unset($payment); // Break reference

                                    // Apply matched payments first
                                    foreach ($matchedPayments as $idx) {
                                        if ($remainingDue > 0) {
                                            $payment = &$paymentQueueByClass[$classid][$idx];
                                            $amountToApply = min($payment['remaining'], $remainingDue);
                                            $actualPaymentForClass += $amountToApply;
                                            $payment['remaining'] -= $amountToApply;
                                            $remainingDue -= $amountToApply;

                                            if (!in_array($payment['ornum'], $appliedOrNumbers)) {
                                                $appliedOrNumbers[] = $payment['ornum'];
                                            }
                                        }
                                    }
                                    unset($payment); // Break reference

                                    // For non-laboratory fees, apply unmatched payments sequentially
                                    // For laboratory fees, do NOT apply unmatched payments
                                    if ($remainingDue > 0 && !$isLaboratoryFee) {
                                        foreach ($unmatchedPayments as $idx) {
                                            if ($remainingDue > 0) {
                                                $payment = &$paymentQueueByClass[$classid][$idx];
                                                if ($payment['remaining'] > 0) {
                                                    $amountToApply = min($payment['remaining'], $remainingDue);
                                                    $actualPaymentForClass += $amountToApply;
                                                    $payment['remaining'] -= $amountToApply;
                                                    $remainingDue -= $amountToApply;

                                                    if (!in_array($payment['ornum'], $appliedOrNumbers)) {
                                                        $appliedOrNumbers[] = $payment['ornum'];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    unset($payment); // Break reference
                                } else {
                                    // No paymentsetupdetail_id available, apply sequentially (old behavior)
                                    foreach ($paymentQueueByClass[$classid] as &$payment) {
                                        if ($payment['remaining'] > 0 && $remainingDue > 0) {
                                            $amountToApply = min($payment['remaining'], $remainingDue);
                                            $actualPaymentForClass += $amountToApply;
                                            $payment['remaining'] -= $amountToApply;
                                            $remainingDue -= $amountToApply;

                                            if (!in_array($payment['ornum'], $appliedOrNumbers)) {
                                                $appliedOrNumbers[] = $payment['ornum'];
                                            }
                                        }
                                    }
                                    unset($payment); // Break reference
                                }
                            }
                        }

                        if ($actualPaymentForClass > 0) {
                            $totalPaymentsForDueDate += $actualPaymentForClass;
                            $paymentDetails[] = [
                                'classid' => $classid,
                                'amount' => $actualPaymentForClass,
                                'or_numbers' => implode(',', $appliedOrNumbers)
                            ];
                        }

                        // Calculate balance before applying credit adjustment and discount
                        // Balance = Amount Due + Debit Adjustments - Actual Payments
                        $balanceBeforeCreditAndDiscount = $classificationAmount + $adjustmentForClass - $actualPaymentForClass;

                        // Apply credit adjustment sequentially (priority-based - earliest due dates first)
                        // Credit adjustments exhaust the balance of earlier months before moving to next month
                        $creditAdjustmentForClass = 0;
                        if ($balanceBeforeCreditAndDiscount > 0 && isset($remainingCreditAdjustmentsByClass[$classid]) && $remainingCreditAdjustmentsByClass[$classid] > 0) {
                            // Apply credit adjustment only up to the remaining balance
                            $creditToApply = min($remainingCreditAdjustmentsByClass[$classid], $balanceBeforeCreditAndDiscount);

                            if ($creditToApply > 0) {
                                $creditAdjustmentForClass = $creditToApply;
                                $totalCreditAdjustmentsForDueDate += $creditToApply;

                                $creditAdjustmentDetails[] = [
                                    'classid' => $classid,
                                    'amount' => $creditToApply
                                ];

                                // Deduct from remaining credit adjustment
                                $remainingCreditAdjustmentsByClass[$classid] -= $creditToApply;
                            }
                        }

                        // Calculate balance after credit adjustment, before discount
                        $balanceBeforeDiscount = $balanceBeforeCreditAndDiscount - $creditAdjustmentForClass;

                        // Only apply discount if there's still a balance to pay
                        if ($balanceBeforeDiscount > 0 && isset($remainingDiscountsByClass[$classid]) && $remainingDiscountsByClass[$classid] > 0) {
                            // Apply discount only up to the remaining balance
                            $discountToApply = min($remainingDiscountsByClass[$classid], $balanceBeforeDiscount);

                            if ($discountToApply > 0) {
                                $totalDiscountsForDueDate += $discountToApply;

                                // Get discount particulars for display
                                $discountParticulars = 'Discount';
                                if ($studentDiscounts->has($classid)) {
                                    $firstDiscount = $studentDiscounts->get($classid)->first();
                                    if ($firstDiscount) {
                                        $discountParticulars = $firstDiscount->particulars;
                                    }
                                }

                                $discountDetails[] = [
                                    'classid' => $classid,
                                    'amount' => $discountToApply,
                                    'particulars' => $discountParticulars
                                ];

                                // Deduct from remaining discount
                                $remainingDiscountsByClass[$classid] -= $discountToApply;
                            }
                        }
                    }

                    // Extract month name from due date
                    $monthName = strtoupper(date('F', strtotime($dueDate)));

                    // Check if this is the current month
                    $currentMonth = date('n'); // Current month number (1-12)
                    $currentYear = date('Y');
                    $dueMonth = date('n', strtotime($dueDate));
                    $dueYear = date('Y', strtotime($dueDate));
                    $isCurrentMonth = ($currentMonth == $dueMonth && $currentYear == $dueYear);

                    $monthlyPayables[] = [
                        'payment_no' => $paymentCounter++,
                        'payment_description' => $dueDateDescriptions[$dueDate] ?? null, // Add payment description from schedule
                        'particulars' => $monthName,
                        'due_date' => $dueDate,
                        'amount' => round($paymentAmount, 2),
                        'total_discounts' => round($totalDiscountsForDueDate, 2),
                        'total_adjustments' => round($totalAdjustmentsForDueDate, 2),
                        'total_credit_adjustments' => round($totalCreditAdjustmentsForDueDate, 2),
                        'total_payments' => round($totalPaymentsForDueDate, 2),
                        'balance' => round($paymentAmount + $totalAdjustmentsForDueDate - $totalCreditAdjustmentsForDueDate - $totalDiscountsForDueDate - $totalPaymentsForDueDate, 2),
                        'is_current_month' => $isCurrentMonth,
                        'discount_details' => $discountDetails,
                        'adjustment_details' => $adjustmentDetails,
                        'credit_adjustment_details' => $creditAdjustmentDetails,
                        'payment_details' => $paymentDetails,
                        'classifications' => $classificationsForPayment
                    ];
                }
            }

            // CASCADING LOGIC: Cascade excess payments and remaining discounts
            // Define priority order for fee classifications
            $priorityOrder = [
                1, // TUITION FEE (highest priority)
                4, // REGISTRATION FEE
                6, // LABORATORY FEES
                9, // COMPUTER FEE
                31, // SIM SYSTEM
                26, // INTRAMURAL'S CONTRIBUTION
                3, // OTHER FEES
                7, // ROBOTICS
            ];

            // Get classification names for labeling
            $classificationNames = DB::table('itemclassification')
                ->select('id', 'description')
                ->get()
                ->keyBy('id')
                ->map(function ($item) {
                    return $item->description;
                })
                ->toArray();

            // Step 1: Calculate total amounts and balances per classid from monthly payables
            $classidTotals = [];
            $classidBalances = [];
            $classidPayments = [];
            $classidDiscounts = [];
            $classidAdjustments = [];
            $classidCreditAdjustments = [];

            foreach ($monthlyPayables as $payable) {
                foreach ($payable['classifications'] as $classif) {
                    $classid = $classif['classid'];
                    $amount = $classif['amount'];

                    // Initialize if not exists (include ALL classids: numeric, books, adjustments)
                    if (!isset($classidTotals[$classid])) {
                        $classidTotals[$classid] = 0;
                        $classidBalances[$classid] = 0;
                        $classidPayments[$classid] = 0;
                        $classidDiscounts[$classid] = 0;
                        $classidAdjustments[$classid] = 0;
                        $classidCreditAdjustments[$classid] = 0;
                    }

                    $classidTotals[$classid] += $amount;
                }

                // Track totals from payment details (include ALL classids)
                foreach ($payable['payment_details'] ?? [] as $payDetail) {
                    $classid = $payDetail['classid'];
                    // Skip cascaded payments to avoid double counting
                    if (isset($payDetail['is_cascaded']) && $payDetail['is_cascaded']) {
                        continue;
                    }
                    $classidPayments[$classid] = ($classidPayments[$classid] ?? 0) + $payDetail['amount'];
                }

                // Track discounts (include ALL classids)
                foreach ($payable['discount_details'] ?? [] as $discDetail) {
                    $classid = $discDetail['classid'];
                    $classidDiscounts[$classid] = ($classidDiscounts[$classid] ?? 0) + $discDetail['amount'];
                }

                // Track adjustments (include ALL classids)
                foreach ($payable['adjustment_details'] ?? [] as $adjDetail) {
                    $classid = $adjDetail['classid'];
                    $classidAdjustments[$classid] = ($classidAdjustments[$classid] ?? 0) + $adjDetail['amount'];
                }

                // Track credit adjustments (include ALL classids)
                foreach ($payable['credit_adjustment_details'] ?? [] as $creditDetail) {
                    $classid = $creditDetail['classid'];
                    $classidCreditAdjustments[$classid] = ($classidCreditAdjustments[$classid] ?? 0) + $creditDetail['amount'];
                }
            }

            // Calculate balances: amount + adjustments - payments - discounts - credit_adjustments
            foreach ($classidTotals as $classid => $totalAmount) {
                $payments = $classidPayments[$classid] ?? 0;
                $discounts = $classidDiscounts[$classid] ?? 0;
                $adjustments = $classidAdjustments[$classid] ?? 0;
                $creditAdj = $classidCreditAdjustments[$classid] ?? 0;

                $balance = $totalAmount + $adjustments - $payments - $discounts - $creditAdj;
                $classidBalances[$classid] = max(0, $balance);
            }

            // Step 2: Calculate excess payments per classification
            // Get RAW payment totals from chrngcashtrans
            $rawPayments = DB::table('chrngtrans as ct')
                ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
                ->select('cct.classid', DB::raw('SUM(cct.amount) as total_paid'))
                ->where('ct.studid', $studid)
                ->where('ct.syid', $syid)
                ->where('cct.syid', $syid)
                ->when($student->levelid >= 14 && $student->levelid <= 25 && $semid, function ($q) use ($semid) {
                    $q->where(function ($subQ) use ($semid) {
                        $subQ->where('cct.semid', $semid)
                            ->orWhereNull('cct.semid');
                    });
                })
                ->where('ct.cancelled', 0)
                ->where('cct.deleted', 0)
                ->where('cct.classid', '>', 0)
                ->where('cct.particulars', 'NOT LIKE', '%BOOK%') // Exclude book payments
                ->groupBy('cct.classid')
                ->get()
                ->keyBy('classid');

            $excessPaymentsByClass = [];
            foreach ($classidTotals as $classid => $totalAmount) {
                if (!is_numeric($classid))
                    continue;

                $totalActualPayments = 0;
                if (isset($rawPayments[$classid])) {
                    $totalActualPayments = (float) $rawPayments[$classid]->total_paid;
                }

                $totalDiscountsForClass = $classidDiscounts[$classid] ?? 0;
                $totalCreditAdjForClass = $classidCreditAdjustments[$classid] ?? 0;
                $totalAdjForClass = $classidAdjustments[$classid] ?? 0;

                // Calculate net amount due
                $netAmountDue = $totalAmount + $totalAdjForClass - $totalDiscountsForClass - $totalCreditAdjForClass;
                $excessPayment = $totalActualPayments - $netAmountDue;

                if ($excessPayment > 0.01) { // Use small threshold to avoid floating point issues
                    $excessPaymentsByClass[$classid] = $excessPayment;
                }
            }

            // Step 3: Cascade excess payments to next priority classifications
            $cascadedPayments = [];
            foreach ($priorityOrder as $classid) {
                $cascadedPayments[$classid] = 0;
            }
            foreach (array_keys($classidBalances) as $classid) {
                if (!isset($cascadedPayments[$classid])) {
                    $cascadedPayments[$classid] = 0;
                }
            }

            $cascadePaymentSourceMap = [];

            // Sort excess payments by priority
            $sortedExcessPayments = [];
            foreach ($priorityOrder as $classid) {
                if (isset($excessPaymentsByClass[$classid])) {
                    $sortedExcessPayments[$classid] = $excessPaymentsByClass[$classid];
                }
            }

            // Process each classification with excess payment
            foreach ($sortedExcessPayments as $fromClassid => $excessAmount) {
                if ($excessAmount <= 0)
                    continue;

                $remainingExcess = $excessAmount;
                $fromIndex = array_search($fromClassid, $priorityOrder);

                if ($fromIndex !== false) {
                    // Cascade through remaining priority order classids
                    for ($i = $fromIndex + 1; $i < count($priorityOrder); $i++) {
                        $toClassid = $priorityOrder[$i];
                        $toClassBalance = $classidBalances[$toClassid] ?? 0;

                        if ($toClassBalance > 0 && $remainingExcess > 0) {
                            $transferAmount = min($remainingExcess, $toClassBalance);
                            $cascadedPayments[$toClassid] += $transferAmount;
                            $cascadePaymentSourceMap[$toClassid] = $fromClassid;
                            $remainingExcess -= $transferAmount;

                            if ($remainingExcess <= 0) {
                                break;
                            }
                        }
                    }

                    // If still has excess, cascade to non-priority classids
                    if ($remainingExcess > 0) {
                        foreach (array_keys($classidBalances) as $toClassid) {
                            if (in_array($toClassid, $priorityOrder)) {
                                continue;
                            }

                            $toClassBalance = $classidBalances[$toClassid] ?? 0;
                            if ($toClassBalance > 0 && $remainingExcess > 0) {
                                $transferAmount = min($remainingExcess, $toClassBalance);
                                $cascadedPayments[$toClassid] += $transferAmount;
                                $cascadePaymentSourceMap[$toClassid] = $fromClassid;
                                $remainingExcess -= $transferAmount;

                                if ($remainingExcess <= 0) {
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            // Step 4: Calculate remaining discounts and cascade them
            $cascadedDiscounts = [];
            foreach ($priorityOrder as $classid) {
                $cascadedDiscounts[$classid] = 0;
            }
            foreach (array_keys($classidBalances) as $classid) {
                if (!isset($cascadedDiscounts[$classid])) {
                    $cascadedDiscounts[$classid] = 0;
                }
            }

            $cascadeDiscountSourceMap = [];

            // Find classifications with remaining discounts (discount > balance)
            $classificationsWithRemainingDiscounts = [];
            foreach ($remainingDiscountsByClass as $classid => $totalDiscount) {
                if ($totalDiscount <= 0 || !is_numeric($classid))
                    continue;

                // Get the current balance for this classid (before any cascaded payments applied)
                $currentBalance = $classidBalances[$classid] ?? 0;

                // Remaining discount = discount that exceeds the balance
                $remainingDiscount = $totalDiscount - $currentBalance;
                if ($remainingDiscount > 0.01) {
                    $classificationsWithRemainingDiscounts[$classid] = $remainingDiscount;
                }
            }

            // Sort remaining discounts by priority
            $sortedRemainingDiscounts = [];
            foreach ($priorityOrder as $classid) {
                if (isset($classificationsWithRemainingDiscounts[$classid])) {
                    $sortedRemainingDiscounts[$classid] = $classificationsWithRemainingDiscounts[$classid];
                }
            }

            // Cascade remaining discounts to next priority classifications
            foreach ($sortedRemainingDiscounts as $fromClassid => $discountAmount) {
                if ($discountAmount <= 0)
                    continue;

                $remainingDiscount = $discountAmount;
                $fromIndex = array_search($fromClassid, $priorityOrder);

                if ($fromIndex !== false) {
                    // Cascade through remaining priority order classids
                    for ($i = $fromIndex + 1; $i < count($priorityOrder); $i++) {
                        $toClassid = $priorityOrder[$i];
                        $toClassBalance = $classidBalances[$toClassid] ?? 0;

                        if ($toClassBalance > 0 && $remainingDiscount > 0) {
                            $transferAmount = min($remainingDiscount, $toClassBalance);
                            $cascadedDiscounts[$toClassid] += $transferAmount;
                            $cascadeDiscountSourceMap[$toClassid] = $fromClassid;
                            $remainingDiscount -= $transferAmount;

                            if ($remainingDiscount <= 0) {
                                break;
                            }
                        }
                    }

                    // If still has remaining discount, cascade to non-priority classids
                    if ($remainingDiscount > 0) {
                        foreach (array_keys($classidBalances) as $toClassid) {
                            if (in_array($toClassid, $priorityOrder)) {
                                continue;
                            }

                            $toClassBalance = $classidBalances[$toClassid] ?? 0;
                            if ($toClassBalance > 0 && $remainingDiscount > 0) {
                                $transferAmount = min($remainingDiscount, $toClassBalance);
                                $cascadedDiscounts[$toClassid] += $transferAmount;
                                $cascadeDiscountSourceMap[$toClassid] = $fromClassid;
                                $remainingDiscount -= $transferAmount;

                                if ($remainingDiscount <= 0) {
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            // Step 5: Apply cascaded payments and discounts to monthly payables
            $cascadedPaymentsApplied = [];
            $cascadedDiscountsApplied = [];
            foreach (array_keys($classidBalances) as $classid) {
                $cascadedPaymentsApplied[$classid] = 0;
                $cascadedDiscountsApplied[$classid] = 0;
            }

            foreach ($monthlyPayables as &$payable) {
                $newPaymentDetails = $payable['payment_details'] ?? [];
                $newDiscountDetails = $payable['discount_details'] ?? [];
                $totalCascadedPaymentsForPayable = 0;
                $totalCascadedDiscountsForPayable = 0;

                foreach ($payable['classifications'] as &$classif) {
                    $classid = $classif['classid'];

                    // Calculate current balance for this classification in this payable
                    // Balance = amount - (payments already applied)
                    $classifAmount = $classif['amount'];

                    // Find existing payments for this classid in this payable (exclude cascaded to avoid double counting)
                    $existingPayments = 0;
                    foreach ($payable['payment_details'] ?? [] as $payDetail) {
                        if ($payDetail['classid'] == $classid && (!isset($payDetail['is_cascaded']) || !$payDetail['is_cascaded'])) {
                            $existingPayments += $payDetail['amount'];
                        }
                    }

                    $existingDiscounts = 0;
                    foreach ($payable['discount_details'] ?? [] as $discDetail) {
                        if ($discDetail['classid'] == $classid && (!isset($discDetail['is_cascaded']) || !$discDetail['is_cascaded'])) {
                            $existingDiscounts += $discDetail['amount'];
                        }
                    }

                    // For adjustments, also subtract the adjustment amount since it's already in adjustment_details
                    $existingAdjustments = 0;
                    if (isset($classif['is_adjustment']) && $classif['is_adjustment']) {
                        foreach ($payable['adjustment_details'] ?? [] as $adjDetail) {
                            if ($adjDetail['classid'] == $classid) {
                                $existingAdjustments += $adjDetail['amount'];
                            }
                        }
                    }

                    $currentBalance = $classifAmount - $existingPayments - $existingDiscounts;

                    // Apply cascaded payment if available
                    if ($currentBalance > 0 && isset($cascadedPayments[$classid]) && $cascadedPayments[$classid] > 0) {
                        $availablePayment = $cascadedPayments[$classid] - $cascadedPaymentsApplied[$classid];
                        $paymentToApply = min($availablePayment, $currentBalance);

                        if ($paymentToApply > 0.01) {
                            $cascadedPaymentsApplied[$classid] += $paymentToApply;
                            $totalCascadedPaymentsForPayable += $paymentToApply;

                            // Get source classification name
                            $sourceClassid = $cascadePaymentSourceMap[$classid] ?? null;
                            $sourceName = $sourceClassid
                                ? ($classificationNames[$sourceClassid] ?? 'Classification ' . $sourceClassid)
                                : 'Excess Payment';

                            $newPaymentDetails[] = [
                                'classid' => $classid,
                                'amount' => $paymentToApply,
                                'or_numbers' => '',
                                'is_cascaded' => true,
                                'source' => $sourceName
                            ];

                            $currentBalance -= $paymentToApply;
                        }
                    }

                    // Apply cascaded discount if available
                    if ($currentBalance > 0 && isset($cascadedDiscounts[$classid]) && $cascadedDiscounts[$classid] > 0) {
                        $availableDiscount = $cascadedDiscounts[$classid] - $cascadedDiscountsApplied[$classid];
                        $discountToApply = min($availableDiscount, $currentBalance);

                        if ($discountToApply > 0.01) {
                            $cascadedDiscountsApplied[$classid] += $discountToApply;
                            $totalCascadedDiscountsForPayable += $discountToApply;

                            // Get source classification name
                            $sourceClassid = $cascadeDiscountSourceMap[$classid] ?? null;
                            $sourceName = $sourceClassid
                                ? ($classificationNames[$sourceClassid] ?? 'Classification ' . $sourceClassid)
                                : 'Discount';

                            $newDiscountDetails[] = [
                                'classid' => $classid,
                                'amount' => $discountToApply,
                                'particulars' => 'Cascaded from ' . $sourceName,
                                'is_cascaded' => true,
                                'source' => $sourceName
                            ];

                            $currentBalance -= $discountToApply;
                        }
                    }
                }
                unset($classif);

                // Update payable totals with cascaded amounts
                if ($totalCascadedPaymentsForPayable > 0 || $totalCascadedDiscountsForPayable > 0) {
                    $payable['payment_details'] = $newPaymentDetails;
                    $payable['discount_details'] = $newDiscountDetails;
                    $payable['total_payments'] += $totalCascadedPaymentsForPayable;
                    $payable['total_discounts'] += $totalCascadedDiscountsForPayable;
                    $payable['balance'] -= ($totalCascadedPaymentsForPayable + $totalCascadedDiscountsForPayable);
                    $payable['balance'] = max(0, $payable['balance']);
                }
            }
            unset($payable);

            // Step 6: Calculate remaining overpayments (excess payments that weren't cascaded)
            $remainingOverpayments = [];
            $totalOverpayment = 0;

            // Track how much was cascaded out from each source
            $cascadedOutBySource = [];
            foreach ($excessPaymentsByClass as $classid => $excess) {
                $cascadedOutBySource[$classid] = 0;
            }

            // Calculate cascaded out amounts from the actual applied cascades
            foreach ($cascadePaymentSourceMap as $toClassid => $fromClassid) {
                $cascadedAmount = $cascadedPaymentsApplied[$toClassid] ?? 0;
                $cascadedOutBySource[$fromClassid] = ($cascadedOutBySource[$fromClassid] ?? 0) + $cascadedAmount;
            }

            // Remaining overpayment = excess - cascaded out
            foreach ($excessPaymentsByClass as $classid => $excess) {
                $cascadedOut = $cascadedOutBySource[$classid] ?? 0;
                $remaining = $excess - $cascadedOut;
                if ($remaining > 0.01) {
                    $classificationName = $classificationNames[$classid] ?? 'Classification ' . $classid;
                    $remainingOverpayments[] = [
                        'classid' => $classid,
                        'classification' => $classificationName,
                        'amount' => round($remaining, 2)
                    ];
                    $totalOverpayment += $remaining;
                }
            }

            // Build payments grouped by classid for items without schedule
            $paymentsByClassid = [];
            foreach ($studentPaymentsRaw as $payment) {
                $classid = $payment->classid;
                if (!isset($paymentsByClassid[$classid])) {
                    $paymentsByClassid[$classid] = [];
                }
                $paymentsByClassid[$classid][] = [
                    'ornum' => $payment->ornum,
                    'amount' => (float) $payment->amount,
                    'transdate' => $payment->transdate
                ];
            }

            // Group payments by classid and OR number for items without schedule
            $groupedPaymentsByClassid = [];
            foreach ($paymentsByClassid as $classid => $payments) {
                $groupedByOR = [];
                foreach ($payments as $payment) {
                    $ornum = $payment['ornum'];
                    if (!isset($groupedByOR[$ornum])) {
                        $groupedByOR[$ornum] = [
                            'ornum' => $ornum,
                            'amount' => 0,
                            'transdate' => $payment['transdate']
                        ];
                    }
                    $groupedByOR[$ornum]['amount'] += $payment['amount'];
                }
                $groupedPaymentsByClassid[$classid] = array_values($groupedByOR);
            }

            // Collect items without payment schedules (no due dates)
            $itemsWithoutSchedule = [];
            $totalWithoutSchedule = 0;
            $seenDetailIds = []; // Track seen detail_ids to prevent duplicates

            foreach ($classificationData as $classData) {
                // Check if this classification has no schedule, empty schedule, or schedule with NULL/invalid due dates
                $hasValidSchedule = false;
                if (!empty($classData['schedule_details']) && !$classData['schedule_details']->isEmpty()) {
                    // Check if any schedule has a valid due date (not null)
                    foreach ($classData['schedule_details'] as $schedule) {
                        if ($schedule->duedate !== null && $schedule->duedate !== '') {
                            $hasValidSchedule = true;
                            break;
                        }
                    }
                }

                // If no valid schedule, add to items without schedule
                if (!$hasValidSchedule) {
                    $detailId = $classData['detail_id'];

                    // Skip if we've already added this detail_id
                    if (in_array($detailId, $seenDetailIds)) {
                        continue;
                    }

                    $seenDetailIds[] = $detailId;

                    // Get payment details for this classification
                    $classid = $classData['classid'];
                    $paymentDetailsForClass = [];
                    $totalPaymentsForClass = 0;

                    if (isset($groupedPaymentsByClassid[$classid])) {
                        foreach ($groupedPaymentsByClassid[$classid] as $payment) {
                            $paymentDetailsForClass[] = [
                                'classid' => $classid,
                                'amount' => $payment['amount'],
                                'or_numbers' => $payment['ornum']
                            ];
                            $totalPaymentsForClass += $payment['amount'];
                        }
                    }

                    // Get discount details for this classification
                    $discountDetailsForClass = [];
                    $totalDiscountsForClass = 0;

                    if (isset($discountsByClassid[$classid])) {
                        foreach ($discountsByClassid[$classid] as $discount) {
                            $discountDetailsForClass[] = [
                                'classid' => $classid,
                                'amount' => $discount['amount'],
                                'particulars' => $discount['particulars'] ?? 'Discount'
                            ];
                            $totalDiscountsForClass += $discount['amount'];
                        }
                    }

                    // Get cascaded discounts for this classification
                    if (isset($cascadedDiscounts[$classid])) {
                        $sourceClassid = $cascadeDiscountSourceMap[$classid] ?? null;
                        $sourceName = $sourceClassid
                            ? ($classificationNames[$sourceClassid] ?? 'Classification ' . $sourceClassid)
                            : 'Discount';

                        $discountDetailsForClass[] = [
                            'classid' => $classid,
                            'amount' => $cascadedDiscounts[$classid],
                            'particulars' => 'Cascaded from ' . $sourceName,
                            'is_cascaded' => true,
                            'source' => $sourceName
                        ];
                        $totalDiscountsForClass += $cascadedDiscounts[$classid];
                    }

                    // Get adjustment details (debit/credit)
                    $adjustmentDetailsForClass = [];
                    $creditAdjustmentDetailsForClass = [];
                    $totalAdjustmentsForClass = 0;
                    $totalCreditAdjustmentsForClass = 0;

                    if (isset($debitAdjustmentsByClassid[$classid])) {
                        foreach ($debitAdjustmentsByClassid[$classid] as $adjustment) {
                            $adjustmentDetailsForClass[] = [
                                'classid' => $classid,
                                'amount' => $adjustment['amount'],
                                'particulars' => $adjustment['particulars'] ?? 'Adjustment'
                            ];
                            $totalAdjustmentsForClass += $adjustment['amount'];
                        }
                    }

                    if (isset($creditAdjustmentsByClassid[$classid])) {
                        foreach ($creditAdjustmentsByClassid[$classid] as $creditAdj) {
                            $creditAdjustmentDetailsForClass[] = [
                                'classid' => $classid,
                                'amount' => $creditAdj['amount'],
                                'particulars' => $creditAdj['particulars'] ?? 'Credit Adjustment'
                            ];
                            $totalCreditAdjustmentsForClass += $creditAdj['amount'];
                        }
                    }

                    // Calculate balance
                    $classAmount = round($classData['total_amount'], 2);
                    $classBalance = $classAmount + $totalAdjustmentsForClass - $totalCreditAdjustmentsForClass - $totalPaymentsForClass - $totalDiscountsForClass;

                    $itemsWithoutSchedule[] = [
                        'detail_id' => $detailId,
                        'classid' => $classData['classid'],
                        'description' => $classData['description'],
                        'classification' => $classData['classification'],
                        'amount' => $classAmount,
                        'total_payments' => round($totalPaymentsForClass, 2),
                        'total_discounts' => round($totalDiscountsForClass, 2),
                        'total_adjustments' => round($totalAdjustmentsForClass, 2),
                        'total_credit_adjustments' => round($totalCreditAdjustmentsForClass, 2),
                        'balance' => round(max(0, $classBalance), 2),
                        'payment_details' => $paymentDetailsForClass,
                        'discount_details' => $discountDetailsForClass,
                        'adjustment_details' => $adjustmentDetailsForClass,
                        'credit_adjustment_details' => $creditAdjustmentDetailsForClass,
                        'sub_items' => $classData['sub_items'],
                        'is_book_entry' => $classData['is_book_entry'] ?? false,
                        'is_old_account' => $classData['is_old_account'] ?? false
                    ];
                    $totalWithoutSchedule += $classData['total_amount'];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_amount' => $totalAmount,
                    'number_of_months' => count($monthlyPayables),
                    'monthly_payables' => $monthlyPayables,
                    'items_without_schedule' => $itemsWithoutSchedule,
                    'total_without_schedule' => round($totalWithoutSchedule, 2),
                    'overpayments' => $remainingOverpayments,
                    'total_overpayment' => round($totalOverpayment, 2),
                    'student_info' => [
                        'studid' => $studid,
                        'levelid' => $student->levelid,
                        'units' => $units
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching monthly assessment: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Helper method to get tuition header for a student
     */
    private function getStudentTuitionHeader($student, $syid, $semid)
    {
        // Determine enrollment table based on level
        $enrollTable = null;
        if ($student->levelid >= 1 && $student->levelid <= 13) {
            $enrollTable = 'enrolledstud';
        } elseif ($student->levelid >= 14 && $student->levelid <= 16) {
            $enrollTable = 'sh_enrolledstud';
        } elseif ($student->levelid >= 17 && $student->levelid <= 25) {
            $enrollTable = 'college_enrolledstud';
        }

        if (!$enrollTable) {
            return null;
        }

        // Get enrollment record to find feesid
        // Note: semid is optional for enrolledstud (basic education students)
        $enrollment = DB::table($enrollTable)
            ->where('studid', $student->id)
            ->where('syid', $syid)
            ->when($semid && $enrollTable !== 'enrolledstud', function ($q) use ($semid) {
                // Only apply semid filter for SHS and College, not for basic education
                $q->where('semid', $semid);
            })
            ->where('deleted', 0)
            ->first();

        $feesId = $enrollment->feesid ?? null;

        // Get tuitionheader
        if ($feesId) {
            return DB::table('tuitionheader')
                ->where('id', $feesId)
                ->where('deleted', 0)
                ->first();
        }

        // Fallback: try latest feesid via helper (studinfo then enrollments)
        $fallbackFeesId = $this->getLatestFeesId($student->id, (int) $student->levelid, $syid, $semid);
        if ($fallbackFeesId) {
            return DB::table('tuitionheader')
                ->where('id', $fallbackFeesId)
                ->where('deleted', 0)
                ->first();
        }

        // Fallback: match by level/course/strand
        $query = DB::table('tuitionheader')
            ->where('syid', $syid)
            ->where('levelid', $student->levelid)
            ->where('deleted', 0);

        if ($semid) {
            $query->where('semid', $semid);
        }

        if ($student->courseid) {
            $query->where('courseid', $student->courseid);
        } elseif ($student->strandid) {
            $query->where('strandid', $student->strandid);
        }

        return $query->first();
    }

    /**
     * Get payment receipt details
     */
    public function getPaymentReceipt(Request $request)
    {
        $data = $request->json()->all();

        $ornum = $data['ornum'] ?? null;
        $studid = $data['studid'] ?? null;

        if (!$ornum || !$studid) {
            return response()->json([
                'success' => false,
                'message' => 'OR Number and Student ID are required',
                'data' => null
            ], 400);
        }

        try {
            // Get main transaction header from chrngtrans
            $transaction = DB::table('chrngtrans as ct')
                ->leftJoin('studinfo as si', 'ct.studid', '=', 'si.id')
                ->leftJoin('gradelevel as gl', 'si.levelid', '=', 'gl.id')
                ->leftJoin('sy as sy', 'ct.syid', '=', 'sy.id')
                ->leftJoin('semester as sem', 'ct.semid', '=', 'sem.id')
                ->select(
                    'ct.ornum',
                    'ct.transdate',
                    'ct.totalamount',
                    'ct.amountpaid',
                    'ct.amounttendered',
                    'ct.change_amount',
                    'ct.paymenttype_id',
                    'ct.other_paymenttype_ids',
                    'ct.particulars as trans_particulars',
                    'ct.paytype',
                    'ct.refno',
                    'ct.accountname',
                    'ct.bankname',
                    'ct.chequeno',
                    'ct.chequedate',
                    'ct.creditcardno',
                    'ct.cardtype',
                    'ct.syid',
                    'ct.semid',
                    'ct.isonlinepay',
                    'si.levelid',
                    'si.courseid',
                    DB::raw("CONCAT(COALESCE(si.lastname, ''), ', ', COALESCE(si.firstname, ''),
                        CASE WHEN si.middlename IS NOT NULL AND si.middlename != ''
                            THEN CONCAT(' ', LEFT(si.middlename, 1), '.')
                            ELSE ''
                        END) as student_name"),
                    'si.sid as student_id',
                    'gl.levelname as grade_level',
                    'sy.sydesc as school_year',
                    'sem.semester as semester'
                )
                ->where('ct.ornum', $ornum)
                ->where('ct.studid', $studid)
                ->where('ct.cancelled', 0)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment receipt not found or has been cancelled',
                    'data' => null
                ], 404);
            }

            // Check if this is an online payment - use different table
            $isOnlinePayment = $transaction->isonlinepay == 1;

            if ($isOnlinePayment) {
                // For online payments, get items from chrngtransitems (item-level detail)
                $rawItems = DB::table('chrngtransitems as cti')
                    ->leftJoin('items as i', 'cti.itemid', '=', 'i.id')
                    ->leftJoin('itemclassification as ic', 'cti.classid', '=', 'ic.id')
                    ->select(
                        'i.description as item_description',
                        'ic.description as item_class',
                        'cti.amount',
                        'cti.itemid',
                        'cti.classid'
                    )
                    ->where('cti.ornum', $transaction->ornum)
                    ->where('cti.studid', $studid)
                    ->where('cti.deleted', 0)
                    ->orderBy('cti.classid')
                    ->get();

                // For online payments, items are already at item-level, so no breakdown needed
                $tuitionItemsBreakdown = collect();
            } else {
                // For regular payments, get from chrngcashtrans (classification-level breakdown)
                $rawItems = DB::table('chrngcashtrans as cct')
                    ->join('chrngtrans as ct', 'cct.transno', '=', 'ct.transno')
                    ->leftJoin('itemclassification as ic', 'cct.classid', '=', 'ic.id')
                    ->select(
                        'ic.description as item_class',
                        'cct.particulars',
                        'cct.amount',
                        'cct.classid',
                        'cct.syid as payment_syid',
                        'cct.semid as payment_semid',
                        'cct.paymentsetupdetail_id',
                        DB::raw('NULL as item_description'),
                        DB::raw('NULL as itemid')
                    )
                    ->where('ct.ornum', $transaction->ornum)
                    ->where('ct.studid', $studid)
                    ->where('cct.studid', $studid) // Filter chrngcashtrans by student ID
                    ->where('cct.deleted', 0)
                    ->orderBy('cct.classid')
                    ->get();

                // Also get tuition items breakdown from chrngtransitems for this transaction
                $tuitionItemsBreakdown = DB::table('chrngtransitems as cti')
                    ->leftJoin('items as i', 'cti.itemid', '=', 'i.id')
                    ->select(
                        'cti.classid',
                        'i.description as item_description',
                        'cti.amount'
                    )
                    ->where('cti.ornum', $transaction->ornum)
                    ->where('cti.studid', $studid)
                    ->where('cti.syid', $transaction->syid)
                    ->where(function ($q) use ($transaction) {
                        $q->where('cti.semid', $transaction->semid)
                            ->orWhereNull('cti.semid');
                    })
                    ->where('cti.deleted', 0)
                    ->orderBy('cti.classid')
                    ->orderBy('i.description')
                    ->get()
                    ->groupBy('classid');
            }

            // Get enrolled units for college students (level 17-25)
            $units = 0;
            if ($transaction->levelid >= 17 && $transaction->levelid <= 25) {
                $unitsResult = DB::table('college_loadsubject as cls')
                    ->join('college_classsched as cs', 'cls.schedid', '=', 'cs.id')
                    ->join('college_prospectus as cp', 'cs.subjectID', '=', 'cp.id')
                    ->select(DB::raw('SUM(cp.lecunits + cp.labunits) as totalunits'))
                    ->where('cls.studid', $studid)
                    ->where('cls.syid', $transaction->syid)
                    ->where('cls.semid', $transaction->semid)
                    ->where('cls.deleted', 0)
                    ->where(function ($q) {
                        $q->where('cls.isDropped', 0)
                            ->orWhereNull('cls.isDropped');
                    })
                    ->first();

                $units = $unitsResult && $unitsResult->totalunits ? (float) $unitsResult->totalunits : 0;
            }

            // Pre-calculate remaining balances for all classifications (ONE QUERY instead of per-item)
            $classIds = $rawItems->pluck('classid')->unique()->filter()->toArray();
            $remainingBalancesMap = [];
            $tuitionDetailsMap = [];
            if (!empty($classIds)) {
                // Get feesid from enrollment table to get the correct tuitionheader
                $feesId = null;
                $enrollTable = null;

                if ($transaction->levelid == 14 || $transaction->levelid == 15) {
                    $enrollTable = 'sh_enrolledstud';
                } elseif ($transaction->levelid >= 17 && $transaction->levelid <= 25) {
                    $enrollTable = 'college_enrolledstud';
                } elseif ($transaction->levelid == 26) {
                    $enrollTable = 'tesda_enrolledstud';
                } else {
                    $enrollTable = 'enrolledstud';
                }

                $enrollInfo = DB::table($enrollTable)
                    ->where('studid', $studid)
                    ->where('syid', $transaction->syid)
                    ->where(function ($q) use ($transaction) {
                        if ($transaction->levelid >= 17 && $transaction->levelid <= 25) {
                            $q->where('semid', $transaction->semid);
                        }
                    })
                    ->where('deleted', 0)
                    ->first();

                if ($enrollInfo && isset($enrollInfo->feesid)) {
                    $feesId = $enrollInfo->feesid;
                }

                // Get total fee amounts by classid from tuitiondetail WITH istuition flag
                $query = DB::table('tuitiondetail as td')
                    ->join('tuitionheader as th', 'td.headerid', '=', 'th.id')
                    ->whereIn('td.classificationid', $classIds)
                    ->where('td.deleted', 0)
                    ->where('th.deleted', 0)
                    ->where('th.syid', $transaction->syid);

                // Apply semester filter for college students
                if ($transaction->levelid >= 17 && $transaction->levelid <= 25) {
                    $query->where('th.semid', $transaction->semid);
                }

                // Use feesid if available, otherwise fall back to level/course matching
                if ($feesId) {
                    $query->where('th.id', $feesId);
                } else {
                    $query->where('th.levelid', $transaction->levelid)
                        ->where(function ($q) use ($transaction) {
                            $q->where('th.courseid', $transaction->courseid)
                                ->orWhereNull('th.courseid');
                        });
                }

                $totalFees = $query
                    ->select('td.classificationid as classid', DB::raw('SUM(td.amount) as amount'), DB::raw('MAX(td.istuition) as istuition'))
                    ->groupBy('td.classificationid')
                    ->get()
                    ->keyBy('classid');

                // Store tuition details for later use
                foreach ($totalFees as $classid => $feeData) {
                    $tuitionDetailsMap[$classid] = [
                        'base_amount' => (float) $feeData->amount,
                        'istuition' => $feeData->istuition == 1,
                        'units' => $units
                    ];
                }

                // Get total paid for each classification UP TO this transaction
                // Use chrngcashtrans for most payments, but chrngtransitems for online payments
                $totalPaidByClass = DB::table('chrngtrans as ct')
                    ->leftJoin('chrngcashtrans as cct', function ($join) {
                        $join->on('ct.transno', '=', 'cct.transno')
                            ->where('ct.isonlinepay', '!=', 1)
                            ->orWhereNull('ct.isonlinepay');
                    })
                    ->leftJoin('chrngtransitems as cti', function ($join) {
                        $join->on('ct.ornum', '=', 'cti.ornum')
                            ->where('ct.isonlinepay', '=', 1);
                    })
                    ->whereIn(DB::raw('COALESCE(cct.classid, cti.classid)'), $classIds)
                    ->where('ct.studid', $studid)
                    ->where('ct.syid', $transaction->syid)
                    ->where(function ($q) use ($transaction) {
                        $q->where('ct.semid', $transaction->semid)
                            ->orWhereNull('ct.semid');
                    })
                    ->where(function ($q) {
                        $q->where('cct.deleted', 0)
                            ->orWhere('cti.deleted', 0);
                    })
                    ->where('ct.cancelled', 0)
                    ->where('ct.transdate', '<=', $transaction->transdate)
                    ->groupBy(DB::raw('COALESCE(cct.classid, cti.classid)'))
                    ->select(
                        DB::raw('COALESCE(cct.classid, cti.classid) as classid'),
                        DB::raw('SUM(COALESCE(cct.amount, cti.amount)) as total_paid')
                    )
                    ->get()
                    ->keyBy('classid');

                // Calculate remaining balances
                foreach ($totalFees as $classid => $feeData) {
                    $baseAmount = (float) $feeData->amount;
                    $totalFeeAmount = $baseAmount;

                    // Multiply by units if this is tuition for college students
                    if ($feeData->istuition == 1 && $units > 0) {
                        $totalFeeAmount = $baseAmount * $units;
                    }

                    $totalPaid = isset($totalPaidByClass[$classid]) ? (float) $totalPaidByClass[$classid]->total_paid : 0;
                    $remainingBalance = $totalFeeAmount - $totalPaid;

                    $remainingBalancesMap[$classid] = max(0, $remainingBalance);
                }
            }

            // For each item, use pre-fetched data (NO INDIVIDUAL QUERIES)
            $items = $rawItems->map(function ($item) use ($remainingBalancesMap, $tuitionDetailsMap, $isOnlinePayment) {
                // Get remaining balance from pre-calculated map
                $remainingBalance = null;
                if ($item->classid && isset($remainingBalancesMap[$item->classid])) {
                    $remainingBalance = $remainingBalancesMap[$item->classid];
                }

                // Determine the description to display
                if ($isOnlinePayment) {
                    // For online payments, use item_description
                    $description = $item->item_description ?? $item->item_class ?? 'Payment';
                } else {
                    // For regular payments
                    if ($item->classid == 0) {
                        // classid = 0 means outside fees - use particulars column
                        $description = $item->particulars ?? 'Payment';
                    } else {
                        // Use classification description
                        $description = $item->item_class ?? 'Payment';
                    }
                }

                // Check if this is a tuition fee for college students
                $unitPrice = $item->amount;
                $qty = 1;
                $quantityLabel = null;

                if ($item->classid && isset($tuitionDetailsMap[$item->classid])) {
                    $tuitionDetail = $tuitionDetailsMap[$item->classid];
                    if ($tuitionDetail['istuition'] && $tuitionDetail['units'] > 0) {
                        // For tuition fees, show base_amount as unit price and units as quantity
                        $unitPrice = $tuitionDetail['base_amount'];
                        $qty = $tuitionDetail['units'];
                        $quantityLabel = $tuitionDetail['units'] . ' Units';
                    }
                }

                return (object) [
                    'item_description' => $description,
                    'item_class' => $item->item_class,
                    'unit_price' => $unitPrice,
                    'qty' => $qty,
                    'quantity_label' => $quantityLabel,
                    'amount' => $item->amount,
                    'remaining_balance' => $remainingBalance,
                    'classid' => $item->classid,
                    'payment_syid' => $item->payment_syid ?? null,
                    'payment_semid' => $item->payment_semid ?? null,
                    'paymentsetupdetail_id' => $item->paymentsetupdetail_id ?? null
                ];
            });

            // Group items by classid (classification), then sum amounts
            $groupedItems = [];
            // Map of sy/sem for label lookups
            $syMap = DB::table('sy')->pluck('sydesc', 'id');
            $semMap = DB::table('semester')->pluck('semester', 'id');
            // Map paymentsetupdetail_id to human-friendly label (description fallback to month of duedate)
            $psdLabels = [];
            $psdIds = $items->pluck('paymentsetupdetail_id')->filter()->unique()->values();
            if ($psdIds->isNotEmpty()) {
                $psdRows = DB::table('paymentsetupdetail')
                    ->select('id', 'description', 'duedate')
                    ->whereIn('id', $psdIds)
                    ->where('deleted', 0)
                    ->get();
                foreach ($psdRows as $row) {
                    $label = trim($row->description ?? '');
                    if ($label === '' && !empty($row->duedate)) {
                        try {
                            $label = strtoupper(\Carbon\Carbon::parse($row->duedate)->format('F'));
                        } catch (\Exception $e) {
                            $label = '';
                        }
                    }
                    $psdLabels[$row->id] = $label;
                }
            }

            // Build book entry installment map keyed by paymentsetupdetail_id for receipt display
            $bookInstallmentsByPsd = [];
            $bookEntriesForReceipt = DB::table('bookentries as be')
                ->leftJoin('items as i', 'be.bookid', '=', 'i.id')
                ->select('be.id', 'be.bookid', 'be.amount', 'be.mopid', 'be.semid', 'i.description as title')
                ->where('be.studid', $studid)
                ->where('be.syid', $transaction->syid)
                ->where('be.deleted', 0)
                ->whereIn('be.bestatus', ['DRAFT', 'POSTED', 'APPROVED'])
                ->when($transaction->levelid >= 17 && $transaction->levelid <= 25, function ($q) use ($transaction) {
                    $q->where('be.semid', $transaction->semid);
                })
                ->get();

            foreach ($bookEntriesForReceipt as $book) {
                if (!$book->mopid) {
                    continue;
                }

                $schedule = DB::table('paymentsetupdetail')
                    ->where('paymentid', $book->mopid)
                    ->where('deleted', 0)
                    ->orderBy('paymentno')
                    ->get();

                if ($schedule->isEmpty()) {
                    continue;
                }

                $scheduleCount = max(1, $schedule->count());
                foreach ($schedule as $psd) {
                    $suffix = $psdLabels[$psd->id] ?? '';
                    if ($suffix === '' && !empty($psd->duedate)) {
                        try {
                            $suffix = strtoupper(\Carbon\Carbon::parse($psd->duedate)->format('F'));
                        } catch (\Exception $e) {
                            $suffix = 'Payment ' . $psd->paymentno;
                        }
                    }
                    if ($suffix === '') {
                        $suffix = 'Payment ' . $psd->paymentno;
                    }

                    $title = $book->title ?? ('Book #' . $book->bookid);
                    $label = trim($title . ' - ' . $suffix);

                    $dueAmount = ($psd->percentamount && $psd->percentamount > 0)
                        ? ((float) $book->amount) * ($psd->percentamount / 100)
                        : ((float) $book->amount) / $scheduleCount;

                    if (!isset($bookInstallmentsByPsd[$psd->id])) {
                        $bookInstallmentsByPsd[$psd->id] = [];
                    }
                    $bookInstallmentsByPsd[$psd->id][] = [
                        'description' => $label,
                        'amount' => round($dueAmount, 2),
                    ];
                }
            }

            foreach ($items as $item) {
                $isMismatchedPeriod = !$isOnlinePayment && (
                    (!is_null($item->payment_syid) && $item->payment_syid != $transaction->syid) ||
                    (!is_null($item->payment_semid) && !is_null($transaction->semid) && $item->payment_semid != $transaction->semid)
                );

                $displayParticulars = $item->item_class ?? $item->item_description ?? 'N/A';

                // If this schedule corresponds to book entries, prepare book sub-items and overwrite totals
                $bookSubItemsForItem = [];
                $bookAmountForItem = null;
                if (
                    !$isOnlinePayment &&
                    !empty($item->paymentsetupdetail_id) &&
                    isset($bookInstallmentsByPsd[$item->paymentsetupdetail_id])
                ) {
                    foreach ($bookInstallmentsByPsd[$item->paymentsetupdetail_id] as $bi) {
                        $bookSubItemsForItem[] = [
                            'description' => $bi['description'],
                            'amount' => $bi['amount'],
                        ];
                    }
                    $bookAmountForItem = array_sum(array_column($bookInstallmentsByPsd[$item->paymentsetupdetail_id], 'amount'));
                    $displayParticulars = 'Books - ' . ($psdLabels[$item->paymentsetupdetail_id] ?? 'Payment ' . $item->paymentsetupdetail_id);
                }

                // Append paymentsetupdetail label for schedule-based items
                if (
                    !$isOnlinePayment &&
                    !empty($item->paymentsetupdetail_id) &&
                    isset($psdLabels[$item->paymentsetupdetail_id]) &&
                    $psdLabels[$item->paymentsetupdetail_id] !== '' &&
                    empty($bookSubItemsForItem)
                ) {
                    $displayParticulars = trim($displayParticulars . ' - ' . $psdLabels[$item->paymentsetupdetail_id]);
                }
                $baseKey = $item->classid ?? 'outside_fees';
                if ($isMismatchedPeriod) {
                    $syLabel = isset($syMap[$item->payment_syid]) ? $syMap[$item->payment_syid] : $item->payment_syid;
                    $semLabel = $item->payment_semid ? (isset($semMap[$item->payment_semid]) ? $semMap[$item->payment_semid] : $item->payment_semid) : 'N/A';
                    $displayParticulars = trim($displayParticulars . " (Old Accounts SY: {$syLabel} Semester: {$semLabel})");
                    // Ensure this old-account slice groups separately from current SY items
                    $groupKey = $baseKey . '|oldsy:' . ($item->payment_syid ?? 'na') . '|oldsem:' . ($item->payment_semid ?? 'na');
                } else {
                    // Group by paymentsetupdetail_id when available so each schedule shows distinctly
                    $groupKey = $baseKey;
                    if (!$isOnlinePayment && !empty($item->paymentsetupdetail_id)) {
                        $groupKey = $groupKey . '|psd:' . $item->paymentsetupdetail_id;
                    }
                }

                // Use classid as the key to group by classification
                $key = $groupKey;

                if (!isset($groupedItems[$key])) {
                    // Get sub-items breakdown for this classification if available
                    $subItems = !empty($bookSubItemsForItem) ? $bookSubItemsForItem : [];
                    // Only attach tuition item breakdown to CURRENT SY/SEM items to avoid duplicating old-account slices
                    if (
                        !$isOnlinePayment &&
                        !$isMismatchedPeriod &&
                        empty($item->paymentsetupdetail_id) &&
                        isset($tuitionItemsBreakdown) &&
                        $item->classid &&
                        isset($tuitionItemsBreakdown[$item->classid])
                    ) {
                        foreach ($tuitionItemsBreakdown[$item->classid] as $subItem) {
                            $subItems[] = [
                                'description' => $subItem->item_description ?? 'N/A',
                                'amount' => (float) $subItem->amount
                            ];
                        }
                    }

                    $groupedItems[$key] = [
                        'particulars' => $displayParticulars,
                        'item_class' => $displayParticulars,
                        'unit_price' => (float) ($bookAmountForItem ?? ($item->unit_price ?? 0)),
                        'quantity' => (int) $item->qty,
                        'quantity_label' => $item->quantity_label,
                        'amount' => (float) ($bookAmountForItem ?? $item->amount),
                        'remaining_balance' => $item->remaining_balance !== null ? (float) $item->remaining_balance : null,
                        'classid' => $item->classid,
                        'paymentsetupdetail_id' => $item->paymentsetupdetail_id,
                        'sub_items' => $subItems
                    ];
                } else {
                    // Same classification, sum the amount
                    $groupedItems[$key]['amount'] += (float) ($bookAmountForItem ?? $item->amount);
                    // Track additional paymentsetupdetail_id values if merged
                    if (!empty($item->paymentsetupdetail_id)) {
                        if (empty($groupedItems[$key]['paymentsetupdetail_ids'])) {
                            $groupedItems[$key]['paymentsetupdetail_ids'] = [];
                        }
                        $groupedItems[$key]['paymentsetupdetail_ids'][] = $item->paymentsetupdetail_id;
                        $groupedItems[$key]['paymentsetupdetail_ids'] = array_values(array_unique($groupedItems[$key]['paymentsetupdetail_ids']));
                    }
                }
            }

            // Get payment type descriptions
            $paymentTypeIds = [$transaction->paymenttype_id];
            if ($transaction->other_paymenttype_ids) {
                $otherIds = json_decode($transaction->other_paymenttype_ids, true);
                if (is_array($otherIds)) {
                    $paymentTypeIds = array_merge($paymentTypeIds, $otherIds);
                }
            }

            $paymentTypeIds = array_filter($paymentTypeIds);
            $paymentMethods = [];
            if (!empty($paymentTypeIds)) {
                $paymentTypes = DB::table('paymenttype')
                    ->select('id', 'description')
                    ->whereIn('id', $paymentTypeIds)
                    ->get()
                    ->keyBy('id');

                // Get primary payment type
                if ($transaction->paymenttype_id) {
                    $primaryType = $paymentTypes->get($transaction->paymenttype_id);
                    if ($primaryType) {
                        $paymentMethods[] = $primaryType->description;
                    }
                }

                // Get other payment types
                if ($transaction->other_paymenttype_ids) {
                    $otherIds = json_decode($transaction->other_paymenttype_ids, true);
                    if (is_array($otherIds)) {
                        foreach ($otherIds as $otherTypeId) {
                            $otherType = $paymentTypes->get($otherTypeId);
                            if ($otherType) {
                                $paymentMethods[] = $otherType->description;
                            }
                        }
                    }
                }
            }

            $paymentMethodDisplay = !empty($paymentMethods) ? implode(', ', $paymentMethods) : null;

            // Use change_amount from database if available; also compute fallback from tendered-total
            $changeFromDb = $transaction->change_amount !== null ? (float) $transaction->change_amount : null;
            $changeComputed = null;
            if ($transaction->amounttendered !== null && $transaction->totalamount !== null) {
                $changeComputed = (float) $transaction->amounttendered - (float) $transaction->totalamount;
            }
            // Prefer DB value when present; otherwise use computed; if both exist, take the larger positive (to avoid hidden change)
            if ($changeFromDb !== null && $changeComputed !== null) {
                $change = max($changeFromDb, $changeComputed);
            } elseif ($changeFromDb !== null) {
                $change = $changeFromDb;
            } elseif ($changeComputed !== null) {
                $change = $changeComputed;
            } else {
                $change = 0;
            }

            // Determine if CASH is involved
            $hasCash = false;
            foreach ($paymentMethods as $method) {
                if (strtoupper(trim($method)) === 'CASH') {
                    $hasCash = true;
                    break;
                }
            }

            // If no CASH payment involved, treat change as overpayment
            $overpayment = 0;
            if (!$hasCash && $change > 0) {
                $overpayment = $change;
                $change = 0;
            }

            $receipt = [
                'header' => [
                    'or_number' => $transaction->ornum,
                    'date' => $transaction->transdate,
                    'student_name' => $transaction->student_name,
                    'student_id' => $transaction->student_id,
                    'grade_level' => $transaction->grade_level,
                    'school_year' => $transaction->school_year,
                    'semester' => $transaction->semester,
                ],
                'items' => array_values($groupedItems),
                'summary' => [
                    'total_amount' => (float) $transaction->totalamount,
                    'amount_paid' => (float) $transaction->amountpaid,
                    'amount_tendered' => (float) $transaction->amounttendered,
                    'change' => (float) $change,
                    'overpayment' => (float) $overpayment,
                ],
                'payment_details' => [
                    'payment_type' => $transaction->paytype,
                    'payment_method' => $paymentMethodDisplay,
                    'paymenttype_id' => $transaction->paymenttype_id,
                    'other_paymenttype_ids' => $transaction->other_paymenttype_ids,
                    'reference_number' => $transaction->refno,
                    'change_amount' => $transaction->change_amount !== null ? (float) $transaction->change_amount : null,
                    'change_computed' => $changeComputed,
                    'account_name' => $transaction->accountname,
                    'bank_name' => $transaction->bankname,
                    'cheque_number' => $transaction->chequeno,
                    'cheque_date' => $transaction->chequedate,
                    'credit_card_number' => $transaction->creditcardno,
                    'card_type' => $transaction->cardtype,
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $receipt
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get installment information for the student
     */
    private function getInstallmentInfo($studid, $syid, $semid, $levelid)
    {
        // Get all payment schedules with balance
        $schedules = DB::table('tuitionheader')
            ->join('tuitiondetail', 'tuitionheader.id', '=', 'tuitiondetail.headerid')
            ->join('itemclassification', 'tuitiondetail.classificationid', '=', 'itemclassification.id')
            ->leftJoin('paymentsetupdetail as psd', 'tuitiondetail.pschemeid', '=', 'psd.paymentid')
            ->leftJoin(DB::raw('(
                SELECT classid, SUM(amount) as total_paid
                FROM chrngtransitems
                WHERE studid = ' . $studid . '
                    AND syid = ' . $syid . '
                    AND semid = ' . $semid . '
                    AND deleted = 0
                GROUP BY classid
            ) as payments'), 'itemclassification.id', '=', 'payments.classid')
            ->where('tuitionheader.syid', $syid)
            ->where('tuitionheader.semid', $semid)
            ->where('tuitionheader.levelid', $levelid)
            ->where('tuitionheader.deleted', 0)
            ->where('tuitiondetail.deleted', 0)
            ->where('psd.deleted', 0)
            ->select(
                'itemclassification.description as particulars',
                'tuitiondetail.amount',
                'psd.paymentno',
                'psd.duedate',
                'psd.percentamount',
                DB::raw('COALESCE(payments.total_paid, 0) as total_paid')
            )
            ->orderBy('psd.paymentno')
            ->get();

        $installments = [];
        $totalBalance = 0;

        // Group by particulars and calculate remaining installments
        $grouped = $schedules->groupBy('particulars');

        foreach ($grouped as $particular => $items) {
            $totalAmount = $items->first()->amount;
            $totalPaid = $items->first()->total_paid;
            $balance = $totalAmount - $totalPaid;

            if ($balance > 0 && $items->count() > 1) {
                // Count remaining installments (those with due dates in the future or unpaid)
                $today = now();
                $remainingCount = 0;
                $nextDueDate = null;

                foreach ($items as $item) {
                    $installmentAmount = ($totalAmount * $item->percentamount) / 100;

                    if ($totalPaid < $installmentAmount * $item->paymentno) {
                        $remainingCount++;
                        if (!$nextDueDate && $item->duedate) {
                            $nextDueDate = $item->duedate;
                        }
                    }
                }

                if ($remainingCount > 0) {
                    $installments[] = [
                        'particulars' => $particular,
                        'remaining_installments' => $remainingCount,
                        'remaining_balance' => $balance,
                        'next_due_date' => $nextDueDate,
                        'total_installments' => $items->count()
                    ];
                    $totalBalance += $balance;
                }
            }
        }

        return [
            'has_installments' => !empty($installments),
            'installments' => $installments,
            'total_remaining_balance' => $totalBalance
        ];
    }

    /**
     * Print Statement of Account
     */
    public function printSOA(Request $request)
    {
        $studid = $request->get('studid');
        $syid = $request->get('selectedschoolyear');
        $semid = $request->get('selectedsemester');

        // Validate required parameters
        if (!$studid || !$syid) {
            return response()->json([
                'success' => false,
                'message' => 'Student ID and School Year are required'
            ], 400);
        }

        // Get basic info
        $syRecord = DB::table('sy')->where('id', $syid)->first();
        $selectedschoolyear = $syRecord ? $syRecord->sydesc : '';
        $selectedsemester = '';
        if ($semid) {
            $semesterRecord = DB::table('semester')->where('id', $semid)->first();
            $selectedsemester = $semesterRecord ? $semesterRecord->semester : '';
        }

        $studinfo = DB::table('studinfo')
            ->select('id', 'sid', 'lastname', 'firstname', 'middlename', 'suffix')
            ->where('id', $studid)
            ->first();

        if (!$studinfo) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found'
            ], 404);
        }

        // Get enrollment info and determine level, section, course
        [$levelid, $sectionname, $courseabrv] = $this->getEnrollmentInfo($studid, $syid);

        // Add levelid to studinfo
        $studinfo->levelid = $levelid;

        // Get ledger data based on level (using your existing logic in StudentAccountV2Controller)
        $ledger = $this->getLedgerData($studid, $syid, $semid, $levelid);

        // Call generatePDF similar to V1 structure
        return $this->generateSOAPDF(
            $ledger,
            $studid,
            $syid,
            $semid,
            $selectedschoolyear,
            $selectedsemester,
            $studinfo,
            $levelid,
            $courseabrv,
            $sectionname
        );
    }

    /**
     * Get enrollment info - similar to V1 getEnrollmentInfo
     */
    private function getEnrollmentInfo($studid, $syid)
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
                    ->first();

                if ($estud) {
                    $levelid = $estud->levelid;
                    $course = DB::table('college_courses')->where('id', $estud->courseid)->first();
                    $courseabrv = $course ? $course->courseabrv : '';
                } else {
                    // Fallback to studinfo
                    $studinfo = DB::table('studinfo')->select('levelid')->where('id', $studid)->first();
                    if ($studinfo) {
                        $levelid = $studinfo->levelid;
                    }
                }
            }
        }

        return [$levelid, $sectionname, $courseabrv];
    }

    /**
     * Get ledger data based on level using tuitionheader and tuitiondetail
     */
    private function getLedgerData($studid, $syid, $semid, $levelid)
    {
        // Get student's course/program info
        $studinfo = DB::table('studinfo')->where('id', $studid)->first();
        if (!$studinfo) {
            return collect();
        }

        $courseid = $studinfo->courseid ?? null;
        $strandid = $studinfo->strandid ?? null;

        // Get the feesid from enrolled student table (determines which tuitionheader to use)
        $feesId = null;
        $enrollTable = null;

        if ($levelid == 14 || $levelid == 15) {
            $enrollTable = 'sh_enrolledstud';
        } elseif ($levelid >= 17 && $levelid <= 25) {
            $enrollTable = 'college_enrolledstud';
        } elseif ($levelid == 26) {
            $enrollTable = 'tesda_enrolledstud';
        } else {
            $enrollTable = 'enrolledstud';
        }

        $enrollInfo = DB::table($enrollTable)
            ->where('studid', $studid)
            ->where('syid', $syid)
            ->where(function ($q) use ($semid, $levelid) {
                if ($levelid >= 17 && $levelid <= 25) {
                    $q->where('semid', $semid);
                }
            })
            ->where('deleted', 0)
            ->first();

        if ($enrollInfo && isset($enrollInfo->feesid)) {
            $feesId = $enrollInfo->feesid;
        }

        // Get base fees from tuitionheader/tuitiondetail
        $query = DB::table('tuitionheader as th')
            ->join('tuitiondetail as td', 'th.id', '=', 'td.headerid')
            ->join('itemclassification as ic', 'td.classificationid', '=', 'ic.id')
            ->leftJoin('chrngsetup as cs', 'ic.id', '=', 'cs.classid')
            ->select(
                'td.id',
                'ic.id as classid',
                'ic.description as particulars',
                'td.amount',
                'td.amount as payables', // For SOA template compatibility
                DB::raw('0 as payment'), // No payments in this query
                'td.istuition',
                'td.persubj',
                'td.pschemeid',
                'cs.groupname',
                DB::raw('COALESCE(td.createddatetime, NOW()) as createddatetime') // Add createddatetime with fallback
            )
            ->where('th.syid', $syid)
            ->where('th.deleted', 0)
            ->where('td.deleted', 0)
            ->where('ic.deleted', 0);

        // Apply semester filter based on level
        if ($levelid == 14 || $levelid == 15) {
            // SHS
            $query->where(function ($q) use ($semid) {
                $schoolInfoRecord = DB::table('schoolinfo')->first();
                if ($schoolInfoRecord && $schoolInfoRecord->shssetup == 0 && $semid) {
                    $q->where('th.semid', $semid);
                }
            });
        } elseif ($levelid >= 17 && $levelid <= 25) {
            // College
            $query->where('th.semid', $semid);
        }

        // If feesid is found, use it; otherwise fall back to level/course/strand matching
        if ($feesId) {
            $query->where('th.id', $feesId);
        } else {
            $query->where('th.levelid', $levelid)
                ->where(function ($q) use ($courseid, $strandid) {
                    $q->where(function ($subQ) use ($courseid) {
                        if ($courseid) {
                            $subQ->where('th.courseid', $courseid);
                        }
                    })->orWhere(function ($subQ) use ($strandid) {
                        if ($strandid) {
                            $subQ->where('th.strandid', $strandid);
                        }
                    })->orWhere(function ($subQ) {
                        $subQ->whereNull('th.courseid')->whereNull('th.strandid');
                    });
                });
        }

        $fees = $query->distinct()->get();

        // Calculate actual amounts based on units/subjects for tuition and per-subject fees
        foreach ($fees as $fee) {
            $baseAmount = $fee->amount;
            $particulars = $fee->particulars;

            // For college students (level 17-25)
            if ($levelid >= 17 && $levelid <= 25) {
                // If istuition=1, multiply by units using the same logic as processCollegeStudentFees
                $units = $this->getStudentUnits($studid, $syid, $semid, $levelid);
                if ($fee->istuition == 1 && $units > 0) {
                    $fee->amount = $baseAmount * $units;
                    $fee->payables = $fee->amount;
                    $fee->particulars = $particulars . ' | ' . $units . ' Units';
                }

                // If persubj=1, multiply by number of subjects
                if ($fee->persubj == 1) {
                    $subjectCount = DB::table('college_loadsubject')
                        ->join('college_prospectus', 'college_loadsubject.subjectID', '=', 'college_prospectus.id')
                        ->where('college_loadsubject.studid', $studid)
                        ->where('college_loadsubject.syid', $syid)
                        ->where('college_loadsubject.semid', $semid)
                        ->where('college_loadsubject.deleted', 0)
                        ->count();

                    if ($subjectCount > 0) {
                        $fee->amount = $baseAmount * $subjectCount;
                        $fee->payables = $fee->amount;
                        $fee->particulars = $particulars . ' | ' . $subjectCount . ' Subjects';
                    }
                }
            } else {
                // Non-college logic
                if ($fee->istuition == 1) {
                    $units = $this->getStudentUnits($studid, $syid, $semid, $levelid);
                    $fee->amount = $baseAmount * $units;
                    $fee->payables = $fee->amount;
                } elseif ($fee->persubj == 1) {
                    $subjectCount = $this->getStudentSubjectCount($studid, $syid, $semid, $levelid);
                    $fee->amount = $baseAmount * $subjectCount;
                    $fee->payables = $fee->amount;
                } else {
                    // Regular fees - ensure amount and payables are set
                    $fee->payables = $fee->amount;
                }
            }

            // Add runbal field for compatibility with SOA template
            $fee->runbal = $fee->amount; // Will be recalculated in template
        }

        // Add old accounts/balance forward from studledger
        $balForwardRecord = DB::table('balforwardsetup')->first();
        $balclassid = $balForwardRecord ? $balForwardRecord->classid : null;
        if ($balclassid) {
            $oldAccounts = DB::table('studledger as sl')
                ->leftJoin('itemclassification as ic', 'sl.classid', '=', 'ic.id')
                ->leftJoin('chrngsetup as cs', 'sl.classid', '=', 'cs.classid')
                ->select(
                    'sl.id',
                    'sl.classid',
                    DB::raw("COALESCE(sl.particulars, ic.description, 'Old Account') as particulars"),
                    'sl.amount',
                    'sl.amount as payables',
                    DB::raw('0 as payment'),
                    DB::raw('0 as istuition'),
                    DB::raw('0 as persubj'),
                    DB::raw('NULL as pschemeid'),
                    'cs.groupname',
                    DB::raw('COALESCE(sl.createddatetime, NOW()) as createddatetime')
                )
                ->where('sl.studid', $studid)
                ->where('sl.syid', $syid)
                ->where('sl.classid', $balclassid)
                ->where('sl.amount', '>', 0)
                ->where('sl.deleted', 0)
                ->where('sl.void', 0);

            // Apply semester filter if needed
            if ($semid && ($levelid >= 17 && $levelid <= 25)) {
                $oldAccounts->where('sl.semid', $semid);
            }

            $fees = $fees->merge($oldAccounts->get());
        }

        // Get discounts to reduce amounts
        $discounts = DB::table('studdiscounts')
            ->where('studid', $studid)
            ->where('syid', $syid)
            ->where('deleted', 0)
            ->where('posted', 1) // Only posted discounts
            ->when($semid && ($levelid >= 14 && $levelid <= 25), function ($q) use ($semid) {
                $q->where('semid', $semid);
            })
            ->get();

        // Apply discounts to fees by classid (distribute proportionally if multiple items have same classid)
        foreach ($discounts as $discount) {
            $classid = $discount->classid;
            $totalDiscountAmount = $discount->discamount ?? 0;

            // Find all fees with this classid and calculate total
            $feesWithClassid = $fees->filter(function ($fee) use ($classid) {
                return $fee->classid == $classid;
            });

            if ($feesWithClassid->isEmpty()) {
                continue;
            }

            $totalAmount = $feesWithClassid->sum('amount');

            // Apply discount proportionally to each fee with this classid
            foreach ($fees as $fee) {
                if ($fee->classid == $classid) {
                    $proportion = $totalAmount > 0 ? ($fee->amount / $totalAmount) : 0;
                    $discountForThisFee = $totalDiscountAmount * $proportion;

                    $fee->amount = ($fee->amount ?? 0) - $discountForThisFee;
                    $fee->payables = ($fee->payables ?? 0) - $discountForThisFee;
                    $fee->runbal = ($fee->runbal ?? 0) - $discountForThisFee;
                }
            }
        }

        // Get debit adjustments (add to payables as charges)
        // IMPORTANT: All debit adjustments are displayed as separate line items,
        // regardless of whether their classid exists in the student's payables.
        // This allows adding charges for classifications not originally in the student's fees.
        $debitAdjustments = DB::table('adjustmentdetails as ad')
            ->join('adjustments as a', 'ad.headerid', '=', 'a.id')
            ->leftJoin('itemclassification as ic', 'a.classid', '=', 'ic.id')
            ->leftJoin('chrngsetup as cs', function ($join) {
                $join->on('a.classid', '=', 'cs.classid')
                    ->where('cs.deleted', '=', 0)
                    ->limit(1);
            })
            ->select(
                'a.id',
                'ad.id as adjustmentdetail_id',
                'a.classid',
                DB::raw("CONCAT('ADJUSTMENT: ', COALESCE(ic.description, a.description, 'Debit Adjustment')) as particulars"),
                'a.amount',
                'a.amount as payables',
                DB::raw('0 as payment'),
                DB::raw('0 as istuition'),
                DB::raw('0 as persubj'),
                'a.mop as pschemeid',
                DB::raw('MAX(cs.groupname) as groupname'),
                DB::raw('COALESCE(a.createddatetime, NOW()) as createddatetime')
            )
            ->where('ad.studid', $studid)
            ->where('a.syid', $syid)
            ->where('a.isdebit', 1)
            ->where('a.amount', '>', 0) // Only include debit adjustments with positive amounts
            ->where('ad.deleted', 0)
            ->where('a.deleted', 0)
            ->when($semid && ($levelid >= 14 && $levelid <= 25), function ($q) use ($semid) {
                $q->where('a.semid', $semid);
            })
            ->groupBy('a.id', 'ad.id', 'a.classid', 'a.description', 'ic.description', 'a.amount', 'a.mop', 'a.createddatetime')
            ->get();

        // Add all debit adjustments to fees as separate line items
        foreach ($debitAdjustments as $adj) {
            $adj->runbal = $adj->amount;
            $fees->push($adj);
        }

        // Get credit adjustments (reduce payables as payments)
        // IMPORTANT: Credit adjustments ONLY apply to fees with matching classid.
        // They reduce the amount of existing fees but are NOT shown as separate line items.
        // This ensures credits only apply where there's an actual charge to offset.
        $creditAdjustments = DB::table('adjustmentdetails as ad')
            ->join('adjustments as a', 'ad.headerid', '=', 'a.id')
            ->select('a.classid', 'a.amount')
            ->where('ad.studid', $studid)
            ->where('a.syid', $syid)
            ->where('a.iscredit', 1)
            ->where('a.amount', '>', 0) // Only include credit adjustments with positive amounts
            ->where('ad.deleted', 0)
            ->where('a.deleted', 0)
            ->when($semid && ($levelid >= 14 && $levelid <= 25), function ($q) use ($semid) {
                $q->where('a.semid', $semid);
            })
            ->get();

        // Apply credit adjustments to reduce amounts (strict classid matching)
        foreach ($creditAdjustments as $adj) {
            $creditApplied = false;
            foreach ($fees as $fee) {
                if ($fee->classid == $adj->classid) {
                    $adjAmount = $adj->amount ?? 0;
                    $fee->amount = ($fee->amount ?? 0) - $adjAmount;
                    $fee->payables = ($fee->payables ?? 0) - $adjAmount;
                    $fee->runbal = ($fee->runbal ?? 0) - $adjAmount;
                    $creditApplied = true;
                }
            }
            // Note: If no matching classid found, the credit adjustment is not applied
            // This is intentional - credits must match existing charges
        }

        // Get book entries from bookentries table
        $bookEntries = DB::table('bookentries as be')
            ->leftJoin('items as i', 'be.bookid', '=', 'i.id')
            ->leftJoin('itemclassification as ic', 'be.classid', '=', 'ic.id')
            ->leftJoin('chrngsetup as cs', 'be.classid', '=', 'cs.classid')
            ->select(
                'be.id',
                'be.classid',
                DB::raw("CONCAT('BOOK-', COALESCE(i.description, CONCAT('Book #', be.bookid))) as particulars"),
                'be.amount',
                'be.amount as payables',
                DB::raw('0 as payment'),
                DB::raw('0 as istuition'),
                DB::raw('0 as persubj'),
                DB::raw('NULL as pschemeid'),
                'cs.groupname',
                DB::raw('COALESCE(be.createddatetime, NOW()) as createddatetime')
            )
            ->where('be.studid', $studid)
            ->where('be.syid', $syid)
            ->where('be.amount', '>', 0)
            ->where('be.deleted', 0)
            ->whereIn('be.bestatus', ['DRAFT', 'POSTED', 'APPROVED']) // Include DRAFT status to match payment schedule
            ->when($semid && ($levelid >= 14 && $levelid <= 25), function ($q) use ($semid) {
                $q->where('be.semid', $semid);
            })
            ->get();

        // Add runbal to book entries and add to fees
        foreach ($bookEntries as $book) {
            $book->runbal = $book->amount ?? 0;
            $book->classid = 'BOOK_' . $book->id; // Use unique identifier for book entries
            $fees->push($book);
        }

        // Ensure all fees have required properties
        foreach ($fees as $fee) {
            $fee->amount = $fee->amount ?? 0;
            $fee->payables = $fee->payables ?? 0;
            $fee->runbal = $fee->runbal ?? 0;
        }

        // Filter out fees where amount is 0 or negative (only show payables with amount > 0)
        $fees = $fees->filter(function ($fee) {
            return $fee->amount > 0;
        });

        return $fees;
    }

    /**
     * Get student total units
     */
    private function getStudentUnits($studid, $syid, $semid, $levelid)
    {
        if ($levelid >= 17 && $levelid <= 25) {
            // College
            $studentUnits = DB::table('college_loadsubject')
                ->join('college_prospectus', 'college_loadsubject.subjectID', '=', 'college_prospectus.id')
                ->where('college_loadsubject.studid', $studid)
                ->where('college_loadsubject.syid', $syid)
                ->where('college_loadsubject.semid', $semid)
                ->where('college_loadsubject.deleted', 0)
                ->select('college_prospectus.lecunits', 'college_prospectus.labunits', 'college_prospectus.subjectID')
                ->get();

            $totalUnits = 0;
            foreach ($studentUnits as $unit) {
                // Check if subject has assessment unit
                $assessmentUnit = DB::table('tuition_assessmentunit')
                    ->where('subjid', $unit->subjectID)
                    ->where('deleted', 0)
                    ->first();

                if ($assessmentUnit) {
                    $totalUnits += 1.5;
                } else {
                    $totalUnits += $unit->lecunits + $unit->labunits;
                }
            }
            return $totalUnits;
        }

        return 0;
    }

    /**
     * Get student subject count
     */
    private function getStudentSubjectCount($studid, $syid, $semid, $levelid)
    {
        if ($levelid >= 17 && $levelid <= 25) {
            // College
            return DB::table('college_loadsubject')
                ->where('studid', $studid)
                ->where('syid', $syid)
                ->where('semid', $semid)
                ->where('deleted', 0)
                ->count();
        } elseif ($levelid == 14 || $levelid == 15) {
            // SHS
            return DB::table('sh_studsched')
                ->join('sh_classsched', 'sh_studsched.schedid', '=', 'sh_classsched.id')
                ->where('sh_studsched.studid', $studid)
                ->where('sh_studsched.deleted', 0)
                ->where('sh_classsched.deleted', 0)
                ->where('sh_classsched.syid', $syid)
                ->where('sh_classsched.semid', $semid)
                ->count();
        }

        return 0;
    }

    /**
     * Generate PDF - same structure as V1 generatePDF
     */
    private function generateSOAPDF(
        $ledger,
        $studid,
        $syid,
        $semid,
        $selectedschoolyear,
        $selectedsemester,
        $studinfo,
        $levelid,
        $courseabrv,
        $sectionname
    ) {
        // Get payments from chrngtrans/chrngcashtrans (regular payments)
        // Exclude discount entries as discounts are already subtracted from payables
        $payments = DB::table('chrngtrans as ct')
            ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
            ->selectRaw('
                ct.studid,
                ct.syid,
                ct.semid,
                cct.classid,
                cct.particulars,
                cct.amount as payment,
                ct.ornum,
                ct.transdate as createddatetime
            ')
            ->where('ct.studid', $studid)
            ->where('ct.syid', $syid)
            ->where('ct.cancelled', 0)
            ->where(function ($q) {
                $q->where('cct.particulars', 'NOT LIKE', '%DISCOUNT%')
                    ->orWhereNull('cct.particulars');
            })
            ->when($semid, function ($q) use ($semid) {
                $q->where('ct.semid', $semid);
            })
            ->orderBy('ct.transdate', 'desc')
            ->get();

        // Get old account payments from studledger (only for balance forward)
        $balForwardRecord = DB::table('balforwardsetup')->first();
        $balclassid = $balForwardRecord ? $balForwardRecord->classid : null;
        if ($balclassid) {
            $oldPayments = DB::table('studledger')
                ->select(
                    'studid',
                    'syid',
                    'semid',
                    'classid',
                    'particulars',
                    'payment',
                    'ornum',
                    'createddatetime'
                )
                ->where('studid', $studid)
                ->where('syid', $syid)
                ->where('payment', '>', 0)
                ->where('classid', $balclassid)
                ->where('deleted', 0)
                ->where('void', 0)
                ->when($semid, function ($q) use ($semid) {
                    $q->where('semid', $semid);
                })
                ->get();

            // Merge old payments with regular payments
            $payments = $payments->merge($oldPayments);
        }

        // Get credit adjustments (show as payment/credit transactions)
        $creditAdjustments = DB::table('adjustmentdetails as ad')
            ->join('adjustments as a', 'ad.headerid', '=', 'a.id')
            ->leftJoin('itemclassification as ic', 'a.classid', '=', 'ic.id')
            ->select(
                'ad.studid',
                'a.syid',
                'a.semid',
                'a.classid',
                DB::raw("CONCAT('CREDIT ADJUSTMENT: ', COALESCE(a.description, ic.description, 'Credit')) as particulars"),
                'a.amount as payment',
                DB::raw("NULL as ornum"),
                DB::raw("COALESCE(a.createddatetime, NOW()) as createddatetime")
            )
            ->where('ad.studid', $studid)
            ->where('a.syid', $syid)
            ->where('a.iscredit', 1)
            ->where('a.amount', '>', 0)
            ->where('ad.deleted', 0)
            ->where('a.deleted', 0)
            ->when($semid && ($levelid >= 14 && $levelid <= 25), function ($q) use ($semid) {
                $q->where('a.semid', $semid);
            })
            ->groupBy('ad.studid', 'a.syid', 'a.semid', 'a.classid', 'a.description', 'ic.description', 'a.amount', 'a.createddatetime')
            ->get();

        // Merge credit adjustments with payments
        $payments = $payments->merge($creditAdjustments);

        // Get discounts (show as payment/credit transactions)
        $discounts = DB::table('studdiscounts as sd')
            ->join('discounts as d', 'sd.discountid', '=', 'd.id')
            ->select(
                'sd.studid',
                'sd.syid',
                'sd.semid',
                'sd.classid',
                DB::raw("CONCAT('DISCOUNT: ', d.particulars) as particulars"),
                'sd.discamount as payment',
                DB::raw("NULL as ornum"),
                DB::raw("COALESCE(sd.createddatetime, NOW()) as createddatetime")
            )
            ->where('sd.studid', $studid)
            ->where('sd.syid', $syid)
            ->where('sd.deleted', 0)
            ->where('sd.posted', 1)
            ->when($semid && ($levelid >= 14 && $levelid <= 25), function ($q) use ($semid) {
                $q->where('sd.semid', $semid);
            })
            ->get();

        // Merge discounts with payments
        $payments = $payments->merge($discounts);

        // Sort all payments by date descending
        $payments = $payments->sortByDesc('createddatetime')->values();

        // Get monthly assessment data with discounts AND payments applied
        // Payments are matched by classid and applied sequentially to earliest due dates
        $monthdue = [];

        // Get student info
        $student = DB::table('studinfo')
            ->select('id', 'levelid', 'courseid', 'strandid')
            ->where('id', $studid)
            ->where('deleted', 0)
            ->first();

        if ($student) {
            // Get the feesid from enrolled student table
            $feesId = null;
            $enrollTable = null;

            if ($student->levelid == 14 || $student->levelid == 15) {
                $enrollTable = 'sh_enrolledstud';
            } elseif ($student->levelid >= 17 && $student->levelid <= 25) {
                $enrollTable = 'college_enrolledstud';
            } elseif ($student->levelid == 26) {
                $enrollTable = 'tesda_enrolledstud';
            } else {
                $enrollTable = 'enrolledstud';
            }

            $enrollInfo = DB::table($enrollTable)
                ->where('studid', $studid)
                ->where('syid', $syid)
                ->where(function ($q) use ($semid, $student) {
                    if ($student->levelid >= 17 && $student->levelid <= 25) {
                        $q->where('semid', $semid);
                    }
                })
                ->where('deleted', 0)
                ->first();

            if ($enrollInfo && isset($enrollInfo->feesid)) {
                $feesId = $enrollInfo->feesid;
            }

            // Get tuition setup from tuitionheader/tuitiondetail
            $query = DB::table('tuitionheader as th')
                ->join('tuitiondetail as td', 'th.id', '=', 'td.headerid')
                ->join('itemclassification as ic', 'td.classificationid', '=', 'ic.id')
                ->select([
                    'td.id as tuitiondetailid',
                    'td.classificationid as classid',
                    'ic.description as particulars',
                    'td.amount',
                    'td.pschemeid',
                    'td.istuition',
                    'td.persubj',
                    'td.permop',
                    'td.permopid'
                ])
                ->where('th.syid', $syid)
                ->where('th.semid', $semid)
                ->where('th.deleted', 0)
                ->where('td.deleted', 0)
                ->where('ic.deleted', 0);

            if ($feesId) {
                $query->where('th.id', $feesId);
            } else {
                $query->where('th.levelid', $student->levelid)
                    ->where(function ($q) use ($student) {
                        $q->where(function ($subQ) use ($student) {
                            if ($student->courseid) {
                                $subQ->where('th.courseid', $student->courseid);
                            }
                        })->orWhere(function ($subQ) use ($student) {
                            if ($student->strandid) {
                                $subQ->where('th.strandid', $student->strandid);
                            }
                        })->orWhere(function ($subQ) {
                            $subQ->whereNull('th.courseid')->whereNull('th.strandid');
                        });
                    });
            }

            $tuitionFees = $query->get();

            if (!$tuitionFees->isEmpty()) {
                // Get enrolled units for college students
                $units = 0;
                if ($student->levelid >= 17 && $student->levelid <= 25) {
                    $unitsResult = DB::table('college_loadsubject as cls')
                        ->join('college_prospectus as cp', 'cls.subjectID', '=', 'cp.id')
                        ->select(DB::raw('SUM(cp.lecunits + cp.labunits) as totalunits'))
                        ->where('cls.studid', $studid)
                        ->where('cls.syid', $syid)
                        ->where('cls.semid', $semid)
                        ->where('cls.deleted', 0)
                        ->where('cls.isDropped', 0)
                        ->first();

                    $units = $unitsResult && $unitsResult->totalunits ? (float) $unitsResult->totalunits : 0;
                }

                // Get discounts (priority-based application)
                $discounts = DB::table('studdiscounts')
                    ->select('classid', DB::raw('SUM(discamount) as total_discount'))
                    ->where('studid', $studid)
                    ->where('syid', $syid)
                    ->where(function ($q) use ($semid, $student) {
                        if ($student->levelid >= 17 && $student->levelid <= 25) {
                            $q->where('semid', $semid);
                        }
                    })
                    ->where('deleted', 0)
                    ->where('posted', 1)
                    ->groupBy('classid')
                    ->get()
                    ->keyBy('classid');

                // Get payments for this student (needed for monthly assessment calculation)
                $paymentsForAssessment = DB::table('chrngtrans as ct')
                    ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
                    ->select('cct.classid', DB::raw('SUM(cct.amount) as total_paid'))
                    ->where('ct.studid', $studid)
                    ->where('ct.syid', $syid)
                    ->where('ct.cancelled', 0)
                    ->when($semid, function ($q) use ($semid) {
                        $q->where('ct.semid', $semid);
                    })
                    ->groupBy('cct.classid')
                    ->get()
                    ->keyBy('classid');

                // Build payment schedule for each fee item WITH classid tracking
                $allSchedules = [];

                foreach ($tuitionFees as $fee) {
                    $baseAmount = (float) $fee->amount;
                    $classid = $fee->classid;

                    // Calculate total amount based on fee type
                    $totalAmount = $baseAmount;
                    $particulars = $fee->particulars;

                    // For college students
                    if ($student->levelid >= 17 && $student->levelid <= 25) {
                        if ($fee->istuition == 1 && $units > 0) {
                            $totalAmount = $baseAmount * $units;
                            $particulars .= ' | ' . $units . ' Units';
                        }

                        if ($fee->persubj == 1) {
                            $subjectCount = DB::table('college_studsched as css')
                                ->join('college_classsched as cs', 'css.schedid', '=', 'cs.id')
                                ->where('css.studid', $studid)
                                ->where('cs.syid', $syid)
                                ->where('cs.semesterID', $semid)
                                ->where('css.deleted', 0)
                                ->where('css.dropped', 0)
                                ->where('cs.deleted', 0)
                                ->distinct('cs.subjectID')
                                ->count('cs.subjectID');

                            if ($subjectCount > 0) {
                                $totalAmount *= $subjectCount;
                            }
                        }

                        if ($fee->permop == 1 && $fee->permopid) {
                            $paymentSetup = DB::table('paymentsetup')
                                ->where('id', $fee->permopid)
                                ->first();

                            if ($paymentSetup && $paymentSetup->noofpayment) {
                                $totalAmount *= $paymentSetup->noofpayment;
                            }
                        }
                    }

                    // Get total discount for this classid
                    $totalDiscount = $discounts->has($classid) ? (float) $discounts->get($classid)->total_discount : 0;

                    // Get total payment for this classid
                    $totalPayment = $paymentsForAssessment->has($classid) ? (float) $paymentsForAssessment->get($classid)->total_paid : 0;

                    // Get payment schedule
                    $paymentSchedule = collect();
                    if ($fee->pschemeid) {
                        $paymentSchedule = DB::table('paymentsetupdetail')
                            ->select('paymentno', 'duedate', 'percentamount', 'description')
                            ->where('paymentid', $fee->pschemeid)
                            ->where('deleted', 0)
                            ->orderBy('paymentno')
                            ->get();
                    }

                    if (!$paymentSchedule->isEmpty()) {
                        // Distribute across payment schedule
                        $remainingDiscount = $totalDiscount;
                        $remainingPayment = $totalPayment;
                        $scheduleCount = $paymentSchedule->count();

                        foreach ($paymentSchedule as $schedule) {
                            // Calculate due amount for this installment
                            if ($schedule->percentamount && $schedule->percentamount > 0) {
                                $dueAmount = $totalAmount * ($schedule->percentamount / 100);
                            } else {
                                $dueAmount = $totalAmount / $scheduleCount;
                            }

                            // Apply discount priority-based (earliest due dates first)
                            $discountForThisSchedule = 0;
                            if ($remainingDiscount > 0) {
                                $discountForThisSchedule = min($remainingDiscount, $dueAmount);
                                $remainingDiscount -= $discountForThisSchedule;

                                // NOTE: Excess discount cascading is now handled by cascadeRemainingDiscounts()
                                // which runs after all fees have been added to the schedule.
                                // That ensures book entries and standalone adjustments can receive cascaded discounts.
                            }

                            $balanceAfterDiscount = $dueAmount - $discountForThisSchedule;

                            // Apply payment priority-based (earliest due dates first)
                            $paymentForThisSchedule = 0;
                            if ($remainingPayment > 0) {
                                $paymentForThisSchedule = min($remainingPayment, $balanceAfterDiscount);
                                $remainingPayment -= $paymentForThisSchedule;
                            }

                            $finalBalance = $balanceAfterDiscount - $paymentForThisSchedule;

                            if ($schedule->duedate) {
                                $allSchedules[] = [
                                    'duedate' => $schedule->duedate,
                                    'classid' => $classid,
                                    'amount' => $dueAmount,
                                    'balance' => $finalBalance,
                                ];
                            }
                        }
                    }
                }

                // Group by month
                $monthlyTotals = [];
                foreach ($allSchedules as $sched) {
                    $duedate = $sched['duedate'];
                    $monthKey = strtoupper(date('F', strtotime($duedate))) . '-' . date('Y', strtotime($duedate));
                    $monthName = strtoupper(date('F', strtotime($duedate)));

                    if (!isset($monthlyTotals[$monthKey])) {
                        $monthlyTotals[$monthKey] = [
                            'month_name' => $monthName,
                            'duedate' => $duedate,
                            'amount' => 0,
                            'balance' => 0,
                            'year' => date('Y', strtotime($duedate)),
                            'month_num' => date('n', strtotime($duedate))
                        ];
                    }

                    $monthlyTotals[$monthKey]['amount'] += $sched['amount'];
                    $monthlyTotals[$monthKey]['balance'] += $sched['balance'];
                }

                // Sort by year and month
                uasort($monthlyTotals, function ($a, $b) {
                    if ($a['year'] != $b['year']) {
                        return $a['year'] <=> $b['year'];
                    }
                    return $a['month_num'] <=> $b['month_num'];
                });

                // Build monthdue array
                foreach ($monthlyTotals as $monthData) {
                    $monthdue[] = (object) [
                        'duedate' => $monthData['duedate'],
                        'particulars' => $monthData['month_name'],
                        'amount' => round($monthData['amount'], 2),
                        'amountpay' => 0, // Template will apply payments from $payments collection
                        'balance' => round($monthData['balance'], 2),
                    ];
                }
            }
        }

        // Empty monthsetup for now
        $monthsetup = (object) ['monthid' => 0, 'description' => ''];
        $monthinword = '';

        // Calculate running balance: Total Assessment - Total Payments
        // Note: ledger already has discounts subtracted
        $totalAssessment = $ledger->sum('amount');
        $totalPayments = $payments->sum('payment');
        $runningBalance = $totalAssessment - $totalPayments;

        // Determine which template to use based on configuration
        // First check if there's an active template in soa_template_config
        $templateConfig = 'finance/reports/pdf/pdf_statementofacct_v3';

        if ($templateConfig) {
            $view = $templateConfig;
        } else {
            // Fallback to school-based template selection
            $schoolInfoRecord = DB::table('schoolinfo')->first();
            $view = ($schoolInfoRecord && strtolower($schoolInfoRecord->abbreviation) == 'taborin')
                ? 'finance/reports/pdf/pdf_statementofacct_default_v2_gtc'
                : 'finance/reports/pdf/pdf_statementofacct_default_v2';
        }

        // Create class alias for lowercase 'db' (template uses lowercase db::)
        if (!class_exists('db')) {
            class_alias('Illuminate\Support\Facades\DB', 'db');
        }

        // Generate PDF using same template as V1
        $pdf = PDF::loadView($view, compact(
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
            'sectionname',
            'syid',
            'semid',
            'runningBalance'
        ))->setPaper('letter');

        return $pdf->stream('Statement_Of_Account.pdf');
    }

    /**
     * Toggle discount posted status (post/unpost)
     * Used in transaction logs to post or unpost individual discounts
     */
    public function toggleDiscountPosted(Request $request)
    {
        $data = $request->json()->all();

        $discountId = $data['discount_id'] ?? null;
        $studid = $data['studid'] ?? null;
        $classid = $data['classid'] ?? null;
        $syid = $data['syid'] ?? null;
        $semid = $data['semid'] ?? null;

        try {
            if (!$studid || !$classid || !$syid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student ID, Class ID, and School Year are required'
                ], 400);
            }

            // Get the discount record
            $discount = DB::table('studdiscounts')
                ->where('studid', $studid)
                ->where('classid', $classid)
                ->where('syid', $syid)
                ->where('deleted', 0);

            if ($semid) {
                $discount->where('semid', $semid);
            }

            if ($discountId) {
                $discount->where('id', $discountId);
            }

            $discount = $discount->first();

            if (!$discount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Discount record not found'
                ], 404);
            }

            // Toggle the posted status
            $newPostedStatus = $discount->posted == 1 ? 0 : 1;

            DB::table('studdiscounts')
                ->where('id', $discount->id)
                ->update([
                    'posted' => $newPostedStatus,
                    'updatedby' => auth()->id(),
                    'updateddatetime' => \Carbon\Carbon::now('Asia/Manila')
                ]);

            return response()->json([
                'success' => true,
                'message' => $newPostedStatus == 1 ? 'Discount posted successfully' : 'Discount unposted successfully',
                'posted' => $newPostedStatus
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Grant exam permit to selected students
     * Only for enrolled students in current/active school year and semester with balance > 0
     */
    public function grantExamPermit(Request $request)
    {
        try {
            $studentIds = $request->input('student_ids', []);
            $syid = $request->input('syid');
            $semid = $request->input('semid');

            if (empty($studentIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No students selected'
                ], 400);
            }

            if (!$syid) {
                return response()->json([
                    'success' => false,
                    'message' => 'School year is required'
                ], 400);
            }

            $grantedCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($studentIds as $studid) {
                // Verify student is enrolled in the specified SY/Sem
                $student = DB::table('studinfo')->where('id', $studid)->first();

                if (!$student) {
                    $skippedCount++;
                    continue;
                }

                // Determine enrollment table based on level
                $enrollTable = 'enrolledstud';
                if ($student->levelid == 14 || $student->levelid == 15) {
                    $enrollTable = 'sh_enrolledstud';
                } elseif ($student->levelid >= 17 && $student->levelid <= 25) {
                    $enrollTable = 'college_enrolledstud';
                } elseif ($student->levelid == 26) {
                    $enrollTable = 'tesda_enrolledstud';
                }

                // Check if student is enrolled
                $enrollQuery = DB::table($enrollTable)
                    ->where('studid', $studid)
                    ->where('syid', $syid)
                    ->where('deleted', 0);

                // Add semester check for college, SHS, and higher ed
                if (in_array($student->levelid, [14, 15]) || ($student->levelid >= 17 && $student->levelid <= 25)) {
                    if ($semid) {
                        $enrollQuery->where('semid', $semid);
                    }
                }

                $isEnrolled = $enrollQuery->exists();

                if (!$isEnrolled) {
                    $skippedCount++;
                    continue;
                }

                // Check if permit already exists
                $existingPermit = DB::table('permittoexam')
                    ->where('studid', $studid)
                    ->where('syid', $syid)
                    ->where('deleted', 0);

                if ($semid) {
                    $existingPermit->where('semid', $semid);
                }

                if ($existingPermit->exists()) {
                    // Update existing permit
                    $existingPermit->update([
                        'updatedby' => auth()->id(),
                        'updateddatetime' => now(),
                    ]);
                } else {
                    // Create new permit
                    DB::table('permittoexam')->insert([
                        'studid' => $studid,
                        'syid' => $syid,
                        'semid' => $semid,
                        'quarterid' => null, // Can be set if needed
                        'createdby' => auth()->id(),
                        'createddatetime' => now(),
                        'deleted' => 0,
                    ]);
                }

                $grantedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully granted exam permit to {$grantedCount} student(s)." .
                    ($skippedCount > 0 ? " {$skippedCount} student(s) were skipped (not enrolled or other issues)." : ""),
                'granted_count' => $grantedCount,
                'skipped_count' => $skippedCount,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revoke exam permit for a student
     */
    public function revokeExamPermit(Request $request)
    {
        try {
            $studid = $request->input('student_id');
            $syid = $request->input('syid');
            $semid = $request->input('semid');

            // Validation
            if (!$studid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student ID is required.'
                ], 400);
            }

            if (!$syid) {
                return response()->json([
                    'success' => false,
                    'message' => 'School year is required.'
                ], 400);
            }

            // Get student info
            $student = DB::table('studinfo')->where('id', $studid)->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found.'
                ], 404);
            }

            // Find the permit record
            $permitQuery = DB::table('permittoexam')
                ->where('studid', $studid)
                ->where('syid', $syid)
                ->where('deleted', 0);

            if ($semid) {
                $permitQuery->where('semid', $semid);
            }

            $permit = $permitQuery->first();

            if (!$permit) {
                return response()->json([
                    'success' => false,
                    'message' => 'No exam permit found for this student in the selected period.'
                ], 404);
            }

            // Soft delete the permit record
            DB::table('permittoexam')
                ->where('id', $permit->id)
                ->update([
                    'deleted' => 1,
                    'deletedby' => auth()->id(),
                    'deleteddatetime' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully revoked exam permit for {$student->firstname} {$student->lastname}."
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    public function validateNoDownpaymentEligibility(Request $request)
    {
        // Convert select_all to boolean properly (handles "false" string, 0, null, etc)
        $selectAll = filter_var($request->input('select_all', false), FILTER_VALIDATE_BOOLEAN);
        $studentIds = $request->input('student_ids', []);
        $filters = $request->input('filters', []);

        // Debug logging
        \Log::info('=== Validate No DP Request ===', [
            'select_all_raw' => $request->input('select_all'),
            'select_all_converted' => $selectAll,
            'student_ids_count' => count($studentIds),
            'student_ids' => $studentIds,
            'filters' => $filters
        ]);

        try {
            // Get ACTIVE school year (isactive = 1)
            $activeSchoolYear = DB::table('sy')
                ->where('isactive', 1)
                ->first();

            if (!$activeSchoolYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active school year found. Please set an active school year first.',
                    'eligible_count' => 0,
                    'total_count' => 0
                ], 400);
            }

            // Get ACTIVE semester (isactive = 1) - optional
            $activeSemester = DB::table('semester')
                ->where('isactive', 1)
                ->first();

            $schoolYear = $activeSchoolYear->id;
            $semester = $activeSemester ? $activeSemester->id : null;
            $periodText = $semester ? "{$activeSchoolYear->sydesc} - {$activeSemester->semester}" : $activeSchoolYear->sydesc;

            // Get enrolled student IDs for the ACTIVE school year/semester
            $enrolledStudentIds = $this->getEnrolledStudentIds($schoolYear, $semester, null);

            // Build query for eligible students with proper joins
            $query = DB::table('studinfo as si')
                ->leftJoin('gradelevel as gl', 'gl.id', '=', 'si.levelid')
                ->where('si.studstatus', 0)
                ->where('si.nodp', 0)
                ->whereNotIn('si.id', $enrolledStudentIds);

            // Apply the same exclusion criteria as frontend checkbox logic
            // Exclude students where:
            // - gradelevel_nodp = 1 (gradelevel has no downpayment flag)
            // - grantee = 2 AND esc = 1 (ESC students)
            // - grantee = 3 AND voucher = 1 (Voucher students)
            $query->where(function ($q) {
                $q->whereNull('gl.nodp')
                    ->orWhere('gl.nodp', '!=', 1);
            });

            $query->where(function ($q) {
                $q->where(function ($subQ) {
                    // Not ESC student
                    $subQ->where('si.grantee', '!=', 2)
                        ->orWhere('gl.esc', '!=', 1)
                        ->orWhereNull('gl.esc');
                })
                    ->where(function ($subQ) {
                        // Not Voucher student
                        $subQ->where('si.grantee', '!=', 3)
                            ->orWhere('gl.voucher', '!=', 1)
                            ->orWhereNull('gl.voucher');
                    });
            });

            // Apply active/deleted filters
            if (Schema::hasColumn('studinfo', 'deleted')) {
                $query->where('si.deleted', 0);
            }
            if (Schema::hasColumn('studinfo', 'studisactive')) {
                $query->where('si.studisactive', 1);
            }

            if ($selectAll) {
                // When select_all is true, apply the same filters as the main table
                // Note: school_year and semester are already filtered via enrollment check above
                if (!empty($filters['academic_program'])) {
                    $query->where('gl.acadprogid', $filters['academic_program']);
                }
                if (!empty($filters['academic_level'])) {
                    $query->where('si.levelid', $filters['academic_level']);
                }
                if (!empty($filters['section'])) {
                    $query->where('si.sectionid', $filters['section']);
                }
                if (!empty($filters['course'])) {
                    $query->where('si.courseid', $filters['course']);
                }
                if (!empty($filters['strand'])) {
                    $query->where('si.strandid', $filters['strand']);
                }
                if (!empty($filters['scholarship'])) {
                    $query->where('si.grantee', $filters['scholarship']);
                }
            } else {
                // Use specific student IDs
                if (empty($studentIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No students selected.',
                        'eligible_count' => 0,
                        'total_count' => 0
                    ], 400);
                }

                // Debug logging
                \Log::info('Validate No DP - Individual Selection', [
                    'select_all' => $selectAll,
                    'student_ids' => $studentIds,
                    'student_ids_count' => count($studentIds)
                ]);

                $query->whereIn('si.id', $studentIds);
            }

            $eligibleCount = $query->count();
            $totalCount = $selectAll ? $eligibleCount : count($studentIds);

            // Debug logging
            \Log::info('Validate No DP - Results', [
                'select_all' => $selectAll,
                'eligible_count' => $eligibleCount,
                'total_count' => $totalCount,
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);

            return response()->json([
                'success' => true,
                'eligible_count' => $eligibleCount,
                'total_count' => $totalCount,
                'period' => $periodText,
                'select_all' => $selectAll,
                'message' => $eligibleCount > 0
                    ? "{$eligibleCount}" . ($selectAll ? "" : " of {$totalCount}") . " student(s) are eligible for no downpayment in {$periodText}."
                    : "None of the selected students are eligible for no downpayment in {$periodText}."
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'eligible_count' => 0,
                'total_count' => 0
            ], 500);
        }
    }

    /**
     * Allow no downpayment for selected students
     * Only available for students with studstatus = 0 (Not Enrolled)
     * in the ACTIVE school year and semester (isactive = 1)
     * Supports select_all flag to avoid max_input_vars limit
     */
    public function allowNoDownpayment(Request $request)
    {
        // Convert select_all to boolean properly (handles "false" string, 0, null, etc)
        $selectAll = filter_var($request->input('select_all', false), FILTER_VALIDATE_BOOLEAN);
        $studentIds = $request->input('student_ids', []);
        $filters = $request->input('filters', []);

        try {
            // Get ACTIVE school year (isactive = 1)
            $activeSchoolYear = DB::table('sy')
                ->where('isactive', 1)
                ->first();

            if (!$activeSchoolYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active school year found. Please set an active school year first.'
                ], 400);
            }

            // Get ACTIVE semester (isactive = 1) - optional
            $activeSemester = DB::table('semester')
                ->where('isactive', 1)
                ->first();

            $schoolYear = $activeSchoolYear->id;
            $semester = $activeSemester ? $activeSemester->id : null;
            $periodText = $semester ? "{$activeSchoolYear->sydesc} - {$activeSemester->semester}" : $activeSchoolYear->sydesc;

            // Get enrolled student IDs for the ACTIVE school year/semester
            $enrolledStudentIds = $this->getEnrolledStudentIds($schoolYear, $semester, null);

            // Build query for valid students with proper joins and exclusions
            $query = DB::table('studinfo as si')
                ->leftJoin('gradelevel as gl', 'gl.id', '=', 'si.levelid')
                ->where('si.studstatus', 0)
                ->where('si.nodp', 0)
                ->whereNotIn('si.id', $enrolledStudentIds);

            // Apply the same exclusion criteria as frontend checkbox logic
            $query->where(function ($q) {
                $q->whereNull('gl.nodp')
                    ->orWhere('gl.nodp', '!=', 1);
            });

            $query->where(function ($q) {
                $q->where(function ($subQ) {
                    // Not ESC student
                    $subQ->where('si.grantee', '!=', 2)
                        ->orWhere('gl.esc', '!=', 1)
                        ->orWhereNull('gl.esc');
                })
                    ->where(function ($subQ) {
                        // Not Voucher student
                        $subQ->where('si.grantee', '!=', 3)
                            ->orWhere('gl.voucher', '!=', 1)
                            ->orWhereNull('gl.voucher');
                    });
            });

            // Apply active/deleted filters
            if (Schema::hasColumn('studinfo', 'deleted')) {
                $query->where('si.deleted', 0);
            }
            if (Schema::hasColumn('studinfo', 'studisactive')) {
                $query->where('si.studisactive', 1);
            }

            if ($selectAll) {
                // Apply filters when select_all is true
                // Note: school_year and semester are already filtered via enrollment check above
                if (!empty($filters['academic_program'])) {
                    $query->where('gl.acadprogid', $filters['academic_program']);
                }
                if (!empty($filters['academic_level'])) {
                    $query->where('si.levelid', $filters['academic_level']);
                }
                if (!empty($filters['section'])) {
                    $query->where('si.sectionid', $filters['section']);
                }
                if (!empty($filters['course'])) {
                    $query->where('si.courseid', $filters['course']);
                }
                if (!empty($filters['strand'])) {
                    $query->where('si.strandid', $filters['strand']);
                }
                if (!empty($filters['scholarship'])) {
                    $query->where('si.grantee', $filters['scholarship']);
                }
            } else {
                // Use specific student IDs
                if (empty($studentIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No students selected.'
                    ], 400);
                }
                $query->whereIn('si.id', $studentIds);
            }

            // Get valid student IDs
            $validStudentIds = $query->pluck('si.id')->toArray();

            if (empty($validStudentIds)) {
                return response()->json([
                    'success' => false,
                    'message' => "No valid students found. Only students who are NOT enrolled in the active period ({$periodText}) can be allowed no downpayment."
                ], 400);
            }

            DB::beginTransaction();

            // Update nodp to 1 for valid students
            $updated = DB::table('studinfo')
                ->whereIn('id', $validStudentIds)
                ->update(['nodp' => 1]);

            // Insert allow-no-dp records for the updated students
            $currentUserId = auth()->id();
            $now = Carbon::now('Asia/Manila');
            $semidForInsert = $semester;

            $existingAllowNdp = DB::table('student_allownodp')
                ->where('syid', $schoolYear)
                ->whereIn('studid', $validStudentIds)
                ->when($semester !== null, function ($q) use ($semester) {
                    $q->where('semid', $semester);
                }, function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('semid')->orWhere('semid', 0);
                    });
                })
                ->where('deleted', 0)
                ->pluck('studid')
                ->toArray();

            $allowNdpRows = [];
            foreach ($validStudentIds as $studid) {
                if (in_array($studid, $existingAllowNdp)) {
                    continue;
                }

                $allowNdpRows[] = [
                    'studid' => $studid,
                    'syid' => $schoolYear,
                    'semid' => $semidForInsert,
                    'deleted' => 0,
                    'createdby' => $currentUserId,
                    'createddatetime' => $now,
                    'updatedby' => $currentUserId,
                    'updateddatetime' => $now
                ];
            }

            if (!empty($allowNdpRows)) {
                DB::table('student_allownodp')->insert($allowNdpRows);
            }

            DB::commit();

            $message = "Successfully allowed {$updated} student(s) to proceed without downpayment for {$periodText}.";

            return response()->json([
                'success' => true,
                'message' => $message,
                'updated_count' => $updated,
                'period' => $periodText
            ]);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate students eligible for revoking no downpayment permission
     * Returns count of students who currently have nodp = 1
     */
    public function validateRevokeNoDownpayment(Request $request)
    {
        $studentIds = $request->input('student_ids', []);

        try {
            if (empty($studentIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No students selected.',
                    'eligible_count' => 0,
                    'total_count' => 0
                ], 400);
            }

            // Count students who have nodp = 1
            $eligibleCount = DB::table('studinfo')
                ->whereIn('id', $studentIds)
                ->where('nodp', 1)
                ->count();

            $totalCount = count($studentIds);

            return response()->json([
                'success' => true,
                'eligible_count' => $eligibleCount,
                'total_count' => $totalCount,
                'message' => $eligibleCount > 0
                    ? "{$eligibleCount}" . ($eligibleCount !== $totalCount ? " of {$totalCount}" : "") . " student(s) have \"Allow No Downpayment\" permission."
                    : "None of the selected students have \"Allow No Downpayment\" permission to revoke."
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'eligible_count' => 0,
                'total_count' => 0
            ], 500);
        }
    }

    /**
     * Revoke no downpayment permission for selected students
     * Sets nodp = 0 for students who currently have nodp = 1
     */
    public function revokeNoDownpayment(Request $request)
    {
        $studentIds = $request->input('student_ids', []);

        try {
            // Get ACTIVE school year (isactive = 1)
            $activeSchoolYear = DB::table('sy')
                ->where('isactive', 1)
                ->first();

            if (!$activeSchoolYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active school year found. Please set an active school year first.'
                ], 400);
            }

            // Get ACTIVE semester (isactive = 1) - optional
            $activeSemester = DB::table('semester')
                ->where('isactive', 1)
                ->first();

            $schoolYear = $activeSchoolYear->id;
            $semester = $activeSemester ? $activeSemester->id : null;

            if (empty($studentIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No students selected.'
                ], 400);
            }

            DB::beginTransaction();

            // Update nodp to 0 for students who have nodp = 1
            $updated = DB::table('studinfo')
                ->whereIn('id', $studentIds)
                ->where('nodp', 1)
                ->update(['nodp' => 0]);

            if ($updated === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No students were updated. They may not have "Allow No Downpayment" permission.'
                ], 400);
            }

            // Mark student_allownodp records as deleted for the active period
            $now = Carbon::now('Asia/Manila');
            $currentUserId = auth()->id();

            $deletedRows = DB::table('student_allownodp')
                ->whereIn('studid', $studentIds)
                ->where('syid', $schoolYear)
                ->when($semester !== null, function ($q) use ($semester) {
                    $q->where('semid', $semester);
                }, function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('semid')->orWhere('semid', 0);
                    });
                })
                ->where('deleted', 0)
                ->update([
                    'deleted' => 1,
                    'deletedby' => $currentUserId,
                    'deleteddatetime' => $now,
                    'updatedby' => $currentUserId,
                    'updateddatetime' => $now
                ]);

            DB::commit();

            $message = "Successfully revoked \"Allow No Downpayment\" permission for {$updated} student(s).";

            return response()->json([
                'success' => true,
                'message' => $message,
                'updated_count' => $updated,
                'allownodp_marked_deleted' => $deletedRows
            ]);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getFinancialHistory($studid, $syid = null, $semid = null)
    {
        // 1. Check for any enrollment records
        $enrollmentTables = ['enrolledstud', 'sh_enrolledstud', 'college_enrolledstud', 'tesda_enrolledstud'];
        $hasEnrollment = false;
        $allEnrollments = collect();

        foreach ($enrollmentTables as $table) {
            if (Schema::hasTable($table)) {
                $enrollments = DB::table($table)->where('studid', $studid)->where('deleted', 0)->get();
                if ($enrollments->isNotEmpty()) {
                    $hasEnrollment = true;
                    $allEnrollments = $allEnrollments->merge($enrollments);
                }
            }
        }

        if (!$hasEnrollment) {
            return [
                'student_info' => null,
                'school_fees' => [],
                'monthly_assessments' => [],
                'old_accounts' => [],
                'discounts_adjustments' => [],
            ];
        }

        // 2. Get Active SY/Sem first (needed for K-10 students and as fallback)
        $activeSY = DB::table('sy')->where('isactive', 1)->first();
        $activeSem = DB::table('semester')->where('isactive', 1)->first();

        // 3. If syid/semid are provided, use them; otherwise use active SY/Sem
        $targetSY = $syid ? DB::table('sy')->where('id', $syid)->first() : $activeSY;
        $targetSem = $semid ? DB::table('semester')->where('id', $semid)->first() : $activeSem;

        // 4. Get unique SY/Sem combinations
        // For K-10 students (enrolledstud table with no semid), use target semester or active semester
        $terms = $allEnrollments->map(function ($enrollment) use ($targetSem, $activeSem) {
            $semesterId = property_exists($enrollment, 'semid') ? $enrollment->semid : ($targetSem ? $targetSem->id : ($activeSem ? $activeSem->id : null));
            return [
                'syid' => $enrollment->syid,
                'semid' => $semesterId,
            ];
        })->unique(function ($item) {
            return $item['syid'] . '-' . $item['semid'];
        })->values();

        $studentInfo = DB::table('studinfo as si')
            ->leftJoin('gradelevel as gl', 'gl.id', '=', 'si.levelid')
            ->leftJoin('college_courses as cc', 'si.courseid', '=', 'cc.id')
            ->leftJoin('sh_strand as ss', 'si.strandid', '=', 'ss.id')
            ->leftJoin('grantee as g', 'si.grantee', '=', 'g.id')
            ->where('si.id', $studid)
            ->select(
                'si.*',
                'gl.acadprogid as program_id',
                'gl.levelname',
                'cc.courseabrv as program_name',
                'ss.strandcode as strand_name',
                'g.description as grantee_description',
                'si.picurl as profile_pic'
            )
            ->first();

        if (!$studentInfo) {
            return [
                'student_info' => null,
                'school_fees' => [],
                'monthly_assessments' => [],
                'old_accounts' => [],
                'discounts_adjustments' => [],
            ];
        }

        // Get student info with section and status (use target SY/Sem for enrollment lookup)
        $studentInfoData = $this->buildStudentInfo($studentInfo, $targetSY, $targetSem);

        // Ensure feesid is populated (studinfo or latest enrollment for the target term)
        if (empty($studentInfo->feesid)) {
            $studentInfo->feesid = $this->getLatestFeesId(
                $studid,
                (int) ($studentInfo->levelid ?? 0),
                $targetSY ? $targetSY->id : null,
                $targetSem ? $targetSem->id : null
            );
        }

        // Find target term (either specified syid/semid or active term)
        $targetTerm = null;
        $oldAccountTerms = [];

        foreach ($terms as $term) {
            $termSyid = $term['syid'];
            $termSemid = $term['semid'];

            // If syid/semid are provided, check if this term matches
            // Otherwise, check if this term is active
            if ($syid !== null || $semid !== null) {
                // Using specified syid/semid - check if this term matches
                $matchesTarget = true;

                // Check if SY matches
                if ($syid !== null && $termSyid != $syid) {
                    $matchesTarget = false;
                }

                // Check if semester matches (handle null cases for K-10 students)
                if ($semid !== null) {
                    // If semid is specified, it must match
                    if ($termSemid != $semid) {
                        $matchesTarget = false;
                    }
                } else {
                    // If semid is not specified in the request:
                    // - For K-10 students (levelid < 14), termSemid should be null (from active sem assignment)
                    // - For semester-based programs (SHS/College), termSemid might be set, but we'll allow it
                    //   since we're not filtering by semester
                    // This allows flexibility when only SY is specified
                }

                if ($matchesTarget) {
                    $targetTerm = ['syid' => $termSyid, 'semid' => $termSemid];
                } else {
                    $oldAccountTerms[] = ['syid' => $termSyid, 'semid' => $termSemid];
                }
            } else {
                // No specific syid/semid provided - use active term logic
                // Check if this term is active
                // For students without semid (Kinder-Grade 10), only check syid
                // For students with semid (SH/College), check both syid and semid
                if (is_null($termSemid)) {
                    $isActive = ($activeSY && $activeSY->id == $termSyid);
                } else {
                    $isActive = ($activeSY && $activeSY->id == $termSyid) && ($activeSem && $activeSem->id == $termSemid);
                }

                if ($isActive) {
                    $targetTerm = ['syid' => $termSyid, 'semid' => $termSemid];
                } else {
                    $oldAccountTerms[] = ['syid' => $termSyid, 'semid' => $termSemid];
                }
            }
        }

        // Get classid and itemid from labfeesetup (always 1 row)
        $labFeeSetup = DB::table('labfeesetup')
            ->where('deleted', 0)
            ->first();
        $labFeeClassId = $labFeeSetup ? $labFeeSetup->classid : null;
        $labFeeItemId = $labFeeSetup ? $labFeeSetup->itemid : null;

        // Get labfees data - only for college students (level 17-25) and only for enrolled subjects
        $labFees = collect([]);

        // Check if student is college level (17-25)
        if ($studentInfo && $studentInfo->levelid >= 17 && $studentInfo->levelid <= 25) {
            // Get student's enrolled subjects from college_loadsubject for all terms
            $studentSubjectIDs = DB::table('college_loadsubject')
                ->where('studid', $studid)
                ->where('deleted', 0)
                ->pluck('subjectID')
                ->unique()
                ->toArray();

            if (!empty($studentSubjectIDs)) {
                // Query labfees directly where subjid matches subjectID from college_loadsubject
                $labFees = DB::table('labfees as lf')
                    ->leftJoin('college_prospectus as cp', 'lf.subjid', '=', 'cp.id')
                    ->select(
                        'lf.id as laboratory_fee_id',
                        'lf.mode_of_payment as paymentsetup_id',
                        'lf.amount',
                        'lf.lab_amount',
                        'lf.subjid',
                        'lf.syid',
                        'lf.semid',
                        'cp.subjCode as subjcode',
                        'cp.subjDesc as subjdesc'
                    )
                    ->where('lf.deleted', 0)
                    ->whereIn('lf.subjid', $studentSubjectIDs)
                    ->orderBy('lf.id')
                    ->get();
            }
        }

        // Get laboratory fee items grouped by laboratory_fee_id
        $laboratoryFeeItems = DB::table('laboratory_fee_items as lfi')
            ->leftJoin('items as i', 'lfi.item_id', '=', 'i.id')
            ->select(
                'lfi.id',
                'lfi.laboratory_fee_id',
                'lfi.item_id',
                'lfi.amount',
                'i.description as item_description'
            )
            ->where('lfi.deleted', 0)
            ->orderBy('lfi.laboratory_fee_id')
            ->orderBy('lfi.id')
            ->get()
            ->groupBy('laboratory_fee_id');

        // Format labfees for response with the new structure
        $formattedLabFees = $labFees->map(function ($labFee) use ($labFeeClassId, $labFeeItemId, $laboratoryFeeItems) {
            $labFeeId = $labFee->laboratory_fee_id;
            $items = $laboratoryFeeItems->get($labFeeId, collect());

            // Build laboratory_fee_items object
            $laboratoryFeeItemsObj = [];

            // Add labfee entry
            if ($labFee->lab_amount > 0) {
                $laboratoryFeeItemsObj['labfee'] = [
                    'classid' => $labFeeClassId,
                    'itemid' => $labFeeItemId,
                    'lab_amount' => (float) $labFee->lab_amount
                ];
            }

            // Add individual items as item_1, item_2, etc.
            $itemIndex = 1;
            foreach ($items as $item) {
                $laboratoryFeeItemsObj['item_' . $itemIndex] = [
                    'id' => $item->id,
                    'item_id' => $item->item_id,
                    'item_description' => $item->item_description,
                    'amount' => (float) $item->amount
                ];
                $itemIndex++;
            }

            return [
                'paymentsetup_id' => $labFee->paymentsetup_id,
                'classid' => $labFeeClassId,
                'itemid' => $labFeeItemId,
                'amount' => (float) $labFee->amount,
                'laboratory_fee_items' => $laboratoryFeeItemsObj
            ];
        })->values()->toArray();

        // Initialize response structure
        $response = [
            'student_info' => $studentInfoData,
            'school_fees' => [],
            'monthly_assessments' => [],
            'old_accounts' => [],
            'discounts_adjustments' => [],
            'labfees' => $formattedLabFees,
        ];

        // Get target term data (either specified or active)
        if ($targetTerm) {
            // For basic ed (K-10), ignore semid so payments without sem tags still apply
            $isBasicLevel = ($studentInfo && (($studentInfo->levelid >= 1 && $studentInfo->levelid <= 13) || $studentInfo->levelid == 16));
            $effectiveSemid = $isBasicLevel ? null : $targetTerm['semid'];
            $termData = $this->getTermFinancialData($studid, $targetTerm['syid'], $effectiveSemid, $studentInfo);
            $response['school_fees'] = $termData['school_fees'];
            $response['monthly_assessments'] = $termData['monthly_assessments'];
            $response['discounts_adjustments'] = $termData['discounts_adjustments'];
            $response['total_overpayment'] = $termData['total_overpayment'] ?? 0;
        }

        // Get old account balances
        $response['old_accounts'] = $this->getOldAccountBalances($studid, $oldAccountTerms, $studentInfo);

        // Debug marker to verify code is running
        $response['code_version'] = 'v2025-01-19-labfee-FIXED';

        // Debug data for student 2 lab fee matching
        if ($studid == 2) {
            $response['debug_due_date_items'] = $termData['debug_due_date_items'] ?? [];
            $response['debug_lab_fee_schedule_summary'] = $termData['debug_lab_fee_schedule_summary'] ?? [];
            $response['debug_lab_matching'] = $GLOBALS['student2_lab_debug'] ?? [];
            $response['debug_month_items'] = $GLOBALS['student2_month_items'] ?? [];
            $response['debug_one_time_lab_processing'] = $GLOBALS['student2_one_time_lab'] ?? [];
            $response['debug_payments_skipped'] = $GLOBALS['student2_one_time_lab_skipped'] ?? [];
            $response['debug_payments_applied'] = $GLOBALS['student2_one_time_lab_applied'] ?? [];
            $response['debug_schedule_items_created'] = $GLOBALS['student2_schedule_items'] ?? [];
        }

        return $response;
    }


    /**
     * Get temporary financial data for non-enrolled students with selected fees
     */
    public function getTemporaryFinancialData($studid, $feesId, $activeSY, $activeSem)
    {
        // Get student info
        $studentInfo = DB::table('studinfo as si')
            ->leftJoin('gradelevel as gl', 'gl.id', '=', 'si.levelid')
            ->leftJoin('college_courses as cc', 'si.courseid', '=', 'cc.id')
            ->leftJoin('sh_strand as ss', 'si.strandid', '=', 'ss.id')
            ->leftJoin('grantee as g', 'si.grantee', '=', 'g.id')
            ->where('si.id', $studid)
            ->select(
                'si.*',
                'gl.acadprogid as program_id',
                'gl.levelname',
                'cc.courseabrv as program_name',
                'ss.strandcode as strand_name',
                'g.description as grantee_description',
                'si.picurl as profile_pic'
            )
            ->first();

        if (!$studentInfo) {
            return [
                'student_info' => null,
                'school_fees' => [],
                'monthly_assessments' => [],
                'old_accounts' => [],
                'discounts_adjustments' => [],
            ];
        }

        // Build student info with temporary status
        $studentInfoData = $this->buildStudentInfo($studentInfo, $activeSY, $activeSem);

        // Get tuition fees for the selected tuition header
        $tuitionFees = $this->getTemporaryTuitionFees($feesId, $studid, $activeSY, $activeSem);

        return [
            'student_info' => $studentInfoData,
            'school_fees' => $tuitionFees['school_fees'],
            'monthly_assessments' => $tuitionFees['monthly_assessments'],
            'old_accounts' => [], // No old accounts for non-enrolled students
            'discounts_adjustments' => [], // No discounts/adjustments for temporary fees
            'tuition_excluded_no_units' => $tuitionFees['tuition_excluded_no_units'] ?? false, // Flag indicating tuition was excluded
        ];
    }

    /**
     * Get temporary tuition fees for non-enrolled students
     */
    private function getTemporaryTuitionFees($feesId, $studid, $activeSY, $activeSem)
    {
        $schoolFees = [];
        $monthlyAssessments = [];

        // Get tuition header details
        $tuitionHeader = DB::table('tuitionheader')
            ->where('id', $feesId)
            ->where('deleted', 0)
            ->first();

        if (!$tuitionHeader) {
            return ['school_fees' => [], 'monthly_assessments' => []];
        }

        // Get student info to determine if college student
        $studentInfo = DB::table('studinfo')
            ->where('id', $studid)
            ->select('levelid', 'courseid')
            ->first();

        $isCollegeStudent = $studentInfo && $studentInfo->levelid >= 17 && $studentInfo->levelid <= 25;

        // Calculate units and subject counts for college students
        $totalUnits = 0;
        $subjectCount = 0;

        \Log::info('College student check', [
            'studid' => $studid,
            'isCollegeStudent' => $isCollegeStudent,
            'student_levelid' => $studentInfo->levelid,
            'activeSY_exists' => $activeSY ? true : false,
            'activeSem_exists' => $activeSem ? true : false
        ]);

        if ($isCollegeStudent && $activeSY && $activeSem) {
            // Get student's loaded subjects and calculate units
            $studentUnits = DB::table('college_loadsubject')
                ->join('college_classsched', 'college_loadsubject.schedid', '=', 'college_classsched.id')
                ->join('college_prospectus', 'college_classsched.subjectID', '=', 'college_prospectus.id')
                ->where('college_loadsubject.studid', $studid)
                ->where('college_loadsubject.syid', $activeSY->id)
                ->where('college_loadsubject.semid', $activeSem->id)
                ->where('college_loadsubject.deleted', 0)
                ->select('college_prospectus.*')
                ->distinct()
                ->get();

            // Get subject count (count distinct subjects)
            $subjectCountResult = DB::table('college_loadsubject')
                ->join('college_prospectus', 'college_loadsubject.subjectID', '=', 'college_prospectus.id')
                ->where('college_loadsubject.studid', $studid)
                ->where('college_loadsubject.syid', $activeSY->id)
                ->where('college_loadsubject.semid', $activeSem->id)
                ->where('college_loadsubject.deleted', 0)
                ->selectRaw('COUNT(DISTINCT college_loadsubject.subjectID) as subject_count')
                ->first();

            $subjectCount = $subjectCountResult ? $subjectCountResult->subject_count : 0;

            // Calculate total units if subjects are found
            if ($studentUnits->count() > 0) {
                // Get subject IDs
                $subjectIds = $studentUnits->pluck('subjectID')->unique()->toArray();

                // Get assessment units with priority: courseid + subjid > subjid only
                // First try to get course-specific assessment units
                $assessmentUnits = DB::table('tuition_assessmentunit')
                    ->select('subjid', 'assessmentunit', 'courseid', 'paymentschedid')
                    ->where('tuition_assessmentunit.deleted', 0)
                    ->where('tuition_assessmentunit.syid', $activeSY->id)
                    ->where('tuition_assessmentunit.semid', $activeSem->id)
                    ->whereIn('tuition_assessmentunit.subjid', $subjectIds)
                    ->where(function ($query) use ($studentInfo) {
                        // Priority 1: Match both courseid and subjid
                        $query->where('tuition_assessmentunit.courseid', $studentInfo->courseid)
                            // Priority 2: Match subjid only (courseid is null - applies to all courses)
                            ->orWhereNull('tuition_assessmentunit.courseid');
                    })
                    ->orderByRaw('CASE WHEN courseid IS NOT NULL THEN 1 ELSE 2 END') // Course-specific first
                    ->get()
                    ->unique('subjid') // Take first match per subject (course-specific wins)
                    ->keyBy('subjid');

                // Calculate total units
                foreach ($studentUnits as $unit) {
                    if ($assessmentUnits->has($unit->subjectID)) {
                        // Use the assessment unit from the table (can be course-specific or general)
                        $totalUnits += (float) $assessmentUnits->get($unit->subjectID)->assessmentunit;
                    } else {
                        // Use actual lecture + lab units
                        $totalUnits += ($unit->lecunits ?? 0) + ($unit->labunits ?? 0);
                    }
                }
            }

            \Log::info('College student temporary fee calculation', [
                'studid' => $studid,
                'totalUnits' => $totalUnits,
                'subjectCount' => $subjectCount,
                'studentUnits_count' => $studentUnits->count(),
                'activeSY' => $activeSY ? $activeSY->id : null,
                'activeSem' => $activeSem ? $activeSem->id : null,
                'courseid' => $studentInfo->courseid ?? null
            ]);
        }

        // Get tuition details
        $tuitionDetails = DB::table('tuitiondetail as td')
            ->join('itemclassification as ic', 'td.classificationid', '=', 'ic.id')
            ->where('td.headerid', $feesId)
            ->where('td.deleted', 0)
            ->select([
                'td.id as tuitiondetail_id',
                'td.classificationid as classid',
                'ic.description as particulars',
                'ic.classcode as priority',
                'td.amount',
                'td.istuition',
                'td.persubj',
            ])
            ->orderBy('ic.classcode')
            ->orderBy('ic.description')
            ->get();

        \Log::info('Tuition details retrieved', [
            'feesId' => $feesId,
            'details_count' => $tuitionDetails->count(),
            'details' => $tuitionDetails->map(function ($detail) {
                return [
                    'id' => $detail->tuitiondetail_id,
                    'particulars' => $detail->particulars,
                    'amount' => $detail->amount,
                    'istuition' => $detail->istuition,
                    'persubj' => $detail->persubj,
                ];
            })->toArray()
        ]);

        // Group fees by classification
        $feesByClassification = [];
        $detailLabels = []; // Store display labels for monthly assessments
        $tuitionExcluded = false; // Flag to track if tuition was excluded due to no units

        foreach ($tuitionDetails as $detail) {
            // Skip tuition fees if totalUnits is 0 for college students
            if ($isCollegeStudent && $detail->istuition == 1 && $totalUnits == 0) {
                $tuitionExcluded = true;
                \Log::info('Excluding tuition fee due to no units', [
                    'detail_id' => $detail->tuitiondetail_id,
                    'particulars' => $detail->particulars,
                    'totalUnits' => $totalUnits,
                    'studid' => $studid
                ]);
                continue; // Skip this tuition fee
            }

            $classId = $detail->classid;
            if (!isset($feesByClassification[$classId])) {
                $feesByClassification[$classId] = [
                    'particulars' => $detail->particulars,
                    'priority' => $detail->priority,
                    'amount' => 0,
                    'items' => []
                ];
            }

            // Calculate the actual amount based on college tuition rules
            $calculatedAmount = $detail->amount;

            \Log::info('Processing tuition detail', [
                'detail_id' => $detail->tuitiondetail_id,
                'particulars' => $detail->particulars,
                'base_amount' => $detail->amount,
                'istuition' => $detail->istuition,
                'persubj' => $detail->persubj,
                'isCollegeStudent' => $isCollegeStudent,
                'totalUnits' => $totalUnits ?? 0,
                'subjectCount' => $subjectCount ?? 0
            ]);

            $displayLabel = $detail->particulars;

            if ($isCollegeStudent) {
                if ($detail->istuition == 1) {
                    // Tuition fee per unit
                    $calculatedAmount = $detail->amount * $totalUnits;
                    $displayLabel = $detail->particulars . ' (' . $totalUnits . ' units × ₱' . number_format($detail->amount, 2) . ')';
                    \Log::info('Applied per-unit calculation for college student', [
                        'detail_id' => $detail->tuitiondetail_id,
                        'base_amount' => $detail->amount,
                        'total_units' => $totalUnits,
                        'calculated_amount' => $calculatedAmount,
                        'display_label' => $displayLabel,
                        'studid' => $studid
                    ]);
                } elseif ($detail->persubj == 1) {
                    // Fee per subject
                    $calculatedAmount = $detail->amount * $subjectCount;
                    $displayLabel = $detail->particulars . ' (' . $subjectCount . ' subjects × ₱' . number_format($detail->amount, 2) . ')';
                    \Log::info('Applied per-subject calculation for college student', [
                        'detail_id' => $detail->tuitiondetail_id,
                        'base_amount' => $detail->amount,
                        'subject_count' => $subjectCount,
                        'calculated_amount' => $calculatedAmount,
                        'display_label' => $displayLabel,
                        'studid' => $studid
                    ]);
                }
                // If neither istuition nor persubj, use amount as-is
            }

            // Store the display label for this detail
            $detailLabels[$detail->tuitiondetail_id] = $displayLabel;

            $feesByClassification[$classId]['amount'] += $calculatedAmount;
            $feesByClassification[$classId]['items'][] = [
                'id' => $detail->tuitiondetail_id,
                'classid' => $detail->classid,
                'particulars' => $displayLabel,
                'amount' => (float) $calculatedAmount,
                'priority' => $detail->priority,
                'istuition' => $detail->istuition,
            ];
        }

        // Fetch tuition items for nested items (similar to enrolled students)
        $tuitionItemsByClassId = [];
        $tuitionDetailsWithItems = DB::table('tuitiondetail as td')
            ->join('itemclassification as ic', 'td.classificationid', '=', 'ic.id')
            ->leftJoin('tuitionitems as ti', function ($join) {
                $join->on('td.id', '=', 'ti.tuitiondetailid')
                    ->where('ti.deleted', '=', 0);
            })
            ->leftJoin('items as i', 'ti.itemid', '=', 'i.id')
            ->where('td.headerid', $feesId)
            ->where('td.deleted', 0)
            ->select([
                'td.classificationid as classid',
                'ti.id as tuitionitem_id',
                'ti.itemid',
                'i.description as item_description',
                'ti.amount as item_amount',
                'ti.createddatetime'
            ])
            ->orderBy('ti.createddatetime', 'asc')
            ->get();

        // Group tuition items by classid, maintaining priority order
        foreach ($tuitionDetailsWithItems as $row) {
            if ($row->tuitionitem_id) { // Only include rows that have tuition items
                if (!isset($tuitionItemsByClassId[$row->classid])) {
                    $tuitionItemsByClassId[$row->classid] = [];
                }
                $tuitionItemsByClassId[$row->classid][] = [
                    'itemid' => $row->itemid,
                    'particulars' => $row->item_description ?? 'Unknown Item',
                    'amount' => round((float) $row->item_amount, 2),
                    'classid' => $row->classid,
                    'createddatetime' => $row->createddatetime ?? null,
                ];
            }
        }

        // FALLBACK: For classids without tuitionitems, build nested items from actual payments
        // This handles cases where tuition setup doesn't have items configured but payments were made
        $classidsWithoutTuitionItems = [];
        foreach ($tuitionDetailsWithItems as $row) {
            if (!$row->tuitionitem_id && !isset($tuitionItemsByClassId[$row->classid])) {
                $classidsWithoutTuitionItems[$row->classid] = true;
            }
        }

        if (!empty($classidsWithoutTuitionItems)) {
            // Fetch actual paid items from chrngtransitems for these classids
            // Filter by syid/semid to avoid cross-term contamination
            foreach (array_keys($classidsWithoutTuitionItems) as $classid) {
                $paidItemsQuery = DB::table('chrngtrans as ct')
                    ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
                    ->join('chrngtransitems as cti', 'ct.id', '=', 'cti.chrngtransid')
                    ->join('items as i', 'cti.itemid', '=', 'i.id')
                    ->where('ct.studid', $studid)
                    ->where('ct.syid', $syid)
                    ->where('cct.syid', $syid)
                    ->where('cct.classid', $classid)
                    ->where('ct.cancelled', 0)
                    ->where('cct.deleted', 0)
                    ->where('cti.deleted', 0);

                // For college students, filter by semester
                if ($studentInfo->levelid >= 17 && $studentInfo->levelid <= 25) {
                    $paidItemsQuery->where(function ($q) use ($semid) {
                        $q->where('cct.semid', $semid)->orWhereNull('cct.semid');
                    });
                }

                $paidItems = $paidItemsQuery
                    ->select([
                        'cti.itemid',
                        'i.description as item_description',
                        DB::raw('MAX(cti.amount) as max_amount'),
                        DB::raw('MIN(cti.createddatetime) as first_payment_date')
                    ])
                    ->groupBy('cti.itemid', 'i.description')
                    ->orderBy('first_payment_date', 'asc')
                    ->get();

                if ($paidItems->count() > 0) {
                    $tuitionItemsByClassId[$classid] = [];
                    foreach ($paidItems as $item) {
                        $tuitionItemsByClassId[$classid][] = [
                            'itemid' => $item->itemid,
                            'particulars' => $item->item_description ?? 'Unknown Item',
                            'amount' => round((float) $item->max_amount, 2),
                            'classid' => $classid,
                            'createddatetime' => $item->first_payment_date,
                        ];
                    }

                    \Log::info('[FALLBACK-TUITION-ITEMS] Created nested items from payments', [
                        'classid' => $classid,
                        'items_count' => count($tuitionItemsByClassId[$classid]),
                        'syid' => $syid,
                        'semid' => $semid
                    ]);
                }
            }
        }

        // Create breakdown items for each fee based on payment schedule
        // Get payment schedule if available
        $paymentSchedule = null;
        if ($tuitionHeader->paymentplan) {
            $paymentSchedule = DB::table('paymentsetupdetail')
                ->where('paymentid', $tuitionHeader->paymentplan)
                ->where('deleted', 0)
                ->orderBy('paymentno')
                ->get();
        }

        // Track remaining amounts for nested items across all breakdown items (for sequential filling)
        $nestedItemsRemaining = []; // Key: classid => [itemid => remaining_amount]

        // Initialize remaining amounts for all classids
        foreach ($feesByClassification as $classId => $feeData) {
            if (!isset($nestedItemsRemaining[$classId])) {
                $nestedItemsRemaining[$classId] = [];
                if (isset($tuitionItemsByClassId[$classId]) && !empty($tuitionItemsByClassId[$classId])) {
                    foreach ($tuitionItemsByClassId[$classId] as $tuitionItem) {
                        $nestedItemsRemaining[$classId][$tuitionItem['itemid']] = $tuitionItem['amount'];
                    }
                }
            }
        }

        // Create school fees structure with breakdown items
        foreach ($feesByClassification as $classId => $feeData) {
            $breakdownItems = [];

            // If payment schedule exists, create breakdown items for each payment period
            if ($paymentSchedule && $paymentSchedule->count() > 0) {
                $totalAmount = $feeData['amount'];
                $scheduleCount = $paymentSchedule->count();
                $amountPerPeriod = $totalAmount / $scheduleCount;

                foreach ($paymentSchedule as $schedule) {
                    $dueDate = $schedule->duedate ?? 'No Date';
                    $assessmentLabel = $schedule->description ?? $this->getPaymentDescriptionFallback($schedule->paymentno);

                    // Create breakdown item label
                    $breakdownLabel = $feeData['particulars'] . ' - ' . $assessmentLabel;

                    // Get nested items for this breakdown using sequential priority-based filling
                    $nestedItems = [];
                    if (isset($tuitionItemsByClassId[$classId]) && !empty($tuitionItemsByClassId[$classId])) {
                        // Sort tuition items by payment_priority_sequence (fallback to createddatetime)
                        $sortedTuitionItems = $tuitionItemsByClassId[$classId];
                        usort($sortedTuitionItems, function ($a, $b) {
                            $priorityA = $a['payment_priority_sequence'] ?? 9999;
                            $priorityB = $b['payment_priority_sequence'] ?? 9999;
                            if ($priorityA != $priorityB) {
                                return $priorityA <=> $priorityB;
                            }
                            // Fallback to createddatetime if priorities are equal
                            $dateA = $a['createddatetime'] ?? null;
                            $dateB = $b['createddatetime'] ?? null;
                            if ($dateA && $dateB) {
                                return strtotime($dateA) <=> strtotime($dateB);
                            }
                            if ($dateA)
                                return -1;
                            if ($dateB)
                                return 1;
                            return 0;
                        });

                        // Use sequential priority-based filling instead of proportional
                        $nestedItems = $this->distributeNestedItemsSequentially(
                            $sortedTuitionItems,
                            $amountPerPeriod,
                            $nestedItemsRemaining[$classId]
                        );
                    }

                    // Safety check: ensure items array exists and has at least one element
                    $firstItem = !empty($feeData['items']) && isset($feeData['items'][0]) ? $feeData['items'][0] : [];

                    $breakdownItems[] = [
                        'id' => $firstItem['id'] ?? null, // Use first item's id
                        'classid' => $classId,
                        'particulars' => $breakdownLabel,
                        'amount' => round($amountPerPeriod, 2),
                        'payment' => 0, // No payments for temporary fees
                        'balance' => round($amountPerPeriod, 2),
                        'priority' => $firstItem['priority'] ?? null,
                        'istuition' => $firstItem['istuition'] ?? 0,
                        'paymentsetupdetail_id' => $schedule->id,
                        'items' => $nestedItems // Nested items from tuitionitems table
                    ];
                }
            } else {
                // No payment schedule, use single item (original behavior)
                // Safety check: ensure items array exists
                if (empty($feeData['items']) || !is_array($feeData['items'])) {
                    continue; // Skip if no items
                }

                foreach ($feeData['items'] as $item) {
                    // Get nested items for this item using sequential priority-based filling
                    $nestedItems = [];
                    if (isset($tuitionItemsByClassId[$classId]) && !empty($tuitionItemsByClassId[$classId])) {
                        // Sort tuition items by priority (createddatetime) to maintain order
                        $sortedTuitionItems = $tuitionItemsByClassId[$classId];
                        usort($sortedTuitionItems, function ($a, $b) {
                            $priorityA = $a['payment_priority_sequence'] ?? 9999;
                            $priorityB = $b['payment_priority_sequence'] ?? 9999;
                            if ($priorityA != $priorityB) {
                                return $priorityA <=> $priorityB;
                            }
                            // Fallback to createddatetime if priorities are equal
                            $dateA = $a['createddatetime'] ?? null;
                            $dateB = $b['createddatetime'] ?? null;
                            if ($dateA && $dateB) {
                                return strtotime($dateA) <=> strtotime($dateB);
                            }
                            if ($dateA)
                                return -1;
                            if ($dateB)
                                return 1;
                            return 0;
                        });

                        // Use sequential priority-based filling instead of proportional
                        $nestedItems = $this->distributeNestedItemsSequentially(
                            $sortedTuitionItems,
                            $item['amount'],
                            $nestedItemsRemaining[$classId]
                        );
                    }

                    $breakdownItems[] = [
                        'id' => $item['id'],
                        'classid' => $item['classid'],
                        'particulars' => $item['particulars'],
                        'amount' => $item['amount'],
                        'payment' => 0, // No payments for temporary fees
                        'balance' => $item['amount'],
                        'priority' => $item['priority'],
                        'istuition' => $item['istuition'],
                        'items' => $nestedItems // Nested items from tuitionitems table
                    ];
                }
            }

            $schoolFees[] = [
                'particulars' => $feeData['particulars'],
                'total_balance' => $feeData['amount'],
                'total_amount' => $feeData['amount'],
                'classid' => $classId,
                'items' => $breakdownItems
            ];
        }

        // For monthly assessments, we need to create a payment schedule based on the payment plan
        // Get actual payment schedule from paymentsetupdetail table
        if ($tuitionHeader->paymentplan) {
            // Get payment schedule details
            $paymentSchedule = DB::table('paymentsetupdetail')
                ->where('paymentid', $tuitionHeader->paymentplan)
                ->where('deleted', 0)
                ->orderBy('paymentno')
                ->get();

            if ($paymentSchedule->isEmpty()) {
                \Log::warning('No payment schedule found for paymentplan: ' . $tuitionHeader->paymentplan);
            } else {
                // Calculate total amount using the calculated amounts (including per-unit/per-subject multipliers)
                // Exclude tuition fees if they were excluded from school fees
                $totalAmount = 0;
                $detailBreakdown = [];

                foreach ($tuitionDetails as $detail) {
                    // Skip tuition fees if they were excluded due to no units
                    if ($isCollegeStudent && $detail->istuition == 1 && $totalUnits == 0) {
                        continue; // Skip this tuition fee
                    }

                    $calculatedAmount = $detail->amount;

                    if ($isCollegeStudent) {
                        if ($detail->istuition == 1) {
                            $calculatedAmount = $detail->amount * $totalUnits;
                        } elseif ($detail->persubj == 1) {
                            $calculatedAmount = $detail->amount * $subjectCount;
                        }
                    }

                    $totalAmount += $calculatedAmount;
                    $detailBreakdown[] = [
                        'detail' => $detail,
                        'calculated_amount' => $calculatedAmount
                    ];
                }

                // Track remaining amounts for nested items in monthly assessments (for sequential filling)
                $monthlyAssessmentsNestedItemsRemaining = []; // Key: classid => [itemid => remaining_amount]

                // Initialize remaining amounts for all classids in monthly assessments
                foreach ($feesByClassification as $classid => $feeData) {
                    if (!isset($monthlyAssessmentsNestedItemsRemaining[$classid])) {
                        $monthlyAssessmentsNestedItemsRemaining[$classid] = [];
                        if (isset($tuitionItemsByClassId[$classid]) && !empty($tuitionItemsByClassId[$classid])) {
                            foreach ($tuitionItemsByClassId[$classid] as $tuitionItem) {
                                $monthlyAssessmentsNestedItemsRemaining[$classid][$tuitionItem['itemid']] = $tuitionItem['amount'];
                            }
                        }
                    }
                }

                // Only create monthly assessments if totalAmount > 0 (avoid division by zero)
                if ($totalAmount > 0 && $paymentSchedule->count() > 0) {
                    $scheduleCount = $paymentSchedule->count();
                    $monthlyAmount = $totalAmount / $scheduleCount;

                    $currentDate = date('Y-m-d');

                    foreach ($paymentSchedule as $schedule) {
                        $dueDate = $schedule->duedate ?? 'No Date';
                        $assessmentLabel = $schedule->description ?? $this->getPaymentDescriptionFallback($schedule->paymentno);

                        // Determine status based on due date
                        $status = 'pending';
                        if ($dueDate && $dueDate !== 'No Date' && $dueDate < $currentDate) {
                            $status = 'overdue';
                        }

                        // Build breakdown with proper structure and nested items
                        $breakdown = [];
                        foreach ($detailBreakdown as $item) {
                            $displayLabel = isset($detailLabels[$item['detail']->tuitiondetail_id])
                                ? $detailLabels[$item['detail']->tuitiondetail_id]
                                : $item['detail']->particulars;

                            $itemAmount = ($totalAmount > 0) ? (($item['calculated_amount'] / $totalAmount) * $monthlyAmount) : 0;
                            $classid = $item['detail']->classid;

                            // Get nested items for this breakdown item using sequential priority-based filling
                            $nestedItems = [];
                            if (isset($tuitionItemsByClassId[$classid]) && !empty($tuitionItemsByClassId[$classid])) {
                                // Sort tuition items by priority (createddatetime) to maintain order
                                $sortedTuitionItems = $tuitionItemsByClassId[$classid];
                                usort($sortedTuitionItems, function ($a, $b) {
                                    $dateA = $a['createddatetime'] ?? null;
                                    $dateB = $b['createddatetime'] ?? null;
                                    if ($dateA && $dateB) {
                                        return strtotime($dateA) <=> strtotime($dateB);
                                    }
                                    if ($dateA)
                                        return -1;
                                    if ($dateB)
                                        return 1;
                                    return 0;
                                });

                                // Ensure the nested array structure exists
                                if (!isset($monthlyAssessmentsNestedItemsRemaining[$classid])) {
                                    $monthlyAssessmentsNestedItemsRemaining[$classid] = [];
                                }

                                // Use sequential priority-based filling instead of proportional
                                $nestedItems = $this->distributeNestedItemsSequentially(
                                    $sortedTuitionItems,
                                    $itemAmount,
                                    $monthlyAssessmentsNestedItemsRemaining[$classid]
                                );
                            }

                            $breakdown[] = [
                                'classid' => $classid,
                                'particulars' => $displayLabel,
                                'amount' => round($itemAmount, 2),
                                'payment' => 0, // No payments for temporary fees
                                'balance' => round($itemAmount, 2),
                                'items' => $nestedItems // Nested items from tuitionitems table
                            ];
                        }

                        $monthlyAssessments[] = [
                            'paymentsetupdetail_id' => $schedule->id,
                            'due_date' => $dueDate,
                            'assessment_label' => $assessmentLabel,
                            'total_due' => round($monthlyAmount, 2),
                            'balance' => round($monthlyAmount, 2),
                            'status' => $status,
                            'breakdown' => $breakdown
                        ];
                    }
                }
            }
        }

        return [
            'school_fees' => $schoolFees,
            'monthly_assessments' => $monthlyAssessments,
            'tuition_excluded_no_units' => $tuitionExcluded // Flag indicating tuition was excluded
        ];
    }

    /**
     * Get payment description fallback based on payment number
     */
    private function getPaymentDescriptionFallback($paymentNo)
    {
        $paymentDescriptions = [
            1 => 'DOWNPAYMENT',
            2 => '1ST MONTH',
            3 => '2ND MONTH',
            4 => '3RD MONTH',
            5 => '4TH MONTH',
            6 => '5TH MONTH',
            7 => '6TH MONTH',
            8 => '7TH MONTH',
            9 => '8TH MONTH',
            10 => '9TH MONTH',
            11 => '10TH MONTH',
            12 => 'FINAL PAYMENT'
        ];

        return $paymentDescriptions[$paymentNo] ?? "PAYMENT {$paymentNo}";
    }

    /**
     * Distribute breakdown amount across nested items using sequential priority-based filling
     * Instead of proportional distribution, fills items sequentially until exhausted
     * 
     * @param array $sortedTuitionItems Array of tuition items sorted by priority
     * @param float $breakdownAmount Amount to distribute across nested items
     * @param array $remainingAmounts Reference to array tracking remaining amounts per item (keyed by itemid)
     * @return array Array of nested items with distributed amounts
     */
    private function distributeNestedItemsSequentially($sortedTuitionItems, $breakdownAmount, &$remainingAmounts = [])
    {
        $nestedItems = [];
        $remainingQuota = $breakdownAmount;

        foreach ($sortedTuitionItems as $tuitionItem) {
            if ($remainingQuota <= 0) {
                break; // Breakdown amount is fully filled
            }

            $itemid = $tuitionItem['itemid'];
            $originalAmount = $tuitionItem['amount'];

            // Initialize remaining amount if not set
            if (!isset($remainingAmounts[$itemid])) {
                $remainingAmounts[$itemid] = $originalAmount;
            }

            // Get remaining amount for this item
            $itemRemaining = $remainingAmounts[$itemid];

            if ($itemRemaining <= 0) {
                // This item is already exhausted, skip to next
                continue;
            }

            // Fill as much as possible from this item
            $amountToFill = min($itemRemaining, $remainingQuota);

            // Add nested item
            $nestedItems[] = [
                'itemid' => $itemid,
                'particulars' => $tuitionItem['particulars'],
                'amount' => round($amountToFill, 2),
                'payment' => 0, // No payments for temporary fees
                'balance' => round($amountToFill, 2),
                'classid' => $tuitionItem['classid'],
            ];

            // Update remaining amounts
            $remainingAmounts[$itemid] -= $amountToFill;
            $remainingQuota -= $amountToFill;
        }

        return $nestedItems;
    }

    private function buildStudentInfo($studentInfo, $activeSY, $activeSem)
    {
        $levelid = $studentInfo->levelid;
        $sectionName = null;
        $studStatus = null;

        if ($activeSY && $activeSem) {
            if ($levelid >= 17 && $levelid <= 25) {
                // College student
                $enrollment = DB::table('college_enrolledstud as ce')
                    ->leftJoin('college_sections as cs', 'ce.sectionID', '=', 'cs.id')
                    ->where('ce.studid', $studentInfo->id)
                    ->where('ce.syid', $activeSY->id)
                    ->where('ce.semid', $activeSem->id)
                    ->where('ce.deleted', 0)
                    ->select('cs.sectionDesc as section_name', 'ce.studstatus')
                    ->first();

                if ($enrollment) {
                    $sectionName = $enrollment->section_name;
                    $studStatus = $enrollment->studstatus;
                }
            } elseif ($levelid == 14 || $levelid == 15) {
                // SHS student
                $enrollment = DB::table('sh_enrolledstud as se')
                    ->leftJoin('sections as s', 'se.sectionid', '=', 's.id')
                    ->where('se.studid', $studentInfo->id)
                    ->where('se.syid', $activeSY->id)
                    ->where('se.deleted', 0)
                    ->select('s.sectionname as section_name', 'se.studstatus')
                    ->first();

                if ($enrollment) {
                    $sectionName = $enrollment->section_name;
                    $studStatus = $enrollment->studstatus;
                }
            } else {
                // Elementary/JHS student
                $enrollment = DB::table('enrolledstud as e')
                    ->leftJoin('sections as s', 'e.sectionid', '=', 's.id')
                    ->where('e.studid', $studentInfo->id)
                    ->where('e.syid', $activeSY->id)
                    ->where('e.deleted', 0)
                    ->select('s.sectionname as section_name', 'e.studstatus')
                    ->first();

                if ($enrollment) {
                    $sectionName = $enrollment->section_name;
                    $studStatus = $enrollment->studstatus;
                }
            }
        }

        $fullname = trim($studentInfo->firstname . ' ' . ($studentInfo->middlename ? $studentInfo->middlename . ' ' : '') . $studentInfo->lastname);

        return [
            'id' => $studentInfo->id,
            'sid' => $studentInfo->sid,
            'fullname' => $fullname,
            'firstname' => $studentInfo->firstname,
            'middlename' => $studentInfo->middlename,
            'lastname' => $studentInfo->lastname,
            'levelid' => $studentInfo->levelid,
            'levelname' => $studentInfo->levelname,
            'program_name' => $studentInfo->program_name ?? $studentInfo->strand_name ?? null,
            'section_name' => $sectionName,
            'grantee_description' => $studentInfo->grantee_description,
            'student_status' => $studStatus,
            'picurl' => $studentInfo->profile_pic ?? $studentInfo->picurl ?? null,
        ];
    }


    private function getTermFinancialData($studid, $syid, $semid, $studentInfo)
    {
        // Ensure we have a feesid on the passed student info for downstream calls
        if (empty($studentInfo->feesid)) {
            $studentInfo->feesid = $this->getLatestFeesId(
                $studid,
                (int) ($studentInfo->levelid ?? 0),
                $syid,
                $semid
            );
        }

        // Get classid, itemid, and item description from labfeesetup for laboratory fees (always 1 row)
        $labFeeSetup = DB::table('labfeesetup as lfs')
            ->leftJoin('items as i', 'lfs.itemid', '=', 'i.id')
            ->select('lfs.classid', 'lfs.itemid', 'i.description as item_description')
            ->where('lfs.deleted', 0)
            ->first();
        $labFeeClassId = $labFeeSetup ? $labFeeSetup->classid : null;
        $labFeeItemId = $labFeeSetup ? $labFeeSetup->itemid : null;
        $labFeeItemDescription = $labFeeSetup ? $labFeeSetup->item_description : 'LAB FEE';

        // Initialize adjustment sums array for accumulating adjustments from due dates
        $adjustmentSums = [];
        // Track remaining amounts for adjustment items across breakdowns (for sequential priority-based filling)
        $adjustmentNestedItemsRemaining = []; // Key: adjustmentdetail_id => [itemid => remaining_amount]

        $schedules = self::getStudentPaymentSchedules([$studid], $syid, $semid);

        if (empty($schedules[$studid])) {
            return [
                'school_fees' => [],
                'monthly_assessments' => [],
                'discounts_adjustments' => [],
                'total_overpayment' => 0,
                'debug_due_date_items' => [],
                'debug_lab_fee_schedule_summary' => [],
            ];
        }

        $schedule = $schedules[$studid];
        $dueDates = $schedule['due_dates'] ?? [];

        // Debug due dates for student 2
        if ($studid == 2) {
            $labFeeDueDates = array_filter($dueDates, function ($item) {
                return isset($item['laboratory_fee_id']);
            });
            \Log::debug('[DUE-DATES-DEBUG] Laboratory fee items in due dates', [
                'total_due_dates_count' => count($dueDates),
                'lab_fee_due_dates_count' => count($labFeeDueDates),
                'lab_fee_items' => array_map(function ($item) {
                    return [
                        'particulars' => substr($item['particulars'] ?? '', 0, 60),
                        'laboratory_fee_id' => $item['laboratory_fee_id'] ?? 'NOT SET',
                        'paymentsetupdetail_id' => $item['paymentsetupdetail_id'] ?? 'NOT SET',
                        'amountpay' => $item['amountpay'] ?? 0
                    ];
                }, $labFeeDueDates)
            ]);
        }

        // Track scheduled payments for laboratory fees so we can force subject-specific totals later
        $labFeeSchedulePaid = []; // [laboratory_fee_id][paymentsetupdetail_id] => amountpay
        // Track full schedule summary per lab fee (amount, paid, balance) keyed by paymentsetupdetail_id
        $labFeeScheduleSummary = []; // [laboratory_fee_id][paymentsetupdetail_id] => ['amount' => ..., 'paid' => ..., 'balance' => ...]

        // Preload payments by paymentsetupdetail_id + classid + itemid to avoid proportional splits later
        // Key format: "{paymentsetupdetail_id|none}|{classid}|{itemid}"
        $paymentsByScheduleItem = DB::table('chrngtrans as ct')
            ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
            ->join('chrngtransitems as cti', 'ct.transno', '=', 'cti.chrngtransid')
            ->select(
                'cct.paymentsetupdetail_id',
                'cti.classid',
                'cti.itemid',
                DB::raw('SUM(cti.amount) as total_paid')
            )
            ->where('ct.studid', $studid)
            ->where('ct.syid', $syid)
            ->where('cct.syid', $syid)
            ->when(
                ($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25),
                function ($query) use ($semid) {
                    return $query->where('ct.semid', $semid);
                }
            )
            ->when(
                ($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25),
                function ($query) use ($semid) {
                    return $query->where(function ($subQ) use ($semid) {
                        $subQ->where('cct.semid', $semid)
                            ->orWhereNull('cct.semid');
                    });
                }
            )
            ->where('ct.cancelled', 0)
            ->where('cct.deleted', 0)
            ->where('cti.deleted', 0)
            ->groupBy('cct.paymentsetupdetail_id', 'cti.classid', 'cti.itemid')
            ->get()
            ->mapWithKeys(function ($row) {
                $psd = $row->paymentsetupdetail_id ?? 'none';
                return [
                    $psd . '|' . (int) $row->classid . '|' . (int) $row->itemid => (float) $row->total_paid
                ];
            })
            ->toArray();

        // Fetch book entries from bookentries table
        $bookEntriesQuery = DB::table('bookentries as be')
            ->leftJoin('items as i', 'be.bookid', '=', 'i.id')
            ->where('be.studid', $studid)
            ->where('be.syid', $syid)
            ->where('be.deleted', 0);

        // Only filter by semid for students that have semester-based enrollment
        if (!is_null($semid) && ($studentInfo->levelid == 14 || $studentInfo->levelid == 15 || ($studentInfo->levelid >= 17 && $studentInfo->levelid <= 26))) {
            $bookEntriesQuery->where('be.semid', $semid);
        }

        $bookEntriesFromDB = $bookEntriesQuery
            ->select([
                'be.id',
                'be.classid',
                'be.amount',
                'be.bestatus',
                'be.mopid',
                'i.description as particulars',
                'i.description as title'
            ])
            ->get();

        // Build school fees (total per particular)
        // Books (classid 11) are kept separate and not grouped
        // Adjustments are excluded from fee amounts
        $feesByParticular = [];
        $bookEntries = [];
        $adjustments = [];
        $totalOverpayment = 0;

        // Debug: Collect all due date items for student 2
        $debugDueDateItems = [];
        if ($studid == 2) {
            foreach ($dueDates as $item) {
                $classid = isset($item['classid']) ? (int) $item['classid'] : null;
                if ($classid == 5) { // Laboratory fee classid
                    $debugDueDateItems[] = [
                        'particulars' => substr($item['particulars'] ?? '', 0, 60),
                        'laboratory_fee_id' => $item['laboratory_fee_id'] ?? 'NOT SET',
                        'amount' => $item['amount'] ?? 0,
                        'amountpay' => $item['amountpay'] ?? 0,
                        'balance' => $item['balance'] ?? 0,
                    ];
                }
            }
        }

        foreach ($dueDates as $item) {
            // Cast classid to integer to ensure consistency (prevent string "9" vs int 9 duplicates)
            $classid = isset($item['classid']) ? (int) $item['classid'] : null;
            $particulars = $item['particulars'] ?? 'Unknown';
            $adjustment = $item['adjustment'] ?? 0;
            $creditAdjustment = $item['credit_adjustment'] ?? 0;
            $discount = $item['discount'] ?? 0;
            $paymentNo = $item['paymentno'] ?? null;
            $dueDate = $item['duedate'] ?? null;
            $isAdjustment = isset($item['is_adjustment']) && $item['is_adjustment'];

            // Debug log for student 1, classid 1, paymentsetupdetail_id 4 (AUGUST)
            if ($studid == 1 && $classid == 1 && isset($item['paymentsetupdetail_id']) && $item['paymentsetupdetail_id'] == 4) {
                \Log::debug('[DUEDATES-DEBUG-STUDENT-1] Processing TUITION - AUGUST', [
                    'particulars' => $particulars,
                    'paymentsetupdetail_id' => $item['paymentsetupdetail_id'],
                    'amountpay' => $item['amountpay'] ?? 'NOT SET',
                    'balance' => $item['balance'] ?? 'NOT SET',
                    'payment_details' => $item['payment_details'] ?? 'NOT SET',
                    'full_item' => json_encode($item)
                ]);
            }

            // Original amount from payment schedule
            $originalAmount = $item['amount'] ?? 0;

            // Amount without adjustments (adjustments will be shown separately)
            $baseAmount = $originalAmount - $adjustment;

            // Skip book entries from bookentries table (they have 'BOOK_' prefix or is_book_entry flag)
            // But DO NOT skip books from tuitiondetail (classid 11)
            if (strpos($classid, 'BOOK_') === 0 || (isset($item['is_book_entry']) && $item['is_book_entry'])) {
                continue;
            }

            // Do NOT skip priority-filled items - they should appear in both school_fees (as totals)
            // and monthly_assessments (as payment breakdowns)

            // If this is a standalone adjustment entry, sum it up for the discounts_adjustments container
            $isStandaloneAdjustment = isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment'];

            if ($isAdjustment || $isStandaloneAdjustment) {
                // Sum adjustments by classid and BASE particulars (without schedule suffix like " - June")
                // Extract base particulars by removing everything after " - " for installment-based adjustments
                $baseParticulars = $particulars;
                if (strpos($particulars, ' - ') !== false) {
                    $baseParticulars = substr($particulars, 0, strpos($particulars, ' - '));
                }
                $adjustmentKey = $classid . '::' . $baseParticulars;

                // Use the static array to accumulate adjustment amounts and payment info

                if (!isset($adjustmentSums[$adjustmentKey])) {
                    $adjustmentSums[$adjustmentKey] = [
                        'type' => 'debit',
                        'classid' => $classid,
                        'particulars' => $baseParticulars, // Use base particulars without suffix
                        'amount' => 0,
                        'paid' => 0,
                        'balance' => 0,
                        'items' => [] // Store individual installment breakdowns
                    ];
                }

                // Add this portion to the total amount and payment info
                $adjustmentSums[$adjustmentKey]['amount'] += $originalAmount;
                $adjustmentSums[$adjustmentKey]['paid'] += ($item['amountpay'] ?? 0);
                $adjustmentSums[$adjustmentKey]['balance'] += ($item['balance'] ?? max(0, $originalAmount - ($item['amountpay'] ?? 0)));

                // Fetch the nested items (individual fee items) for this adjustment installment
                // Use sequential priority-based filling instead of proportional distribution
                $nestedItems = [];
                if (isset($item['adjustmentdetail_id']) && $item['adjustmentdetail_id']) {
                    $adjustmentDetailId = $item['adjustmentdetail_id'];

                    $adjustmentItems = DB::table('adjustmentitems as ai')
                        ->join('items as i', 'ai.itemid', '=', 'i.id')
                        ->select('i.description as particulars', 'ai.amount', 'ai.itemid', 'ai.id')
                        ->where('ai.detailid', $adjustmentDetailId)
                        ->where('ai.deleted', 0)
                        ->where('ai.amount', '>', 0)
                        ->orderBy('ai.id', 'asc') // Use ID for priority ordering
                        ->get();

                    // Convert to array format for easier manipulation
                    $sortedAdjustmentItems = [];
                    foreach ($adjustmentItems as $adjItem) {
                        $sortedAdjustmentItems[] = [
                            'itemid' => $adjItem->itemid,
                            'particulars' => $adjItem->particulars,
                            'amount' => (float) $adjItem->amount,
                        ];
                    }

                    // Initialize remaining amounts for this adjustment detail if not set
                    if (!isset($adjustmentNestedItemsRemaining[$adjustmentDetailId])) {
                        $adjustmentNestedItemsRemaining[$adjustmentDetailId] = [];
                        foreach ($sortedAdjustmentItems as $adjItem) {
                            $adjustmentNestedItemsRemaining[$adjustmentDetailId][$adjItem['itemid']] = $adjItem['amount'];
                        }
                    }

                    $breakdownAmount = $originalAmount;
                    $breakdownPaid = $item['amountpay'] ?? 0;
                    $breakdownBalance = $item['balance'] ?? max(0, $breakdownAmount - $breakdownPaid);

                    // Special case: If there's only 1 adjustment item, use breakdown amount directly
                    if (count($sortedAdjustmentItems) === 1) {
                        $singleItem = $sortedAdjustmentItems[0];
                        $breakdownPaymentRatio = $breakdownAmount > 0 ? ($breakdownPaid / $breakdownAmount) : 0;
                        $breakdownBalanceRatio = $breakdownAmount > 0 ? ($breakdownBalance / $breakdownAmount) : 0;

                        $nestedItems = [
                            [
                                'itemid' => $singleItem['itemid'],
                                'particulars' => $singleItem['particulars'],
                                'amount' => round($breakdownAmount, 2),
                                'payment' => round($breakdownAmount * $breakdownPaymentRatio, 2),
                                'balance' => round($breakdownAmount * $breakdownBalanceRatio, 2),
                            ]
                        ];
                    } else {
                        // Multiple items: use sequential priority-based filling
                        $remainingQuota = $breakdownAmount;
                        $totalDistributed = 0;
                        $breakdownPaymentRatio = $breakdownAmount > 0 ? ($breakdownPaid / $breakdownAmount) : 0;
                        $breakdownBalanceRatio = $breakdownAmount > 0 ? ($breakdownBalance / $breakdownAmount) : 0;

                        foreach ($sortedAdjustmentItems as $adjItem) {
                            if ($remainingQuota <= 0) {
                                break; // Breakdown amount is fully filled
                            }

                            $itemid = $adjItem['itemid'];
                            $originalItemAmount = $adjItem['amount'];

                            // Get remaining amount for this item
                            $itemRemaining = $adjustmentNestedItemsRemaining[$adjustmentDetailId][$itemid] ?? $originalItemAmount;

                            if ($itemRemaining <= 0) {
                                // This item is already exhausted, skip to next
                                continue;
                            }

                            // Fill as much as possible from this item
                            $amountToFill = min($itemRemaining, $remainingQuota);

                            // Calculate payment and balance proportionally for this filled amount
                            $itemPayment = round($amountToFill * $breakdownPaymentRatio, 2);
                            $itemBalance = round($amountToFill * $breakdownBalanceRatio, 2);

                            $nestedItems[] = [
                                'itemid' => $itemid,
                                'particulars' => $adjItem['particulars'],
                                'amount' => round($amountToFill, 2),
                                'payment' => $itemPayment,
                                'balance' => round(max(0, $itemBalance), 2),
                            ];

                            // Update remaining amounts
                            $adjustmentNestedItemsRemaining[$adjustmentDetailId][$itemid] -= $amountToFill;
                            $remainingQuota -= $amountToFill;
                            $totalDistributed += $amountToFill;
                        }

                        // Ensure nested items sum to exactly the breakdown amount
                        $difference = $breakdownAmount - $totalDistributed;
                        if (abs($difference) > 0.01 && !empty($nestedItems)) {
                            // Adjust the last nested item to make the sum exact
                            $lastIndex = count($nestedItems) - 1;
                            $nestedItems[$lastIndex]['amount'] = round($nestedItems[$lastIndex]['amount'] + $difference, 2);

                            // Recalculate payment and balance for the adjusted amount
                            $adjustedAmount = $nestedItems[$lastIndex]['amount'];
                            $nestedItems[$lastIndex]['payment'] = round($adjustedAmount * $breakdownPaymentRatio, 2);
                            $nestedItems[$lastIndex]['balance'] = round($adjustedAmount * $breakdownBalanceRatio, 2);

                            // Also adjust the remaining amount tracking
                            $lastItemId = $sortedAdjustmentItems[$lastIndex]['itemid'] ?? null;
                            if ($lastItemId && isset($adjustmentNestedItemsRemaining[$adjustmentDetailId][$lastItemId])) {
                                $adjustmentNestedItemsRemaining[$adjustmentDetailId][$lastItemId] -= $difference;
                            }
                        } else if (empty($nestedItems) && !empty($sortedAdjustmentItems)) {
                            // All items exhausted - fallback to proportional distribution
                            $totalItemsAmount = array_sum(array_column($sortedAdjustmentItems, 'amount'));

                            if ($totalItemsAmount > 0) {
                                foreach ($sortedAdjustmentItems as $adjItem) {
                                    $proportion = $adjItem['amount'] / $totalItemsAmount;
                                    $proportionalAmount = $breakdownAmount * $proportion;

                                    $itemPayment = round($proportionalAmount * $breakdownPaymentRatio, 2);
                                    $itemBalance = round($proportionalAmount * $breakdownBalanceRatio, 2);

                                    $nestedItems[] = [
                                        'itemid' => $adjItem['itemid'],
                                        'particulars' => $adjItem['particulars'],
                                        'amount' => round($proportionalAmount, 2),
                                        'payment' => $itemPayment,
                                        'balance' => round(max(0, $itemBalance), 2),
                                    ];
                                }

                                // Ensure the sum is exact (handle rounding)
                                $proportionalSum = array_sum(array_column($nestedItems, 'amount'));
                                $finalDifference = $breakdownAmount - $proportionalSum;
                                if (abs($finalDifference) > 0.01 && !empty($nestedItems)) {
                                    $lastIndex = count($nestedItems) - 1;
                                    $nestedItems[$lastIndex]['amount'] = round($nestedItems[$lastIndex]['amount'] + $finalDifference, 2);
                                    $adjustedAmount = $nestedItems[$lastIndex]['amount'];
                                    $nestedItems[$lastIndex]['payment'] = round($adjustedAmount * $breakdownPaymentRatio, 2);
                                    $nestedItems[$lastIndex]['balance'] = round($adjustedAmount * $breakdownBalanceRatio, 2);
                                }
                            }
                        }
                    }
                }

                // Add this installment to the items array for breakdown display
                $adjustmentSums[$adjustmentKey]['items'][] = [
                    'particulars' => $particulars, // Full particulars with suffix (e.g., "ADJUSTMENT: INTRAMS - June")
                    'amount' => $originalAmount,
                    'payment' => ($item['amountpay'] ?? 0),
                    'balance' => ($item['balance'] ?? max(0, $originalAmount - ($item['amountpay'] ?? 0))),
                    'classid' => $classid,
                    'paymentsetupdetail_id' => $item['paymentsetupdetail_id'] ?? null,
                    'items' => $nestedItems // Nested items showing individual fee items
                ];

                continue; // Skip regular fee processing for adjustment entries
            }

            // For laboratory fees, group by subject (laboratory_fee_id) instead of just classid
            // This ensures each subject has its own parent item with breakdown items
            if ($labFeeClassId && $classid == $labFeeClassId) {
                // Get laboratory_fee_id from the item (should be available from getStudentPaymentSchedules)
                $laboratoryFeeId = $item['laboratory_fee_id'] ?? null;

                // Debug for student 2
                if ($studid == 2) {
                    \Log::debug('[LAB-FEE-SCHEDULE-PAID] Processing lab fee item', [
                        'particulars' => substr($item['particulars'] ?? '', 0, 60),
                        'laboratory_fee_id' => $laboratoryFeeId,
                        'paymentsetupdetail_id' => $item['paymentsetupdetail_id'] ?? 'none',
                        'amountpay' => $item['amountpay'] ?? 0,
                        'balance' => $item['balance'] ?? 0
                    ]);
                }

                // Track scheduled payment per lab fee and paymentsetupdetail to avoid cross-subject remapping
                if ($laboratoryFeeId) {
                    $psdKey = $item['paymentsetupdetail_id'] ?? 'none';
                    if (!isset($labFeeSchedulePaid[$laboratoryFeeId])) {
                        $labFeeSchedulePaid[$laboratoryFeeId] = [];
                    }
                    $labFeeSchedulePaid[$laboratoryFeeId][$psdKey] = ($labFeeSchedulePaid[$laboratoryFeeId][$psdKey] ?? 0) + ($item['amountpay'] ?? 0);
                    // Save full schedule breakdown for later forced override in school_fees
                    if (!isset($labFeeScheduleSummary[$laboratoryFeeId])) {
                        $labFeeScheduleSummary[$laboratoryFeeId] = [];
                    }
                    $labFeeScheduleSummary[$laboratoryFeeId][$psdKey] = [
                        'amount' => $baseAmount,
                        'paid' => $item['amountpay'] ?? 0,
                        'balance' => $item['balance'] ?? max(0, $baseAmount - ($item['amountpay'] ?? 0)),
                    ];

                    if ($studid == 2) {
                        \Log::debug('[LAB-FEE-SCHEDULE-PAID] Added to labFeeSchedulePaid', [
                            'laboratory_fee_id' => $laboratoryFeeId,
                            'paymentsetupdetail_id' => $psdKey,
                            'total_paid_so_far' => $labFeeSchedulePaid[$laboratoryFeeId][$psdKey]
                        ]);
                    }
                }
                $labFeeSubjectInfo = null;

                // If laboratory_fee_id is not available, try to get from particulars or match by amount
                if (!$laboratoryFeeId) {
                    // Try to extract from particulars (e.g., "Laboratory Fee - IT 205 (...)")
                    if (preg_match('/Laboratory Fee - ([A-Z0-9]+)/', $particulars, $matches)) {
                        $subjcode = $matches[1];
                        $labFeeMatch = DB::table('labfees as lf')
                            ->leftJoin('college_prospectus as cp', 'lf.subjid', '=', 'cp.id')
                            ->select('lf.id as laboratory_fee_id', 'cp.subjCode as subjcode', 'cp.subjDesc as subjdesc')
                            ->where('lf.syid', $syid)
                            ->where('lf.semid', $semid)
                            ->where('lf.deleted', 0)
                            ->where('cp.subjCode', $subjcode)
                            ->first();

                        if ($labFeeMatch) {
                            $laboratoryFeeId = $labFeeMatch->laboratory_fee_id;
                            $labFeeSubjectInfo = $labFeeMatch;
                        }
                    }

                    // If still no match, try to match by amount
                    if (!$laboratoryFeeId) {
                        $labFeeMatch = DB::table('labfees as lf')
                            ->leftJoin('college_prospectus as cp', 'lf.subjid', '=', 'cp.id')
                            ->select('lf.id as laboratory_fee_id', 'lf.amount as lab_fee_total', 'cp.subjCode as subjcode', 'cp.subjDesc as subjdesc')
                            ->where('lf.syid', $syid)
                            ->where('lf.semid', $semid)
                            ->where('lf.deleted', 0)
                            ->get();

                        // Match by total amount (closest match)
                        foreach ($labFeeMatch as $labFee) {
                            if (abs($labFee->lab_fee_total - $baseAmount) < 0.01) {
                                $laboratoryFeeId = $labFee->laboratory_fee_id;
                                $labFeeSubjectInfo = $labFee;
                                break;
                            }
                        }
                    }
                } else {
                    // Get subject information from laboratory_fee_id
                    $labFeeInfo = DB::table('labfees as lf')
                        ->leftJoin('college_prospectus as cp', 'lf.subjid', '=', 'cp.id')
                        ->select('cp.subjCode as subjcode', 'cp.subjDesc as subjdesc')
                        ->where('lf.id', $laboratoryFeeId)
                        ->where('lf.deleted', 0)
                        ->first();

                    if ($labFeeInfo) {
                        $labFeeSubjectInfo = (object) [
                            'subjcode' => $labFeeInfo->subjcode,
                            'subjdesc' => $labFeeInfo->subjdesc
                        ];
                    }
                }

                // Create a unique key for this laboratory fee subject
                // IMPORTANT: Each laboratory fee must have its own unique key to prevent grouping
                if ($laboratoryFeeId) {
                    $labFeeKey = 'LAB_FEE_' . $laboratoryFeeId;
                } else {
                    // Fallback: use classid + amount + particulars hash to ensure each laboratory fee is unique
                    // Do NOT use only paymentsetupdetail_id as multiple lab fees might share the same payment schedule
                    $uniqueHash = md5($classid . '_' . $baseAmount . '_' . $particulars . '_' . ($item['paymentsetupdetail_id'] ?? ''));
                    $labFeeKey = 'LAB_FEE_UNKNOWN_' . $classid . '_' . substr($uniqueHash, 0, 8);
                }

                if (!isset($feesByParticular[$labFeeKey])) {
                    // Get subject information for label
                    $subjectLabel = $particulars; // Use the particulars from the item (should already have subject info)
                    if (!$labFeeSubjectInfo && $laboratoryFeeId) {
                        // Try to get from laboratory_fee_items
                        $labFeeInfo = DB::table('labfees as lf')
                            ->leftJoin('college_prospectus as cp', 'lf.subjid', '=', 'cp.id')
                            ->select('cp.subjCode as subjcode', 'cp.subjDesc as subjdesc')
                            ->where('lf.id', $laboratoryFeeId)
                            ->where('lf.deleted', 0)
                            ->first();

                        if ($labFeeInfo) {
                            $subjectLabel = "Laboratory Fee - {$labFeeInfo->subjcode} ({$labFeeInfo->subjdesc})";
                        }
                    } elseif ($labFeeSubjectInfo) {
                        $subjectLabel = "Laboratory Fee - {$labFeeSubjectInfo->subjcode} ({$labFeeSubjectInfo->subjdesc})";
                    }

                    $feesByParticular[$labFeeKey] = [
                        'classid' => $classid,
                        'particulars' => $subjectLabel,
                        'total_amount' => 0,
                        'total_paid' => 0,
                        'total_balance' => 0,
                        'items' => [],
                        'itemid' => $labFeeItemId, // Add itemid from labfeesetup for laboratory fees
                        'laboratory_fee_id' => $laboratoryFeeId,
                        'is_laboratory_fee' => true
                    ];
                }

                // Use the labFeeKey for grouping
                $groupKey = $labFeeKey;
            } else {
                // Regular fees - group by classid only (parent item is the classification name)
                // For item management items, use item_management_id for uniqueness (like laboratory fees)
                $itemManagementId = $item['item_management_id'] ?? null;

                // Create unique key: use item_management_id directly for item management items
                if ($itemManagementId) {
                    $groupKey = $itemManagementId;

                    // Debug logging for item management items
                    \Log::info('[ITEM-MGMT-GROUPING] Using item_management_id as groupKey', [
                        'item_management_id' => $itemManagementId,
                        'groupKey' => $groupKey,
                        'classid' => $classid,
                        'particulars' => $particulars,
                        'amount' => $baseAmount
                    ]);
                } else {
                    $groupKey = $classid;
                }

                if (!isset($feesByParticular[$groupKey])) {
                    $feeGroupParticulars = $particulars;

                    // For college students, enhance the main tuition fee label with units calculation
                    if ($studentInfo->levelid >= 17 && $studentInfo->levelid <= 25 && $classid == 1) {
                        // For the main fee group, we need to calculate based on the total tuition fee amount
                        // We'll get this after all items are processed, so for now just use the base label
                        // The enhanced label will be applied when building the final response
                    }

                    $feesByParticular[$groupKey] = [
                        'classid' => $classid,
                        'particulars' => $feeGroupParticulars,
                        'total_amount' => 0,
                        'total_paid' => 0,
                        'total_balance' => 0,
                        'items' => [],
                        'item_management_id' => $itemManagementId, // Store for later reference
                        'is_item_management' => $itemManagementId ? true : false
                    ];
                }
            }

            // For breakdown items, use the original amount and balance from payment schedule
            // This ensures paid status is accurate
            $itemAmount = $baseAmount;
            $itemPaid = $item['amountpay'] ?? 0;
            $itemBalance = $item['balance'] ?? max(0, $originalAmount - $itemPaid);

            // Debug logging for student 2 laboratory fees
            if ($studid == 2 && $labFeeClassId && $classid == $labFeeClassId) {
                \Log::info('[TERM-FINANCIAL-DEBUG] Processing lab fee schedule item', [
                    'particulars' => substr($particulars, 0, 60),
                    'laboratory_fee_id' => $item['laboratory_fee_id'] ?? 'NOT SET',
                    'laboratoryFeeId_variable' => $laboratoryFeeId ?? 'NOT SET',
                    'labFeeKey' => $labFeeKey ?? 'NOT SET',
                    'groupKey' => $groupKey,
                    'itemAmount' => $itemAmount,
                    'itemPaid' => $itemPaid,
                    'itemBalance' => $itemBalance,
                    'item_amountpay' => $item['amountpay'] ?? 'NOT SET',
                    'item_balance' => $item['balance'] ?? 'NOT SET',
                    'FULL_ITEM_DATA' => json_encode($item)
                ]);
            }

            $feesByParticular[$groupKey]['total_amount'] += $itemAmount;
            $feesByParticular[$groupKey]['total_paid'] += $itemPaid;
            $feesByParticular[$groupKey]['total_balance'] += $itemBalance;

            // Add breakdown item for this fee (only if amount > 0)
            // Include payment number/due date to differentiate payment periods
            if ($itemAmount > 0) {
                $itemLabel = $particulars;

                // For school fee breakdown items with paymentno, get description from paymentsetupdetail
                if ($paymentNo && isset($item['pschemeid']) && $item['pschemeid']) {
                    $paymentDescription = DB::table('paymentsetupdetail')
                        ->where('paymentid', $item['pschemeid'])
                        ->where('paymentno', $paymentNo)
                        ->where('deleted', 0)
                        ->value('description');

                    if ($paymentDescription) {
                        // Use the payment description directly (e.g., "1ST MONTH", "2ND MONTH")
                        $itemLabel .= " - {$paymentDescription}";
                    } else {
                        // Fallback: use payment number to create description (same format as monthly assessments)
                        $paymentDescriptions = [
                            1 => 'DOWNPAYMENT',
                            2 => '1ST MONTH',
                            3 => '2ND MONTH',
                            4 => '3RD MONTH',
                            5 => '4TH MONTH',
                            6 => '5TH MONTH',
                            7 => '6TH MONTH',
                            8 => '7TH MONTH',
                            9 => '8TH MONTH',
                            10 => '9TH MONTH',
                            11 => '10TH MONTH',
                            12 => 'FINAL PAYMENT'
                        ];

                        $fallbackDescription = $paymentDescriptions[$paymentNo] ?? "PAYMENT {$paymentNo}";
                        $itemLabel .= " - {$fallbackDescription}";
                    }
                } elseif ($dueDate) {
                    $itemLabel .= " - Due " . date('M d, Y', strtotime($dueDate));
                } elseif ($paymentNo) {
                    // Fallback: use payment number to create description
                    $paymentDescriptions = [
                        1 => 'DOWNPAYMENT',
                        2 => '1ST MONTH',
                        3 => '2ND MONTH',
                        4 => '3RD MONTH',
                        5 => '4TH MONTH',
                        6 => '5TH MONTH',
                        7 => '6TH MONTH',
                        8 => '7TH MONTH',
                        9 => '8TH MONTH',
                        10 => '9TH MONTH',
                        11 => '10TH MONTH',
                        12 => 'FINAL PAYMENT'
                    ];

                    $fallbackDescription = $paymentDescriptions[$paymentNo] ?? "PAYMENT {$paymentNo}";
                    $itemLabel .= " - {$fallbackDescription}";
                } else {
                    // No payment number - check if this is a ONE TIME PAYMENT or NO DUE DATE
                    if (isset($item['pschemeid']) && $item['pschemeid']) {
                        // Has payment setup - check if it's ONE TIME (only 1 schedule entry with valid duedate)
                        $paymentSchedule = DB::table('paymentsetupdetail')
                            ->where('paymentid', $item['pschemeid'])
                            ->where('deleted', 0)
                            ->first();

                        // Check if it has exactly 1 schedule entry
                        $scheduleCount = DB::table('paymentsetupdetail')
                            ->where('paymentid', $item['pschemeid'])
                            ->where('deleted', 0)
                            ->count();

                        // Only label as ONE TIME PAYMENT if it has 1 schedule AND a valid duedate
                        if ($scheduleCount == 1 && $paymentSchedule && $paymentSchedule->duedate) {
                            $itemLabel .= " - ONE TIME PAYMENT";
                        } else {
                            $itemLabel .= " - NO DUE DATE";
                        }
                    } else {
                        // No payment setup - label as NO DUE DATE
                        $itemLabel .= " - NO DUE DATE";
                    }
                }

                // For laboratory fees, use itemid from labfeesetup; for other fees, use itemid from item
                $breakdownItemId = null;
                if ($labFeeClassId && $classid == $labFeeClassId) {
                    // Laboratory fee - use itemid from labfeesetup
                    $breakdownItemId = $labFeeItemId;
                } else {
                    // Regular fee - use itemid from item
                    $breakdownItemId = $item['itemid'] ?? null;
                }

                $feesByParticular[$groupKey]['items'][] = [
                    'particulars' => $itemLabel,
                    'amount' => round($itemAmount, 2),
                    'payment' => round($itemPaid, 2),
                    'balance' => round($itemBalance, 2),
                    'classid' => $classid,
                    'itemid' => $breakdownItemId, // Use labfeesetup itemid for laboratory fees, item itemid for others
                    'paymentsetupdetail_id' => $item['paymentsetupdetail_id'] ?? null,
                    'laboratory_fee_id' => $laboratoryFeeId ?? null, // Add laboratory_fee_id for laboratory fees
                ];
            }

            // Collect adjustments separately
            if ($adjustment > 0) {
                $adjustments[] = [
                    'type' => 'debit',
                    'classid' => $classid,
                    'particulars' => $particulars,
                    'amount' => $adjustment
                ];
            }
            if ($creditAdjustment > 0) {
                $adjustments[] = [
                    'type' => 'credit',
                    'classid' => $classid,
                    'particulars' => $particulars,
                    'amount' => $creditAdjustment
                ];
            }
            // Skip discount processing here - discounts are handled by getDiscountsAndAdjustmentsForTerm
            // which provides proper discount_id information
            // if ($discount > 0) {
            //     $adjustments[] = [
            //         'type' => 'discount',
            //         'classid' => $classid,
            //         'particulars' => $particulars,
            //         'amount' => $discount
            //     ];
            // }
        }

        // SKIP adding adjustments from adjustmentSums
        // These are being added by getDiscountsAndAdjustmentsForTerm() below with complete data
        // Including items breakdown, mop field, and adjustment_id
        // Adding them here would create duplicates with incomplete data

        // Get standalone discounts and adjustments for this term
        $standaloneDiscountsAndAdjustments = $this->getDiscountsAndAdjustmentsForTerm($studid, $syid, $semid, $studentInfo->levelid);

        // Add standalone discounts
        foreach ($standaloneDiscountsAndAdjustments['discounts'] as $discount) {
            $adjustments[] = [
                'type' => 'discount',
                'classid' => $discount['classid'],
                'particulars' => $discount['particulars'],
                'amount' => $discount['amount'],
                'paid' => $discount['paid'],
                'balance' => $discount['balance'],
                'discount_id' => $discount['discount_id'],
                'transaction_date' => $discount['transaction_date'] ?? null,
                'created_by' => $discount['created_by'] ?? null,
                'is_voided' => $discount['is_voided'] ?? 0
            ];
        }

        // Add standalone debit adjustments
        // BUT skip if already added from payment schedule (to avoid duplicates)
        foreach ($standaloneDiscountsAndAdjustments['debit_adjustments'] as $debitAdj) {
            // Check if this adjustment was already added from payment schedule by comparing adjustment_id
            $alreadyAdded = false;
            $adjustmentId = $debitAdj['adjustment_id'] ?? null;

            if ($adjustmentId) {
                // Check if this adjustment_id is already in the adjustments array
                foreach ($adjustments as $existing) {
                    if (($existing['adjustment_id'] ?? null) === $adjustmentId) {
                        $alreadyAdded = true;
                        break;
                    }
                }
            }

            // Skip if already added from payment schedule (which has correct paid amounts)
            if ($alreadyAdded) {
                continue;
            }

            $adjustments[] = [
                'type' => 'debit',
                'classid' => $debitAdj['classid'],
                'particulars' => $debitAdj['particulars'],
                'amount' => $debitAdj['amount'],
                'paid' => $debitAdj['paid'],
                'balance' => $debitAdj['balance'],
                'adjustment_id' => $debitAdj['adjustment_id'],
                'transaction_date' => $debitAdj['transaction_date'] ?? null,
                'created_by' => $debitAdj['created_by'] ?? null,
                'adjstatus' => $debitAdj['adjstatus'] ?? 'SUBMITTED',
                'is_voided' => $debitAdj['is_voided'] ?? 0,
                'mop' => $debitAdj['mop'] ?? null,
                'items' => $debitAdj['items'] ?? [] // Include breakdown items
            ];
        }

        // Add standalone credit adjustments
        // BUT skip if already added from payment schedule (to avoid duplicates)
        foreach ($standaloneDiscountsAndAdjustments['credit_adjustments'] as $creditAdj) {
            // Check if this adjustment was already added from payment schedule by comparing adjustment_id
            $alreadyAdded = false;
            $adjustmentId = $creditAdj['adjustment_id'] ?? null;

            if ($adjustmentId) {
                // Check if this adjustment_id is already in the adjustments array
                foreach ($adjustments as $existing) {
                    if (($existing['adjustment_id'] ?? null) === $adjustmentId) {
                        $alreadyAdded = true;
                        break;
                    }
                }
            }

            // Skip if already added from payment schedule (which has correct paid amounts)
            if ($alreadyAdded) {
                continue;
            }

            $adjustments[] = [
                'type' => 'credit',
                'classid' => $creditAdj['classid'],
                'particulars' => $creditAdj['particulars'],
                'amount' => $creditAdj['amount'],
                'paid' => $creditAdj['paid'],
                'balance' => $creditAdj['balance'],
                'adjustment_id' => $creditAdj['adjustment_id'],
                'transaction_date' => $creditAdj['transaction_date'] ?? null,
                'created_by' => $creditAdj['created_by'] ?? null,
                'adjstatus' => $creditAdj['adjstatus'] ?? 'SUBMITTED',
                'is_voided' => $creditAdj['is_voided'] ?? 0,
                'mop' => $creditAdj['mop'] ?? null
            ];
        }

        // Book entries will be added later after item_management processing
        // This prevents duplicate entries and ensures proper payment calculation from database
        $bookEntries = [];

        // Fetch tuition items for each classification from tuitionitems table
        // First, get the student's enrollment to find the tuitionheader (feesid)
        $enrollmentTables = [
            'enrolledstud' => ['levelid' => [1, 13]],
            'sh_enrolledstud' => ['levelid' => [14, 15]],
            'college_enrolledstud' => ['levelid' => [17, 25]],
            'tesda_enrolledstud' => ['levelid' => [26]],
        ];

        $feesId = null;
        $tuitionItemsByClassId = []; // Initialize outside if block for wider scope

        foreach ($enrollmentTables as $table => $config) {
            $levelRange = $config['levelid'] ?? [];
            // Safety check: ensure levelRange is an array with at least one element
            if (empty($levelRange) || !is_array($levelRange)) {
                continue;
            }
            // For single-element arrays (like tesda), use the same value for min and max
            $minLevel = $levelRange[0] ?? null;
            $maxLevel = isset($levelRange[1]) ? $levelRange[1] : ($levelRange[0] ?? null);

            if (
                $minLevel !== null && $maxLevel !== null &&
                $studentInfo->levelid >= $minLevel && $studentInfo->levelid <= $maxLevel
            ) {
                $query = DB::table($table)
                    ->where('studid', $studid)
                    ->where('syid', $syid)
                    ->where('deleted', 0);

                // Add semester filter for tables that use semid
                if ($table !== 'enrolledstud' && $table !== 'tesda_enrolledstud' && !is_null($semid)) {
                    $query->where('semid', $semid);
                }

                $enrollment = $query->first();
                if ($enrollment && isset($enrollment->feesid)) {
                    $feesId = $enrollment->feesid;
                    break;
                }
            }
        }

        // If we found the tuitionheader, fetch tuition items for each classification
        if ($feesId) {
            // Get all tuitiondetails with their tuitionitems for this tuitionheader
            $tuitionDetailsWithItems = DB::table('tuitiondetail as td')
                ->join('itemclassification as ic', 'td.classificationid', '=', 'ic.id')
                ->leftJoin('tuitionitems as ti', function ($join) {
                    $join->on('td.id', '=', 'ti.tuitiondetailid')
                        ->where('ti.deleted', '=', 0);
                })
                ->leftJoin('items as i', 'ti.itemid', '=', 'i.id')
                ->where('td.headerid', $feesId)
                ->where('td.deleted', 0)
                ->select([
                    'td.classificationid as classid',
                    'ti.id as tuitionitem_id',
                    'ti.itemid',
                    'i.description as item_description',
                    'ti.amount as item_amount',
                    'ti.payment_priority_sequence',
                    'ti.createddatetime'
                ])
                ->orderBy('ti.payment_priority_sequence', 'asc')
                ->orderBy('ti.createddatetime', 'asc')
                ->get();

            // Group tuition items by classid, maintaining priority order
            foreach ($tuitionDetailsWithItems as $row) {
                if ($row->tuitionitem_id) { // Only include rows that have tuition items
                    if (!isset($tuitionItemsByClassId[$row->classid])) {
                        $tuitionItemsByClassId[$row->classid] = [];
                    }
                    $tuitionItemsByClassId[$row->classid][] = [
                        'itemid' => $row->itemid,
                        'particulars' => $row->item_description ?? 'Unknown Item',
                        'amount' => round((float) $row->item_amount, 2),
                        'classid' => $row->classid,
                        'payment_priority_sequence' => $row->payment_priority_sequence ?? 9999,
                        'createddatetime' => $row->createddatetime ?? null,
                    ];
                }
            }

            // Build payment map by paymentsetupdetail_id | classid | itemid (exact item payments)
            // Join cashtrans ON transno + classid to avoid multiplying rows across classifications
            $paymentsByPsdClassItem = DB::table('chrngtrans as ct')
                ->join('chrngtransitems as cti', 'ct.transno', '=', 'cti.chrngtransid')
                ->join('chrngcashtrans as cct', function ($join) {
                    $join->on('ct.transno', '=', 'cct.transno')
                        ->on('cti.classid', '=', 'cct.classid');
                })
                ->select(
                    'cct.paymentsetupdetail_id',
                    'cti.classid',
                    'cti.itemid',
                    DB::raw('SUM(cti.amount) as total_paid')
                )
                ->where('ct.studid', $studid)
                ->where('ct.syid', $syid)
                ->where('cct.syid', $syid)
                ->where('ct.cancelled', 0)
                ->where('cct.deleted', 0)
                ->where('cti.deleted', 0)
                ->when($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25, function ($q) use ($semid) {
                    $q->where('ct.semid', $semid);
                })
                ->when($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25, function ($q) use ($semid) {
                    $q->where(function ($subQ) use ($semid) {
                        $subQ->where('cct.semid', $semid)->orWhereNull('cct.semid');
                    });
                })
                ->groupBy('cct.paymentsetupdetail_id', 'cti.classid', 'cti.itemid')
                ->get()
                ->keyBy(function ($row) {
                    return ($row->paymentsetupdetail_id ?? 'none') . '|' . $row->classid . '|' . $row->itemid;
                });

            // Fallback map by classid|itemid (for items without paymentsetupdetail_id)
            $paymentsByClassItem = DB::table('chrngtrans as ct')
                ->join('chrngtransitems as cti', 'ct.transno', '=', 'cti.chrngtransid')
                ->join('chrngcashtrans as cct', function ($join) {
                    $join->on('ct.transno', '=', 'cct.transno')
                        ->on('cti.classid', '=', 'cct.classid');
                })
                ->select('cti.classid', 'cti.itemid', DB::raw('SUM(cti.amount) as total_paid'))
                ->where('ct.studid', $studid)
                ->where('ct.syid', $syid)
                ->where('cct.syid', $syid)
                ->where('ct.cancelled', 0)
                ->where('cct.deleted', 0)
                ->where('cti.deleted', 0)
                ->when($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25, function ($q) use ($semid) {
                    $q->where('ct.semid', $semid);
                })
                ->when($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25, function ($q) use ($semid) {
                    $q->where(function ($subQ) use ($semid) {
                        $subQ->where('cct.semid', $semid)->orWhereNull('cct.semid');
                    });
                })
                ->groupBy('cti.classid', 'cti.itemid')
                ->get()
                ->keyBy(function ($row) {
                    return $row->classid . '|' . $row->itemid;
                });

            // Laboratory-safe map keyed by PSD|classid|itemid|particulars_base to avoid cross-subject mixing
            $paymentsByPsdClassItemPart = DB::table('chrngtrans as ct')
                ->join('chrngtransitems as cti', 'ct.transno', '=', 'cti.chrngtransid')
                ->join('chrngcashtrans as cct', function ($join) {
                    $join->on('ct.transno', '=', 'cct.transno')
                        ->on('cti.classid', '=', 'cct.classid');
                })
                ->select(
                    'cct.paymentsetupdetail_id',
                    'cti.classid',
                    'cti.itemid',
                    'cct.particulars',
                    DB::raw('SUM(cti.amount) as total_paid')
                )
                ->where('ct.studid', $studid)
                ->where('ct.syid', $syid)
                ->where('cct.syid', $syid)
                ->where('ct.cancelled', 0)
                ->where('cct.deleted', 0)
                ->where('cti.deleted', 0)
                ->when($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25, function ($q) use ($semid) {
                    $q->where('ct.semid', $semid);
                })
                ->when($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25, function ($q) use ($semid) {
                    $q->where(function ($subQ) use ($semid) {
                        $subQ->where('cct.semid', $semid)->orWhereNull('cct.semid');
                    });
                })
                ->groupBy('cct.paymentsetupdetail_id', 'cti.classid', 'cti.itemid', 'cct.particulars')
                ->get()
                ->keyBy(function ($row) {
                    $base = preg_replace('/\\s*-\\s*(DOWNPAYMENT|1ST\\s+MONTH|2ND\\s+MONTH|3RD\\s+MONTH|4TH\\s+MONTH|5TH\\s+MONTH|6TH\\s+MONTH|7TH\\s+MONTH|8TH\\s+MONTH|9TH\\s+MONTH|10TH\\s+MONTH|FINAL\\s+PAYMENT|\\d+(ST|ND|RD|TH)\\s+PAYMENT|\\d+(ST|ND|RD|TH)\\s+MONTH|ONE\\s+TIME\\s+PAYMENT|NO\\s+DUE\\s+DATE|Due\\s+\\w+\\s+\\d+,\\s+\\d{4}|January|February|March|April|May|June|July|August|September|October|November|December).*/i', '', $row->particulars ?? '');
                    $base = trim($base ?? '');
                    return ($row->paymentsetupdetail_id ?? 'none') . '|' . $row->classid . '|' . $row->itemid . '|' . $base;
                });

            // Track remaining amounts for nested items across all breakdown items (for sequential filling)
            $enrolledNestedItemsRemaining = []; // Key: classid => [itemid => remaining_amount]

            // Initialize remaining amounts for all classids
            foreach ($feesByParticular as $classid => $fee) {
                if (!isset($enrolledNestedItemsRemaining[$classid])) {
                    $enrolledNestedItemsRemaining[$classid] = [];
                    if (isset($tuitionItemsByClassId[$classid]) && !empty($tuitionItemsByClassId[$classid])) {
                        foreach ($tuitionItemsByClassId[$classid] as $tuitionItem) {
                            $enrolledNestedItemsRemaining[$classid][$tuitionItem['itemid']] = $tuitionItem['amount'];
                        }
                    }
                }
            }

            // Fetch laboratory fee items for lab fee classid, grouped by laboratory_fee_id
            $laboratoryFeeItemsByLabFeeId = [];
            $laboratoryFeeItemsByClassId = [];

            if ($labFeeClassId) {
                // Get all laboratory fee items
                $laboratoryFeeItems = DB::table('laboratory_fee_items as lfi')
                    ->leftJoin('labfees as lf', 'lfi.laboratory_fee_id', '=', 'lf.id')
                    ->leftJoin('items as i', 'lfi.item_id', '=', 'i.id')
                    ->leftJoin('college_prospectus as cp', 'lf.subjid', '=', 'cp.id')
                    ->select(
                        'lfi.id',
                        'lfi.laboratory_fee_id',
                        'lfi.item_id',
                        'lfi.amount',
                        'lf.subjid',
                        'cp.subjCode as subjcode',
                        'cp.subjDesc as subjdesc',
                        'i.description as item_description'
                    )
                    ->where('lfi.deleted', 0)
                    ->where('lf.syid', $syid)
                    ->where('lf.semid', $semid)
                    ->orderBy('lfi.laboratory_fee_id')
                    ->orderBy('lfi.id')
                    ->get();

                // Group by laboratory_fee_id for subject-specific grouping
                $labFeeGroups = $laboratoryFeeItems->groupBy('laboratory_fee_id');
                foreach ($labFeeGroups as $labFeeId => $items) {
                    $laboratoryFeeItemsByLabFeeId[$labFeeId] = [];
                    foreach ($items as $labItem) {
                        $laboratoryFeeItemsByLabFeeId[$labFeeId][] = [
                            'itemid' => $labItem->item_id,
                            'particulars' => $labItem->item_description ?? 'Laboratory Fee Item',
                            'amount' => (float) $labItem->amount,
                            'laboratory_fee_id' => $labFeeId,
                            'subjcode' => $labItem->subjcode,
                            'subjdesc' => $labItem->subjdesc,
                        ];
                    }

                    // Also add to lab fee classid array for backward compatibility
                    foreach ($items as $labItem) {
                        if (!isset($laboratoryFeeItemsByClassId[$labFeeClassId])) {
                            $laboratoryFeeItemsByClassId[$labFeeClassId] = [];
                        }
                        $laboratoryFeeItemsByClassId[$labFeeClassId][] = [
                            'itemid' => $labItem->item_id,
                            'particulars' => $labItem->item_description ?? 'Laboratory Fee Item',
                            'amount' => (float) $labItem->amount,
                            'laboratory_fee_id' => $labFeeId,
                            'subjcode' => $labItem->subjcode,
                            'subjdesc' => $labItem->subjdesc,
                        ];
                    }
                }

                // Initialize remaining amounts for laboratory fees by laboratory_fee_id
                foreach ($feesByParticular as $key => $fee) {
                    if (isset($fee['is_laboratory_fee']) && $fee['is_laboratory_fee'] && isset($fee['laboratory_fee_id'])) {
                        $labFeeId = $fee['laboratory_fee_id'];
                        if (!isset($enrolledNestedItemsRemaining[$key])) {
                            $enrolledNestedItemsRemaining[$key] = [];

                            // Get lab_amount from labfees table
                            $labFeeData = DB::table('labfees')
                                ->where('id', $labFeeId)
                                ->where('deleted', 0)
                                ->first();

                            $labAmount = $labFeeData ? (float) ($labFeeData->lab_amount ?? 0) : 0;

                            // Initialize lab_amount remaining
                            if ($labAmount > 0) {
                                $enrolledNestedItemsRemaining[$key]['LAB_FEE'] = $labAmount;
                            }

                            // Initialize item amounts remaining
                            if (isset($laboratoryFeeItemsByLabFeeId[$labFeeId]) && !empty($laboratoryFeeItemsByLabFeeId[$labFeeId])) {
                                foreach ($laboratoryFeeItemsByLabFeeId[$labFeeId] as $labItem) {
                                    $enrolledNestedItemsRemaining[$key][$labItem['itemid']] = $labItem['amount'];
                                }
                            }
                        }
                    }
                }

                // Also initialize for lab fee classid for backward compatibility
                if (isset($feesByParticular[$labFeeClassId]) && !isset($enrolledNestedItemsRemaining[$labFeeClassId])) {
                    $enrolledNestedItemsRemaining[$labFeeClassId] = [];
                    if (isset($laboratoryFeeItemsByClassId[$labFeeClassId]) && !empty($laboratoryFeeItemsByClassId[$labFeeClassId])) {
                        foreach ($laboratoryFeeItemsByClassId[$labFeeClassId] as $labItem) {
                            $enrolledNestedItemsRemaining[$labFeeClassId][$labItem['itemid']] = $labItem['amount'];
                        }
                    }
                }
            }

            // Add tuition items to the corresponding fees in $feesByParticular
            foreach ($feesByParticular as $key => &$fee) {
                $classid = $fee['classid'] ?? null;

                // Debug for student 2
                if ($studid == 2 && isset($fee['is_laboratory_fee']) && $fee['is_laboratory_fee']) {
                    \Log::info('[LAB-FEE-NESTED-PATH] Processing laboratory fee', [
                        'fee_particulars' => $fee['particulars'] ?? 'N/A',
                        'laboratory_fee_id' => $fee['laboratory_fee_id'] ?? 'NOT SET',
                        'has_laboratory_fee_id' => isset($fee['laboratory_fee_id']) ? 'YES' : 'NO',
                        'classid' => $classid ?? 'N/A',
                        'in_tuitionItemsByClassId' => isset($tuitionItemsByClassId[$classid]) ? 'YES' : 'NO'
                    ]);
                }

                // Check if this is a laboratory fee grouped by subject
                if (isset($fee['is_laboratory_fee']) && $fee['is_laboratory_fee'] && isset($fee['laboratory_fee_id'])) {
                    $labFeeId = $fee['laboratory_fee_id'];

                    // Get lab_amount from labfees table
                    $labFeeData = DB::table('labfees')
                        ->where('id', $labFeeId)
                        ->where('deleted', 0)
                        ->first();

                    $labAmount = $labFeeData ? (float) ($labFeeData->lab_amount ?? 0) : 0;

                    // Process ALL laboratory fees (whether they have additional items or not)
                    // Laboratory fees grouped by subject - get nested items
                    // Nested items should include: lab_amount (as "LAB FEE") + items from laboratory_fee_items
                    foreach ($fee['items'] as &$breakdownItem) {
                        // Get the breakdown amount (this is what can be covered by this payment number)
                        $breakdownAmount = $breakdownItem['amount'] ?? 0;
                        $breakdownPaid = $breakdownItem['payment'] ?? 0;
                        $breakdownBalance = $breakdownItem['balance'] ?? 0;

                        // Build complete list of nested items: lab_amount first, then items
                        $allNestedItems = [];

                        // Add lab_amount as first nested item (if > 0)
                        if ($labAmount > 0) {
                            $allNestedItems[] = [
                                'itemid' => $labFeeItemId, // Use itemid from labfeesetup for laboratory fees
                                'particulars' => $labFeeItemDescription, // Use description from items table (e.g., "LABORATORY FEE")
                                'amount' => $labAmount,
                                'is_lab_fee' => true, // Flag to identify this as the lab fee itself
                            ];
                        }

                        // Add items from laboratory_fee_items (if any exist)
                        if (isset($laboratoryFeeItemsByLabFeeId[$labFeeId])) {
                            foreach ($laboratoryFeeItemsByLabFeeId[$labFeeId] as $labItem) {
                                $allNestedItems[] = [
                                    'itemid' => $labItem['itemid'],
                                    'particulars' => $labItem['particulars'],
                                    'amount' => $labItem['amount'],
                                    'is_lab_fee' => false,
                                ];
                            }
                        }

                        // Initialize remaining amounts for all nested items
                        if (!isset($enrolledNestedItemsRemaining[$key])) {
                            $enrolledNestedItemsRemaining[$key] = [];
                        }

                        // Initialize lab_amount remaining
                        if ($labAmount > 0 && !isset($enrolledNestedItemsRemaining[$key]['LAB_FEE'])) {
                            $enrolledNestedItemsRemaining[$key]['LAB_FEE'] = $labAmount;
                        }

                        // Initialize item amounts remaining (if any items exist)
                        if (isset($laboratoryFeeItemsByLabFeeId[$labFeeId])) {
                            foreach ($laboratoryFeeItemsByLabFeeId[$labFeeId] as $labItem) {
                                $itemid = $labItem['itemid'];
                                if (!isset($enrolledNestedItemsRemaining[$key][$itemid])) {
                                    $enrolledNestedItemsRemaining[$key][$itemid] = $labItem['amount'];
                                }
                            }
                        }

                        // Use sequential priority-based filling for nested items
                        $remainingQuota = $breakdownAmount;
                        $itemsWithPayment = [];
                        $totalDistributed = 0;

                        // For laboratory fees, get actual payments from chrngtransitems
                        $paidItemIds = [];
                        $paymentsByItemId = [];
                        if ($breakdownPaid > 0) {
                            $paymentsetupdetailId = $breakdownItem['paymentsetupdetail_id'] ?? null;
                            if ($paymentsetupdetailId) {
                                // Get the lab fee particulars to match against chrngcashtrans
                                // Remove suffix to get base particulars for matching
                                $labFeeParticulars = $fee['particulars'] ?? '';
                                $labFeeParticularsBase = preg_replace('/\s*-\s*(DOWNPAYMENT|1ST\s+MONTH|2ND\s+MONTH|3RD\s+MONTH|4TH\s+MONTH|5TH\s+MONTH|6TH\s+MONTH|7TH\s+MONTH|8TH\s+MONTH|9TH\s+MONTH|10TH\s+MONTH|FINAL\s+PAYMENT|\d+(ST|ND|RD|TH)\s+PAYMENT|\d+(ST|ND|RD|TH)\s+MONTH|ONE\s+TIME\s+PAYMENT|NO\s+DUE\s+DATE|Due\s+\w+\s+\d+,\s+\d{4}|January|February|March|April|May|June|July|August|September|October|November|December)$/i', '', $labFeeParticulars);
                                $labFeeParticularsBase = trim($labFeeParticularsBase);

                                // Get all payments for this payment setup detail and classid
                                $allPayments = DB::table('chrngtrans as ct')
                                    ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
                                    ->join('chrngtransitems as cti', 'ct.transno', '=', 'cti.chrngtransid')
                                    ->where('ct.studid', $studid)
                                    ->where('ct.syid', $syid)
                                    ->where('cct.syid', $syid)
                                    ->where('cct.paymentsetupdetail_id', $paymentsetupdetailId)
                                    ->where('cct.classid', $labFeeClassId)
                                    ->when($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25, function ($q) use ($semid) {
                                        $q->where(function ($subQ) use ($semid) {
                                            $subQ->where('cct.semid', $semid)->orWhereNull('cct.semid');
                                        });
                                    })
                                    ->where('ct.cancelled', 0)
                                    ->where('cct.deleted', 0)
                                    ->where('cti.deleted', 0)
                                    ->select('cct.particulars', 'cti.itemid', DB::raw('SUM(cti.amount) as item_paid'))
                                    ->groupBy('cct.particulars', 'cti.itemid')
                                    ->get();

                                // Filter payments by matching base particulars
                                foreach ($allPayments as $paymentItem) {
                                    $paymentParticularsBase = preg_replace('/\s*-\s*(DOWNPAYMENT|1ST\s+MONTH|2ND\s+MONTH|3RD\s+MONTH|4TH\s+MONTH|5TH\s+MONTH|6TH\s+MONTH|7TH\s+MONTH|8TH\s+MONTH|9TH\s+MONTH|10TH\s+MONTH|FINAL\s+PAYMENT|\d+(ST|ND|RD|TH)\s+PAYMENT|\d+(ST|ND|RD|TH)\s+MONTH|ONE\s+TIME\s+PAYMENT|NO\s+DUE\s+DATE|Due\s+\w+\s+\d+,\s+\d{4}|January|February|March|April|May|June|July|August|September|October|November|December)$/i', '', $paymentItem->particulars ?? '');
                                    $paymentParticularsBase = trim($paymentParticularsBase);

                                    // Debug logging for student 2
                                    if ($studid == 2) {
                                        \Log::info('[NESTED-ITEMS-PAYMENT-MATCH] Comparing particulars', [
                                            'fee_particulars' => $labFeeParticulars,
                                            'fee_particulars_base' => $labFeeParticularsBase,
                                            'payment_particulars' => $paymentItem->particulars ?? 'N/A',
                                            'payment_particulars_base' => $paymentParticularsBase,
                                            'match' => ($paymentParticularsBase === $labFeeParticularsBase) ? 'YES' : 'NO',
                                            'payment_itemid' => $paymentItem->itemid,
                                            'payment_amount' => $paymentItem->item_paid
                                        ]);
                                    }

                                    // Only include payments that match this laboratory fee's particulars
                                    if ($paymentParticularsBase === $labFeeParticularsBase) {
                                        $paidItemIds[] = $paymentItem->itemid;
                                        // Sum up payments for the same itemid if there are multiple
                                        if (!isset($paymentsByItemId[$paymentItem->itemid])) {
                                            $paymentsByItemId[$paymentItem->itemid] = 0;
                                        }
                                        $paymentsByItemId[$paymentItem->itemid] += (float) $paymentItem->item_paid;
                                    }
                                }
                            }
                        }

                        foreach ($allNestedItems as $nestedItem) {
                            if ($remainingQuota <= 0) {
                                break; // Breakdown amount is fully filled
                            }

                            $itemKey = $nestedItem['is_lab_fee'] ? 'LAB_FEE' : $nestedItem['itemid'];
                            $originalAmount = $nestedItem['amount'];

                            // Get remaining amount for this item
                            $itemRemaining = $enrolledNestedItemsRemaining[$key][$itemKey] ?? $originalAmount;

                            if ($itemRemaining <= 0) {
                                // This item is already exhausted, skip to next
                                continue;
                            }

                            // Fill as much as possible from this item
                            $amountToFill = min($itemRemaining, $remainingQuota);

                            // Use actual payment from chrngtransitems, not proportional distribution
                            $itemPayment = 0;
                            if (!empty($paidItemIds) && in_array($nestedItem['itemid'], $paidItemIds)) {
                                $itemPayment = $paymentsByItemId[$nestedItem['itemid']] ?? 0;
                            }
                            $itemBalance = $amountToFill - $itemPayment;

                            $itemsWithPayment[] = [
                                'itemid' => $nestedItem['itemid'],
                                'particulars' => $nestedItem['particulars'],
                                'amount' => round($amountToFill, 2),
                                'payment' => $itemPayment,
                                'balance' => $itemBalance,
                                'classid' => $labFeeClassId, // Use classid from labfeesetup (nested items use classid only)
                            ];

                            // Update remaining amounts
                            $enrolledNestedItemsRemaining[$key][$itemKey] -= $amountToFill;
                            $remainingQuota -= $amountToFill;
                            $totalDistributed += $amountToFill;
                        }

                        // Ensure nested items sum to exactly the breakdown amount
                        $difference = $breakdownAmount - $totalDistributed;
                        if (abs($difference) > 0.01) {
                            if (!empty($itemsWithPayment)) {
                                // Adjust the last nested item to make the sum exact
                                $lastIndex = count($itemsWithPayment) - 1;
                                $itemsWithPayment[$lastIndex]['amount'] = round($itemsWithPayment[$lastIndex]['amount'] + $difference, 2);

                                // For laboratory fees, get actual payment from chrngtransitems instead of proportional
                                $paymentsetupdetailId = $breakdownItem['paymentsetupdetail_id'] ?? null;
                                if ($breakdownPaid > 0 && $paymentsetupdetailId) {
                                    // Get the lab fee particulars to match against chrngcashtrans
                                    // Remove suffix to get base particulars for matching
                                    $labFeeParticulars = $fee['particulars'] ?? '';
                                    $labFeeParticularsBase = preg_replace('/\s*-\s*(DOWNPAYMENT|1ST\s+MONTH|2ND\s+MONTH|3RD\s+MONTH|4TH\s+MONTH|5TH\s+MONTH|6TH\s+MONTH|7TH\s+MONTH|8TH\s+MONTH|9TH\s+MONTH|10TH\s+MONTH|FINAL\s+PAYMENT|\d+(ST|ND|RD|TH)\s+PAYMENT|\d+(ST|ND|RD|TH)\s+MONTH|ONE\s+TIME\s+PAYMENT|NO\s+DUE\s+DATE|Due\s+\w+\s+\d+,\s+\d{4}|January|February|March|April|May|June|July|August|September|October|November|December)$/i', '', $labFeeParticulars);
                                    $labFeeParticularsBase = trim($labFeeParticularsBase);

                                    // Get all payments for this payment setup detail and classid
                                    $allPayments = DB::table('chrngtrans as ct')
                                        ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
                                        ->join('chrngtransitems as cti', 'ct.transno', '=', 'cti.chrngtransid')
                                        ->where('ct.studid', $studid)
                                        ->where('ct.syid', $syid)
                                        ->where('cct.syid', $syid)
                                        ->where('cct.paymentsetupdetail_id', $paymentsetupdetailId)
                                        ->where('cct.classid', $labFeeClassId)
                                        ->when($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25, function ($q) use ($semid) {
                                            $q->where(function ($subQ) use ($semid) {
                                                $subQ->where('cct.semid', $semid)->orWhereNull('cct.semid');
                                            });
                                        })
                                        ->where('ct.cancelled', 0)
                                        ->where('cct.deleted', 0)
                                        ->where('cti.deleted', 0)
                                        ->select('cct.particulars', 'cti.itemid', DB::raw('SUM(cti.amount) as item_paid'))
                                        ->groupBy('cct.particulars', 'cti.itemid')
                                        ->get();

                                    // Filter payments by matching base particulars
                                    $paymentsByItemId = [];
                                    foreach ($allPayments as $paymentItem) {
                                        $paymentParticularsBase = preg_replace('/\s*-\s*(DOWNPAYMENT|1ST\s+MONTH|2ND\s+MONTH|3RD\s+MONTH|4TH\s+MONTH|5TH\s+MONTH|6TH\s+MONTH|7TH\s+MONTH|8TH\s+MONTH|9TH\s+MONTH|10TH\s+MONTH|FINAL\s+PAYMENT|\d+(ST|ND|RD|TH)\s+PAYMENT|\d+(ST|ND|RD|TH)\s+MONTH|ONE\s+TIME\s+PAYMENT|NO\s+DUE\s+DATE|Due\s+\w+\s+\d+,\s+\d{4}|January|February|March|April|May|June|July|August|September|October|November|December)$/i', '', $paymentItem->particulars ?? '');
                                        $paymentParticularsBase = trim($paymentParticularsBase);

                                        // Debug logging for student 2
                                        if ($studid == 2) {
                                            \Log::info('[NESTED-ITEMS-ADJUSTMENT-MATCH] Comparing particulars', [
                                                'fee_particulars' => $labFeeParticulars,
                                                'fee_particulars_base' => $labFeeParticularsBase,
                                                'payment_particulars' => $paymentItem->particulars ?? 'N/A',
                                                'payment_particulars_base' => $paymentParticularsBase,
                                                'match' => ($paymentParticularsBase === $labFeeParticularsBase) ? 'YES' : 'NO',
                                                'payment_itemid' => $paymentItem->itemid,
                                                'payment_amount' => $paymentItem->item_paid
                                            ]);
                                        }

                                        // Only include payments that match this laboratory fee's particulars
                                        if ($paymentParticularsBase === $labFeeParticularsBase) {
                                            // Sum up payments for the same itemid if there are multiple
                                            if (!isset($paymentsByItemId[$paymentItem->itemid])) {
                                                $paymentsByItemId[$paymentItem->itemid] = 0;
                                            }
                                            $paymentsByItemId[$paymentItem->itemid] += (float) $paymentItem->item_paid;
                                        }
                                    }

                                    // Apply actual payments to adjusted item
                                    $adjustedItemId = $itemsWithPayment[$lastIndex]['itemid'];
                                    $adjustedAmount = $itemsWithPayment[$lastIndex]['amount'];
                                    $adjustedPayment = $paymentsByItemId[$adjustedItemId] ?? 0;
                                    $itemsWithPayment[$lastIndex]['payment'] = $adjustedPayment;
                                    $itemsWithPayment[$lastIndex]['balance'] = $adjustedAmount - $adjustedPayment;
                                } else {
                                    // No payment, use proportional calculation
                                    $breakdownPaymentRatio = $breakdownAmount > 0 ? ($breakdownPaid / $breakdownAmount) : 0;
                                    $breakdownBalanceRatio = $breakdownAmount > 0 ? ($breakdownBalance / $breakdownAmount) : 0;
                                    $adjustedAmount = $itemsWithPayment[$lastIndex]['amount'];
                                    $itemsWithPayment[$lastIndex]['payment'] = round($adjustedAmount * $breakdownPaymentRatio, 2);
                                    $itemsWithPayment[$lastIndex]['balance'] = round($adjustedAmount * $breakdownBalanceRatio, 2);
                                }

                                // Also adjust the remaining amount tracking
                                $lastItemId = $itemsWithPayment[$lastIndex]['itemid'];
                                if (isset($enrolledNestedItemsRemaining[$key][$lastItemId])) {
                                    $enrolledNestedItemsRemaining[$key][$lastItemId] -= $difference;
                                }
                            }
                        }

                        // Add laboratory fee items as nested items to this breakdown item
                        $breakdownItem['items'] = $itemsWithPayment;
                    }
                    unset($breakdownItem); // Break reference
                } elseif (isset($tuitionItemsByClassId[$classid])) {
                    // For tuition fees in school_fees container, each breakdown item should show its full amount
                    // as a nested item, independent of other breakdown items
                    foreach ($fee['items'] as &$breakdownItem) {
                        // Get the breakdown amount (this is what can be covered by this payment number)
                        $breakdownAmount = $breakdownItem['amount'] ?? 0;
                        $breakdownPaid = $breakdownItem['payment'] ?? 0;
                        $breakdownBalance = $breakdownItem['balance'] ?? 0;

                        // Sort tuition items by payment_priority_sequence (1, 2, 3...) to maintain order
                        $sortedTuitionItems = $tuitionItemsByClassId[$classid];
                        usort($sortedTuitionItems, function ($a, $b) {
                            $priorityA = $a['payment_priority_sequence'] ?? 9999;
                            $priorityB = $b['payment_priority_sequence'] ?? 9999;
                            if ($priorityA != $priorityB) {
                                return $priorityA <=> $priorityB;
                            }
                            // Fallback to createddatetime if priorities are equal
                            $dateA = $a['createddatetime'] ?? null;
                            $dateB = $b['createddatetime'] ?? null;
                            if ($dateA && $dateB) {
                                return strtotime($dateA) <=> strtotime($dateB);
                            }
                            if ($dateA)
                                return -1;
                            if ($dateB)
                                return 1;
                            return 0;
                        });

                        // Check if this breakdown item has cascaded payments from payment_details
                        // If so, we should preserve those values instead of recalculating from database
                        $psdId = $breakdownItem['paymentsetupdetail_id'] ?? null;
                        $hasCascadedPayments = false;
                        if ($psdId && isset($cascadedPaymentsByScheduleId[$psdId]) && $cascadedPaymentsByScheduleId[$psdId] > 0) {
                            $hasCascadedPayments = true;
                        } elseif (!empty($breakdownItem['payment_details'])) {
                            // Cascaded payments are in-memory only (not stored in DB)
                            $hasCascadedPayments = true;
                        }

                        // Recompute nested payments using exact chrngtransitems (paymentsetupdetail_id | classid | itemid)
                        // SKIP this recomputation if the item has cascaded payments, since those aren't in the database yet
                        $itemsWithPayment = [];
                        $exactTotalPaid = 0;
                        if (!$hasCascadedPayments) {
                            foreach ($sortedTuitionItems as $tuitionItem) {
                                $psdId = $breakdownItem['paymentsetupdetail_id'] ?? null;
                                $paidExact = 0;
                                $isLabBreakdown = $labFeeClassId && (($breakdownItem['classid'] ?? $classid) == $labFeeClassId);
                                $labParticularsBase = $isLabBreakdown
                                    ? preg_replace('/\\s*-\\s*(DOWNPAYMENT|1ST\\s+MONTH|2ND\\s+MONTH|3RD\\s+MONTH|4TH\\s+MONTH|5TH\\s+MONTH|6TH\\s+MONTH|7TH\\s+MONTH|8TH\\s+MONTH|9TH\\s+MONTH|10TH\\s+MONTH|FINAL\\s+PAYMENT|\\d+(ST|ND|RD|TH)\\s+PAYMENT|\\d+(ST|ND|RD|TH)\\s+MONTH|ONE\\s+TIME\\s+PAYMENT|NO\\s+DUE\\s+DATE|Due\\s+\\w+\\s+\\d+,\\s+\\d{4}|January|February|March|April|May|June|July|August|September|October|November|December).*/i', '', $breakdownItem['particulars'] ?? '')
                                    : '';
                                $labParticularsBase = trim($labParticularsBase ?? '');
                                if ($psdId !== null) {
                                    if ($isLabBreakdown) {
                                        $partKey = $psdId . '|' . $labFeeClassId . '|' . $tuitionItem['itemid'] . '|' . $labParticularsBase;

                                        // Debug for student 2
                                        if ($studid == 2) {
                                            \Log::info('[TUITION-ITEMS-LAB-MATCH] Looking up payment', [
                                                'breakdown_particulars' => $breakdownItem['particulars'] ?? 'N/A',
                                                'lab_particulars_base' => $labParticularsBase,
                                                'tuition_item_id' => $tuitionItem['itemid'],
                                                'part_key' => $partKey,
                                                'found_in_map' => isset($paymentsByPsdClassItemPart[$partKey]) ? 'YES' : 'NO'
                                            ]);
                                        }

                                        if (isset($paymentsByPsdClassItemPart[$partKey])) {
                                            $row = $paymentsByPsdClassItemPart[$partKey];
                                            $rowBase = trim(preg_replace('/\\s*-\\s*(DOWNPAYMENT|1ST\\s+MONTH|2ND\\s+MONTH|3RD\\s+MONTH|4TH\\s+MONTH|5TH\\s+MONTH|6TH\\s+MONTH|7TH\\s+MONTH|8TH\\s+MONTH|9TH\\s+MONTH|10TH\\s+MONTH|FINAL\\s+PAYMENT|\\d+(ST|ND|RD|TH)\\s+PAYMENT|\\d+(ST|ND|RD|TH)\\s+MONTH|ONE\\s+TIME\\s+PAYMENT|NO\\s+DUE\\s+DATE|Due\\s+\\w+\\s+\\d+,\\s+\\d{4}|January|February|March|April|May|June|July|August|September|October|November|December).*/i', '', $row->particulars ?? ''));
                                            // Match based on base particulars only (subject part)
                                            if ($rowBase === $labParticularsBase) {
                                                $paidExact = (float) $row->total_paid;

                                                if ($studid == 2) {
                                                    \Log::info('[TUITION-ITEMS-LAB-MATCH] Payment matched!', [
                                                        'row_particulars' => $row->particulars ?? 'N/A',
                                                        'row_base' => $rowBase,
                                                        'lab_base' => $labParticularsBase,
                                                        'paid_exact' => $paidExact
                                                    ]);
                                                }
                                            } else {
                                                if ($studid == 2) {
                                                    \Log::info('[TUITION-ITEMS-LAB-MATCH] Payment NOT matched', [
                                                        'row_particulars' => $row->particulars ?? 'N/A',
                                                        'row_base' => $rowBase,
                                                        'lab_base' => $labParticularsBase
                                                    ]);
                                                }
                                            }
                                        }
                                        // Do not fall back to class-only matching for laboratory fees to avoid cross-subject payments
                                    } else {
                                        $mapKey = $psdId . '|' . $classid . '|' . $tuitionItem['itemid'];
                                        if (isset($paymentsByPsdClassItem[$mapKey])) {
                                            $paidExact = (float) $paymentsByPsdClassItem[$mapKey]->total_paid;
                                        }
                                    }
                                } else {
                                    // Avoid class-only fallback for laboratory fees; only use fallback for non-lab items
                                    if (!$isLabBreakdown) {
                                        $fallbackKey = $classid . '|' . $tuitionItem['itemid'];
                                        if (isset($paymentsByClassItem[$fallbackKey])) {
                                            $paidExact = (float) $paymentsByClassItem[$fallbackKey]->total_paid;
                                        }
                                    }
                                }
                                $itemAmount = $tuitionItem['amount'];
                                $itemPayment = min($itemAmount, $paidExact);
                                $itemBalance = $itemAmount - $itemPayment;
                                $itemsWithPayment[] = [
                                    'itemid' => $tuitionItem['itemid'],
                                    'particulars' => $tuitionItem['particulars'],
                                    'amount' => round($itemAmount, 2),
                                    'payment' => round($itemPayment, 2),
                                    'balance' => round(max(0, $itemBalance), 2),
                                    'classid' => $tuitionItem['classid'],
                                ];
                                $exactTotalPaid += $itemPayment;
                            }
                        } else {
                            // Preserve existing items/payment when cascaded payments are present
                            $itemsWithPayment = $breakdownItem['items'] ?? [];
                            $exactTotalPaid = $breakdownPaid;
                        }

                        // If no exact payments were found but the breakdown shows paid amount (e.g., cascaded),
                        // distribute the paid amount proportionally across items to preserve the paid state.
                        if ($exactTotalPaid == 0 && $breakdownPaid > 0 && !empty($itemsWithPayment)) {
                            $distributed = 0;
                            $countItems = count($itemsWithPayment);
                            foreach ($itemsWithPayment as $idx => &$it) {
                                $proportion = ($breakdownAmount > 0) ? (($it['amount'] ?? 0) / $breakdownAmount) : 0;
                                $itmPay = ($idx === $countItems - 1)
                                    ? max(0, $breakdownPaid - $distributed) // put any rounding remainder on last item
                                    : min($it['amount'] ?? 0, round($breakdownPaid * $proportion, 2));
                                $distributed += $itmPay;
                                $itmBal = max(0, ($it['amount'] ?? 0) - $itmPay);
                                $it['payment'] = round($itmPay, 2);
                                $it['balance'] = round($itmBal, 2);
                            }
                            unset($it);
                            $exactTotalPaid = round($distributed, 2);
                        }

                        // Override parent payment/balance based on exact nested totals
                        $breakdownItem['items'] = $itemsWithPayment;
                        $breakdownItem['payment'] = round($exactTotalPaid, 2);
                        $breakdownItem['balance'] = round(max(0, $breakdownAmount - $exactTotalPaid), 2);
                    }
                    unset($breakdownItem); // Break reference
                }
            }
            unset($fee); // Break reference

        }

        // Combine regular fees and book entries
        // For laboratory fees, use a unique key that includes laboratory_fee_id to keep them separate
        // For item management items, use item_management_id to keep them separate
        // For regular fees, use classid to prevent duplicates
        $schoolFeesMap = [];

        foreach ($feesByParticular as $key => $fee) {
            // Cast classid to integer to ensure consistency (prevent string "9" vs int 9 duplicates)
            $classid = isset($fee['classid']) ? (int) $fee['classid'] : null;

            // Check if this is a laboratory fee - laboratory fees should NOT be merged even if they have the same classid
            $isLaboratoryFee = isset($fee['is_laboratory_fee']) && $fee['is_laboratory_fee'];
            $laboratoryFeeId = $fee['laboratory_fee_id'] ?? null;

            // Check if this is an item management item - should NOT be merged
            $isItemManagement = isset($fee['is_item_management']) && $fee['is_item_management'];
            $itemManagementId = $fee['item_management_id'] ?? null;

            // For laboratory fees, use a unique key that includes laboratory_fee_id
            // For item management items, use item_management_id directly
            // For regular fees, use classid as the key
            if ($isLaboratoryFee && $laboratoryFeeId !== null) {
                // Laboratory fee: use key that includes laboratory_fee_id to keep them separate
                $uniqueKey = 'LAB_FEE_' . $laboratoryFeeId;
            } elseif ($isItemManagement && $itemManagementId !== null) {
                // Item management: use item_management_id directly (raw ID from item_management table)
                $uniqueKey = $itemManagementId;
            } elseif ($classid !== null) {
                // Regular fee: use classid as key
                $uniqueKey = $classid;
            } else {
                // Fallback: use the original key from feesByParticular
                $uniqueKey = $key;
            }

            // Use unique key to prevent duplicates
            // If key already exists, merge items instead of replacing (only for regular fees, not laboratory fees or item management)
            if (!isset($schoolFeesMap[$uniqueKey])) {
                $schoolFeesMap[$uniqueKey] = [
                    'classid' => $classid,
                    'particulars' => $fee['particulars'],
                    'total_amount' => 0,
                    'total_paid' => 0,
                    'total_balance' => 0,
                    'items' => [],
                    'itemid' => $fee['itemid'] ?? null,
                    'laboratory_fee_id' => $laboratoryFeeId, // Preserve laboratory_fee_id
                    'is_laboratory_fee' => $isLaboratoryFee, // Preserve is_laboratory_fee flag
                    'item_management_id' => $itemManagementId, // Preserve item_management_id
                    'is_item_management' => $isItemManagement, // Preserve is_item_management flag
                ];
            }

            // For laboratory fees and item management, never merge - each should be separate
            // For regular fees, merge amounts and items
            if (($isLaboratoryFee && $laboratoryFeeId !== null) || ($isItemManagement && $itemManagementId !== null)) {
                // Laboratory fee or Item management: replace (shouldn't happen if grouping is correct, but handle it)
                $schoolFeesMap[$uniqueKey] = [
                    'classid' => $classid,
                    'particulars' => $fee['particulars'],
                    'total_amount' => round($fee['total_amount'] ?? 0, 2),
                    'total_paid' => round($fee['total_paid'] ?? 0, 2),
                    'total_balance' => round($fee['total_balance'] ?? 0, 2),
                    'items' => $fee['items'] ?? [],
                    'itemid' => $fee['itemid'] ?? null,
                    'laboratory_fee_id' => $laboratoryFeeId,
                    'is_laboratory_fee' => $isLaboratoryFee,
                    'item_management_id' => $itemManagementId,
                    'is_item_management' => $isItemManagement,
                ];
            } else {
                // Regular fee: merge amounts and items
                $schoolFeesMap[$uniqueKey]['total_amount'] += round($fee['total_amount'] ?? 0, 2);
                $schoolFeesMap[$uniqueKey]['total_paid'] += round($fee['total_paid'] ?? 0, 2);
                $schoolFeesMap[$uniqueKey]['total_balance'] += round($fee['total_balance'] ?? 0, 2);
                if (isset($fee['items']) && is_array($fee['items'])) {
                    $schoolFeesMap[$uniqueKey]['items'] = array_merge($schoolFeesMap[$uniqueKey]['items'], $fee['items']);
                }
            }
        }

        $schoolFees = array_values($schoolFeesMap);

        // Recompute totals from item-level amounts/payments to avoid class-level over-allocation
        foreach ($schoolFees as &$fee) {
            $isLabFeeGroup = isset($fee['is_laboratory_fee']) && $fee['is_laboratory_fee'];
            $totalAmount = 0;
            $totalPaid = 0;
            $totalBalance = 0;

            // For laboratory fees, keep the payment info coming from the schedule (already subject-specific)
            if ($isLabFeeGroup) {
                if (isset($fee['items']) && is_array($fee['items'])) {
                    foreach ($fee['items'] as &$itm) {
                        $totalAmount += $itm['amount'] ?? 0;
                        $totalPaid += $itm['payment'] ?? 0;
                        $totalBalance += $itm['balance'] ?? 0;
                    }
                    unset($itm);
                }

                $fee['total_amount'] = round($totalAmount, 2);
                $fee['total_paid'] = round($totalPaid, 2);
                $fee['total_balance'] = round($totalBalance, 2);
                continue; // Skip further recomputation for lab fees to avoid cross-subject payment mixing
            }

            if (isset($fee['items']) && is_array($fee['items'])) {
                foreach ($fee['items'] as &$itm) {
                    // Force nested payment allocation by exact itemid from chrngtransitems
                    if (isset($itm['items']) && is_array($itm['items'])) {
                        $psdId = $itm['paymentsetupdetail_id'] ?? null;
                        $parentPaid = 0;
                        foreach ($itm['items'] as &$sub) {
                            $paidExact = 0;
                            $itemIdKey = $sub['itemid'] ?? null;
                            $isLabBreakdown = $labFeeClassId && (($itm['classid'] ?? $fee['classid'] ?? '') == $labFeeClassId);
                            $labParticularsBase = $isLabBreakdown
                                ? preg_replace('/\\s*-\\s*(DOWNPAYMENT|1ST\\s+MONTH|2ND\\s+MONTH|3RD\\s+MONTH|4TH\\s+MONTH|5TH\\s+MONTH|6TH\\s+MONTH|7TH\\s+MONTH|8TH\\s+MONTH|9TH\\s+MONTH|10TH\\s+MONTH|FINAL\\s+PAYMENT|\\d+(ST|ND|RD|TH)\\s+PAYMENT|\\d+(ST|ND|RD|TH)\\s+MONTH|ONE\\s+TIME\\s+PAYMENT|NO\\s+DUE\\s+DATE|Due\\s+\\w+\\s+\\d+,\\s+\\d{4}|January|February|March|April|May|June|July|August|September|October|November|December).*/i', '', $itm['particulars'] ?? '')
                                : '';
                            $labParticularsBase = trim($labParticularsBase ?? '');
                            if ($psdId !== null) {
                                if ($isLabBreakdown) {
                                    $partKey = $psdId . '|' . $labFeeClassId . '|' . $itemIdKey . '|' . $labParticularsBase;
                                    if (isset($paymentsByPsdClassItemPart[$partKey])) {
                                        $row = $paymentsByPsdClassItemPart[$partKey];
                                        $rowBase = trim(preg_replace('/\\s*-\\s*(DOWNPAYMENT|1ST\\s+MONTH|2ND\\s+MONTH|3RD\\s+MONTH|4TH\\s+MONTH|5TH\\s+MONTH|6TH\\s+MONTH|7TH\\s+MONTH|8TH\\s+MONTH|9TH\\s+MONTH|10TH\\s+MONTH|FINAL\\s+PAYMENT|\\d+(ST|ND|RD|TH)\\s+PAYMENT|\\d+(ST|ND|RD|TH)\\s+MONTH|ONE\\s+TIME\\s+PAYMENT|NO\\s+DUE\\s+DATE|Due\\s+\\w+\\s+\\d+,\\s+\\d{4}|January|February|March|April|May|June|July|August|September|October|November|December).*/i', '', $row->particulars ?? ''));
                                        if ($rowBase === $labParticularsBase) {
                                            $paidExact = (float) $row->total_paid;
                                        }
                                    }
                                    // For laboratory fees, do not fall back to class-only map to avoid cross-subject payments
                                } else {
                                    $mapKey = $psdId . '|' . ($itm['classid'] ?? $fee['classid'] ?? '') . '|' . $itemIdKey;
                                    if (isset($paymentsByPsdClassItem[$mapKey])) {
                                        $paidExact = (float) $paymentsByPsdClassItem[$mapKey]->total_paid;
                                    }
                                }
                            } else {
                                // Avoid class-only fallback for laboratory fees; only use for non-lab items
                                if (!$isLabBreakdown) {
                                    $fallbackKey = ($itm['classid'] ?? $fee['classid'] ?? '') . '|' . $itemIdKey;
                                    if (isset($paymentsByClassItem[$fallbackKey])) {
                                        $paidExact = (float) $paymentsByClassItem[$fallbackKey]->total_paid;
                                    }
                                }
                            }

                            $subAmount = $sub['amount'] ?? 0;
                            $subPayment = min($subAmount, $paidExact);
                            $sub['payment'] = round($subPayment, 2);
                            $sub['balance'] = round(max(0, $subAmount - $subPayment), 2);
                            $parentPaid += $subPayment;
                        }
                        unset($sub);

                        // Update parent line based on nested exact totals
                        $itmAmount = $itm['amount'] ?? 0;
                        $itm['payment'] = round($parentPaid, 2);
                        $itm['balance'] = round(max(0, $itmAmount - $parentPaid), 2);
                    }

                    $totalAmount += $itm['amount'] ?? 0;
                    $totalPaid += $itm['payment'] ?? 0;
                    $totalBalance += $itm['balance'] ?? 0;
                }
                unset($itm);
            }

            $fee['total_amount'] = round($totalAmount, 2);
            $fee['total_paid'] = round($totalPaid, 2);
            $fee['total_balance'] = round($totalBalance, 2);
        }
        unset($fee);

        // Debug log schoolFees after merge
        \Log::info('SCHOOL-FEES-AFTER-MERGE', [
            'count' => count($schoolFees),
            'misc_count' => count(array_filter($schoolFees, function ($fee) {
                return isset($fee['classid']) && (int) $fee['classid'] == 9;
            })),
            'misc_fees' => array_filter($schoolFees, function ($fee) {
                return isset($fee['classid']) && (int) $fee['classid'] == 9;
            })
        ]);

        // Track remaining amounts for nested items in school fees (for sequential filling)
        $schoolFeesNestedItemsRemaining = []; // Key: classid => [itemid => remaining_amount]

        // Initialize remaining amounts for all classids in school fees
        foreach ($schoolFees as $schoolFee) {
            $classid = $schoolFee['classid'] ?? null;
            if ($classid && isset($tuitionItemsByClassId[$classid]) && !isset($schoolFeesNestedItemsRemaining[$classid])) {
                $schoolFeesNestedItemsRemaining[$classid] = [];
                foreach ($tuitionItemsByClassId[$classid] as $tuitionItem) {
                    $schoolFeesNestedItemsRemaining[$classid][$tuitionItem['itemid']] = $tuitionItem['amount'];
                }
            }
        }

        // Create a mapping of cascaded payments from dueDates by paymentsetupdetail_id
        // This will be used to add cascaded payments to the first nested item in school_fees
        $cascadedPaymentsByScheduleId = [];
        foreach ($dueDates as $scheduleItem) {
            $psdId = $scheduleItem['paymentsetupdetail_id'] ?? null;
            if ($psdId && isset($scheduleItem['payment_details']) && is_array($scheduleItem['payment_details']) && !empty($scheduleItem['payment_details'])) {
                // Calculate total cascaded payment from payment_details
                $totalCascaded = 0;
                foreach ($scheduleItem['payment_details'] as $paymentDetail) {
                    $totalCascaded += $paymentDetail['amount'] ?? 0;
                }
                if ($totalCascaded > 0) {
                    $cascadedPaymentsByScheduleId[$psdId] = $totalCascaded;
                }
            }
        }

        // Second pass: Add nested items to school fees (always recalculate to ensure correct amounts)
        if (isset($tuitionItemsByClassId) && !empty($tuitionItemsByClassId)) {
            \Log::info('[NESTED-ITEMS-DEBUG] tuitionItemsByClassId keys:', ['keys' => array_keys($tuitionItemsByClassId)]);
            $iteration = 0;
            foreach ($schoolFees as &$schoolFee) {
                $iteration++;
                // Do NOT attach tuitionitems to item management rows; they are standalone
                if (!empty($schoolFee['is_item_management'])) {
                    continue;
                }
                $classid = $schoolFee['classid'] ?? null;
                \Log::info('[NESTED-ITEMS-DEBUG] Processing school fee:', [
                    'classid' => $classid,
                    'particulars' => $schoolFee['particulars'] ?? null,
                    'has_tuition_items' => isset($tuitionItemsByClassId[$classid]) ? 'YES' : 'NO',
                    'has_items_array' => isset($schoolFee['items']) && is_array($schoolFee['items']) ? 'YES' : 'NO'
                ]);

                // Debug log each iteration
                \Log::info('SCHOOL-FEES-LOOP-ITERATION', [
                    'iteration' => $iteration,
                    'classid' => $classid,
                    'particulars' => $schoolFee['particulars'] ?? null,
                    'total_schoolfees_count' => count($schoolFees),
                ]);

                if ($classid && isset($tuitionItemsByClassId[$classid]) && isset($schoolFee['items']) && is_array($schoolFee['items'])) {
                    // For each breakdown item in school fees, add nested tuition items (always recalculate to ensure correct amounts)
                    foreach ($schoolFee['items'] as &$breakdownItem) {
                        // Always recalculate nested items to ensure they match breakdown amounts
                        // Don't skip - we need to fix nested items that might have incorrect amounts

                        // Get the breakdown amount
                        $breakdownAmount = $breakdownItem['amount'] ?? 0;
                        $breakdownPaid = $breakdownItem['payment'] ?? 0;
                        $breakdownBalance = $breakdownItem['balance'] ?? 0;

                        if ($studid == 2 && $classid == 5) {
                            \Log::info('LAB-FEE-DEBUG-BREAKDOWN: Processing breakdown item', [
                                'classid' => $classid,
                                'particulars' => $breakdownItem['particulars'] ?? null,
                                'amount' => $breakdownAmount,
                                'paid' => $breakdownPaid,
                                'balance' => $breakdownBalance,
                                'paymentsetupdetail_id' => $breakdownItem['paymentsetupdetail_id'] ?? 'NOT SET',
                                'laboratory_fee_id' => $breakdownItem['laboratory_fee_id'] ?? 'NOT SET',
                                'labFeeClassId' => $labFeeClassId,
                                'isLabFee' => ($labFeeClassId && $classid == $labFeeClassId)
                            ]);
                        }

                        // Sort tuition items by payment_priority_sequence (1, 2, 3...) to maintain order
                        $sortedTuitionItems = $tuitionItemsByClassId[$classid];
                        usort($sortedTuitionItems, function ($a, $b) {
                            $priorityA = $a['payment_priority_sequence'] ?? 9999;
                            $priorityB = $b['payment_priority_sequence'] ?? 9999;
                            if ($priorityA != $priorityB) {
                                return $priorityA <=> $priorityB;
                            }
                            // Fallback to createddatetime if priorities are equal
                            $dateA = $a['createddatetime'] ?? null;
                            $dateB = $b['createddatetime'] ?? null;
                            if ($dateA && $dateB) {
                                return strtotime($dateA) <=> strtotime($dateB);
                            }
                            if ($dateA)
                                return -1;
                            if ($dateB)
                                return 1;
                            return 0;
                        });

                        // Calculate payment and balance ratios for this breakdown
                        $breakdownPaymentRatio = $breakdownAmount > 0 ? ($breakdownPaid / $breakdownAmount) : 0;
                        $breakdownBalanceRatio = $breakdownAmount > 0 ? ($breakdownBalance / $breakdownAmount) : 0;

                        $itemsWithPayment = [];
                        $totalDistributed = 0;

                        // For school_fees container, use sequential priority-based filling for nested items
                        // If there's only 1 tuition item, use the full breakdown amount
                        if (count($sortedTuitionItems) === 1) {
                            $singleItem = $sortedTuitionItems[0];
                            $itemsWithPayment[] = [
                                'itemid' => $singleItem['itemid'],
                                'particulars' => $singleItem['particulars'],
                                'amount' => round($breakdownAmount, 2),
                                'payment' => round($breakdownPaid, 2),
                                'balance' => round($breakdownBalance, 2),
                                'classid' => $singleItem['classid'],
                            ];
                            $totalDistributed = $breakdownAmount;
                        } else {
                            // Multiple tuition items: use sequential priority-based filling
                            $remainingQuota = $breakdownAmount;

                            // Initialize remaining amounts for this classid if not set
                            if (!isset($schoolFeesNestedItemsRemaining[$classid])) {
                                $schoolFeesNestedItemsRemaining[$classid] = [];
                                foreach ($sortedTuitionItems as $tuitionItem) {
                                    $schoolFeesNestedItemsRemaining[$classid][$tuitionItem['itemid']] = $tuitionItem['amount'];
                                }
                            }

                            foreach ($sortedTuitionItems as $tuitionItem) {
                                if ($remainingQuota <= 0) {
                                    break; // Breakdown amount is fully filled
                                }

                                $itemid = $tuitionItem['itemid'];
                                $originalAmount = $tuitionItem['amount'];

                                // Initialize remaining amount if not set
                                if (!isset($schoolFeesNestedItemsRemaining[$classid][$itemid])) {
                                    $schoolFeesNestedItemsRemaining[$classid][$itemid] = $originalAmount;
                                }

                                // Get remaining amount for this item
                                $itemRemaining = $schoolFeesNestedItemsRemaining[$classid][$itemid];

                                if ($itemRemaining <= 0) {
                                    // This item is already exhausted, skip to next
                                    continue;
                                }

                                // Fill as much as possible from this item (but don't exceed remaining quota)
                                $amountToFill = min($itemRemaining, $remainingQuota);

                                // Only add nested item if amountToFill > 0
                                if ($amountToFill > 0) {
                                    $itemPayment = round($amountToFill * $breakdownPaymentRatio, 2);
                                    $itemBalance = round($amountToFill * $breakdownBalanceRatio, 2);

                                    $itemsWithPayment[] = [
                                        'itemid' => $itemid,
                                        'particulars' => $tuitionItem['particulars'],
                                        'amount' => round($amountToFill, 2),
                                        'payment' => $itemPayment,
                                        'balance' => $itemBalance,
                                        'classid' => $tuitionItem['classid'],
                                    ];

                                    // Update remaining amounts
                                    $schoolFeesNestedItemsRemaining[$classid][$itemid] -= $amountToFill;
                                    $remainingQuota -= $amountToFill;
                                    $totalDistributed += $amountToFill;
                                }
                            }

                            // Ensure nested items sum to exactly the breakdown amount
                            // If there's a difference (rounding or incomplete distribution), adjust accordingly
                            $difference = $breakdownAmount - $totalDistributed;
                            if (abs($difference) > 0.01) {
                                if (!empty($itemsWithPayment)) {
                                    // Adjust the last nested item to make the sum exact
                                    $lastIndex = count($itemsWithPayment) - 1;
                                    $itemsWithPayment[$lastIndex]['amount'] = round($itemsWithPayment[$lastIndex]['amount'] + $difference, 2);

                                    // Recalculate payment and balance for the adjusted amount
                                    $adjustedAmount = $itemsWithPayment[$lastIndex]['amount'];
                                    $itemsWithPayment[$lastIndex]['payment'] = round($adjustedAmount * $breakdownPaymentRatio, 2);
                                    $itemsWithPayment[$lastIndex]['balance'] = round($adjustedAmount * $breakdownBalanceRatio, 2);

                                    // Also adjust the remaining amount tracking
                                    $lastItemId = $itemsWithPayment[$lastIndex]['itemid'];
                                    if (isset($schoolFeesNestedItemsRemaining[$classid][$lastItemId])) {
                                        $schoolFeesNestedItemsRemaining[$classid][$lastItemId] -= $difference;
                                    }
                                } else {
                                    // No nested items were created (all items exhausted)
                                    // Fallback: create a single nested item with the breakdown amount
                                    $firstItem = $sortedTuitionItems[0];
                                    $itemsWithPayment[] = [
                                        'itemid' => $firstItem['itemid'],
                                        'particulars' => $firstItem['particulars'],
                                        'amount' => round($breakdownAmount, 2),
                                        'payment' => round($breakdownPaid, 2),
                                        'balance' => round($breakdownBalance, 2),
                                        'classid' => $firstItem['classid'],
                                    ];
                                }
                            }
                        }

                        // Add tuition items as nested items to this breakdown item
                        $breakdownItem['items'] = $itemsWithPayment;

                        // Check if there are cascaded payments for this breakdown item via paymentsetupdetail_id
                        // If so, add them to the first nested item's payment field
                        $breakdownPsdId = $breakdownItem['paymentsetupdetail_id'] ?? null;
                        if ($breakdownPsdId && isset($cascadedPaymentsByScheduleId[$breakdownPsdId]) && !empty($breakdownItem['items'])) {
                            $cascadedAmount = $cascadedPaymentsByScheduleId[$breakdownPsdId];

                            // Add the cascaded amount to the first nested item
                            $breakdownItem['items'][0]['payment'] = round(($breakdownItem['items'][0]['payment'] ?? 0) + $cascadedAmount, 2);
                            $breakdownItem['items'][0]['balance'] = round(max(0, ($breakdownItem['items'][0]['amount'] ?? 0) - $breakdownItem['items'][0]['payment']), 2);

                            // Recompute parent payment/balance to include cascaded amount
                            $nestedPaidTotal = 0;
                            foreach ($breakdownItem['items'] as $ni) {
                                $nestedPaidTotal += $ni['payment'] ?? 0;
                            }
                            $breakdownItem['payment'] = round($nestedPaidTotal, 2);
                            $breakdownItem['balance'] = round(max(0, ($breakdownItem['amount'] ?? 0) - $nestedPaidTotal), 2);

                            \Log::debug('[SCHOOL-FEES-CASCADED] Added cascaded payment to first nested item', [
                                'classid' => $classid,
                                'paymentsetupdetail_id' => $breakdownPsdId,
                                'cascaded_amount' => $cascadedAmount,
                                'first_nested_item_particulars' => $breakdownItem['items'][0]['particulars'] ?? null,
                                'first_nested_item_payment' => $breakdownItem['items'][0]['payment'],
                                'first_nested_item_balance' => $breakdownItem['items'][0]['balance']
                            ]);
                        }
                    }
                    unset($breakdownItem); // Break reference
                }
            }
            unset($schoolFee); // Break reference

            // Force laboratory fee payments to use schedule-calculated values (per subject and payment setup detail)
            if (!empty($labFeeSchedulePaid)) {
                // Debug for student 2
                if ($studid == 2) {
                    \Log::debug('[LAB-FEE-OVERRIDE] Starting lab fee payment override', [
                        'labFeeSchedulePaid' => $labFeeSchedulePaid,
                        'labFeeScheduleSummary' => $labFeeScheduleSummary,
                        'school_fees_count' => count($schoolFees)
                    ]);
                }

                foreach ($schoolFees as &$fee) {
                    if (!empty($fee['is_laboratory_fee']) && isset($fee['laboratory_fee_id'])) {
                        $labId = $fee['laboratory_fee_id'];
                        $totalAmount = 0;
                        $totalPaid = 0;
                        $totalBalance = 0;

                        if ($studid == 2) {
                            \Log::debug('[LAB-FEE-OVERRIDE] Processing lab fee', [
                                'laboratory_fee_id' => $labId,
                                'particulars' => $fee['particulars'] ?? 'N/A',
                                'items_count' => isset($fee['items']) ? count($fee['items']) : 0,
                                'before_total_paid' => $fee['total_paid'] ?? 0,
                                'before_total_balance' => $fee['total_balance'] ?? 0
                            ]);
                        }

                        if (isset($fee['items']) && is_array($fee['items'])) {
                            foreach ($fee['items'] as &$itm) {
                                $psdKey = $itm['paymentsetupdetail_id'] ?? 'none';
                                $scheduledPaid = $labFeeSchedulePaid[$labId][$psdKey] ?? 0;
                                $scheduledAmount = $labFeeScheduleSummary[$labId][$psdKey]['amount'] ?? ($itm['amount'] ?? 0);
                                $scheduledBalance = $labFeeScheduleSummary[$labId][$psdKey]['balance'] ?? max(0, $scheduledAmount - $scheduledPaid);

                                if ($studid == 2) {
                                    \Log::debug('[LAB-FEE-OVERRIDE] Processing item', [
                                        'particulars' => $itm['particulars'] ?? 'N/A',
                                        'paymentsetupdetail_id' => $psdKey,
                                        'scheduledPaid' => $scheduledPaid,
                                        'scheduledAmount' => $scheduledAmount,
                                        'scheduledBalance' => $scheduledBalance
                                    ]);
                                }

                                $itmAmount = $scheduledAmount;
                                $itmPayment = min($itmAmount, $scheduledPaid);
                                $itmBalance = max(0, $itmAmount - $itmPayment);

                                // Reset nested items to unpaid; they should mirror the subject-level payment
                                if (isset($itm['items']) && is_array($itm['items'])) {
                                    foreach ($itm['items'] as &$sub) {
                                        $subAmount = $sub['amount'] ?? 0;
                                        $sub['payment'] = 0;
                                        $sub['balance'] = round($subAmount, 2);
                                    }
                                    unset($sub);
                                }

                                $itm['amount'] = round($itmAmount, 2);   // force amount from schedule
                                $itm['payment'] = round($itmPayment, 2); // force payment from schedule
                                $itm['balance'] = round($itmBalance, 2); // force balance from schedule

                                $totalAmount += $itmAmount;
                                $totalPaid += $itmPayment;
                                $totalBalance += $itmBalance;
                            }
                            unset($itm);
                        }

                        $fee['total_amount'] = round($totalAmount, 2);
                        $fee['total_paid'] = round($totalPaid, 2);
                        $fee['total_balance'] = round($totalBalance, 2);

                        if ($studid == 2) {
                            \Log::debug('[LAB-FEE-OVERRIDE] After update', [
                                'laboratory_fee_id' => $labId,
                                'totalAmount' => $totalAmount,
                                'totalPaid' => $totalPaid,
                                'totalBalance' => $totalBalance,
                                'fee_total_paid' => $fee['total_paid'],
                                'fee_total_balance' => $fee['total_balance']
                            ]);
                        }
                    }
                }
                unset($fee);
            }

            // Ensure breakdown items in school_fees expose nested_items for frontend
            foreach ($schoolFees as &$sf) {
                if (isset($sf['items']) && is_array($sf['items'])) {
                    foreach ($sf['items'] as &$breakdown) {
                        if (!isset($breakdown['nested_items'])) {
                            $breakdown['nested_items'] = $breakdown['items'] ?? [];
                        }
                    }
                    unset($breakdown);
                }
            }
            unset($sf);
        }

        // Track remaining amounts for nested items in monthly assessments (for sequential filling)
        $monthlyAssessmentsNestedItemsRemaining = []; // Key: classid => [itemid => remaining_amount]

        // Initialize remaining amounts for all classids in monthly assessments
        foreach ($feesByParticular as $classid => $fee) {
            if (!isset($monthlyAssessmentsNestedItemsRemaining[$classid])) {
                $monthlyAssessmentsNestedItemsRemaining[$classid] = [];
                if (isset($tuitionItemsByClassId[$classid]) && !empty($tuitionItemsByClassId[$classid])) {
                    foreach ($tuitionItemsByClassId[$classid] as $tuitionItem) {
                        $monthlyAssessmentsNestedItemsRemaining[$classid][$tuitionItem['itemid']] = $tuitionItem['amount'];
                    }
                }
            }
        }

        // Build monthly assessments with status
        // Group by paymentsetupdetail_id (payment schedule installment) to ensure correct matching
        // This ensures TUITION breakdown items appear in their correct payment period (1st Term, 2nd Term, etc.)
        $currentDate = date('Y-m-d');
        $assessmentsByPaymentSetupDetail = [];

        foreach ($dueDates as $item) {
            // Skip book entries
            $classid = $item['classid'] ?? null;
            if (strpos($classid, 'BOOK_') === 0 || (isset($item['is_book_entry']) && $item['is_book_entry'])) {
                continue;
            }
            $itemManagementId = $item['item_management_id'] ?? null;
            $isItemManagement = !empty($itemManagementId) || (!empty($item['is_item_management']));

            // Prioritize paymentsetupdetail_id for grouping to ensure correct payment period matching
            // This ensures "TUITION | 15 Units - 1st Term" appears in "1st Term" monthly assessment
            $paymentsetupdetailId = $item['paymentsetupdetail_id'] ?? null;
            $duedate = $item['duedate'] ?? 'No Date';

            // Check if this is a laboratory fee - laboratory fees need separate grouping even if they share paymentsetupdetail_id
            $isLaboratoryFee = ($labFeeClassId && $classid == $labFeeClassId);
            $laboratoryFeeId = $item['laboratory_fee_id'] ?? null;

            // Use paymentsetupdetail_id as primary grouping key to match items to correct payment period
            // For laboratory fees, also include laboratory_fee_id to keep them separate
            // For item management, group by item_management_id to avoid nesting multiple charges together
            // Fallback to duedate only if paymentsetupdetail_id is null
            if ($isItemManagement && $itemManagementId) {
                $groupKey = 'im_' . $itemManagementId;
            } elseif ($paymentsetupdetailId !== null) {
                if ($isLaboratoryFee && $laboratoryFeeId !== null) {
                    // Laboratory fee: include laboratory_fee_id to keep each lab fee separate
                    $groupKey = 'psd_' . $paymentsetupdetailId . '_lab_' . $laboratoryFeeId;
                } else {
                    // Regular fee: use paymentsetupdetail_id only
                    $groupKey = 'psd_' . $paymentsetupdetailId; // Prefix to avoid conflicts with date strings
                }
            } else {
                if ($isLaboratoryFee && $laboratoryFeeId !== null) {
                    // Laboratory fee without paymentsetupdetail_id: include laboratory_fee_id
                    $groupKey = 'date_' . $duedate . '_lab_' . $laboratoryFeeId;
                } else {
                    // Regular fee: use duedate only
                    $groupKey = 'date_' . $duedate; // Prefix to avoid conflicts with paymentsetupdetail_id
                }
            }

            if (!isset($assessmentsByPaymentSetupDetail[$groupKey])) {
                $assessmentsByPaymentSetupDetail[$groupKey] = [
                    'paymentsetupdetail_id' => $paymentsetupdetailId,
                    'due_date' => $duedate,
                    'total_due' => 0,
                    'total_paid' => 0,
                    'balance' => 0,
                    'items' => []
                ];
            }

            // Use the same calculation as school fees: baseAmount (excluding adjustments)
            $originalAmount = $item['amount'] ?? 0;
            $adjustment = $item['adjustment'] ?? 0;
            $baseAmount = $originalAmount - $adjustment; // Exclude adjustments, same as school fees

            $assessmentsByPaymentSetupDetail[$groupKey]['total_due'] += $baseAmount;

            // Use the same calculation as school fees for payment and balance
            $itemPaid = $item['amountpay'] ?? 0;
            $itemBalance = $item['balance'] ?? max(0, $baseAmount - $itemPaid);

            // IMPORTANT: Save the parent breakdown balance before nested item loops
            // The variable $itemBalance will be reused inside nested item loops
            // and we need to preserve the parent's balance for the final breakdown item
            $parentBreakdownBalance = $itemBalance;

            $assessmentsByPaymentSetupDetail[$groupKey]['total_paid'] += $itemPaid;
            $assessmentsByPaymentSetupDetail[$groupKey]['balance'] += $itemBalance;
            // Format particulars for monthly assessments
            $itemParticulars = $item['particulars'] ?? 'Unknown';

            // For monthly assessment items with paymentno, get description from paymentsetupdetail
            if (isset($item['paymentno']) && $item['paymentno'] && isset($item['pschemeid']) && $item['pschemeid']) {
                $paymentDescription = DB::table('paymentsetupdetail')
                    ->where('paymentid', $item['pschemeid'])
                    ->where('paymentno', $item['paymentno'])
                    ->where('deleted', 0)
                    ->value('description');

                if ($paymentDescription) {
                    $itemParticulars = "{$itemParticulars} - {$paymentDescription}";
                }
            }

            // Get tuition items for this classid (if available from earlier fetch)
            // Only display items that can be covered by this breakdown amount, in priority order
            $breakdownTuitionItems = [];
            $itemClassId = $item['classid'] ?? null;

            // Check if this is a standalone adjustment (debit/credit adjustment)
            $isStandaloneAdjustment = isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment'];

            if ($isStandaloneAdjustment) {
                // For standalone adjustments, fetch the actual adjustment items (individual fee items)
                // These are stored in adjustmentitems table
                // Use sequential priority-based filling instead of proportional distribution
                $adjustmentDetailId = $item['adjustmentdetail_id'] ?? null;

                if ($adjustmentDetailId) {
                    // Fetch adjustment items from database
                    $adjustmentItems = DB::table('adjustmentitems as ai')
                        ->join('items as i', 'ai.itemid', '=', 'i.id')
                        ->select('i.description as particulars', 'ai.amount', 'ai.itemid', 'ai.id')
                        ->where('ai.detailid', $adjustmentDetailId)
                        ->where('ai.deleted', 0)
                        ->where('ai.amount', '>', 0)
                        ->orderBy('ai.id', 'asc') // Use ID for priority ordering
                        ->get();

                    // Convert to array format for easier manipulation
                    $sortedAdjustmentItems = [];
                    foreach ($adjustmentItems as $adjItem) {
                        $sortedAdjustmentItems[] = [
                            'itemid' => $adjItem->itemid,
                            'particulars' => $adjItem->particulars,
                            'amount' => (float) $adjItem->amount,
                        ];
                    }

                    // Initialize remaining amounts for this adjustment detail if not set
                    // Use a unique key that includes both adjustment detail ID and classid to avoid conflicts
                    $adjustmentKey = $adjustmentDetailId . '_' . ($item['classid'] ?? 'default');
                    if (!isset($adjustmentNestedItemsRemaining[$adjustmentKey])) {
                        $adjustmentNestedItemsRemaining[$adjustmentKey] = [];
                        foreach ($sortedAdjustmentItems as $adjItem) {
                            $adjustmentNestedItemsRemaining[$adjustmentKey][$adjItem['itemid']] = $adjItem['amount'];
                        }
                    }

                    $breakdownAmount = $item['amount'] ?? 0;
                    $breakdownPaid = $item['amountpay'] ?? 0;
                    $breakdownBalance = $item['balance'] ?? max(0, $breakdownAmount - $breakdownPaid);

                    // Special case: If there's only 1 adjustment item, use breakdown amount directly
                    if (count($sortedAdjustmentItems) === 1) {
                        $singleItem = $sortedAdjustmentItems[0];
                        $breakdownPaymentRatio = $breakdownAmount > 0 ? ($breakdownPaid / $breakdownAmount) : 0;
                        $breakdownBalanceRatio = $breakdownAmount > 0 ? ($breakdownBalance / $breakdownAmount) : 0;

                        $breakdownTuitionItems = [
                            [
                                'itemid' => $singleItem['itemid'],
                                'particulars' => $singleItem['particulars'],
                                'amount' => round($breakdownAmount, 2),
                                'payment' => round($breakdownAmount * $breakdownPaymentRatio, 2),
                                'balance' => round(max(0, $breakdownAmount * $breakdownBalanceRatio), 2),
                            ]
                        ];
                    } else {
                        // Multiple items: use sequential priority-based filling
                        $remainingQuota = $breakdownAmount;
                        $totalDistributed = 0;
                        $breakdownPaymentRatio = $breakdownAmount > 0 ? ($breakdownPaid / $breakdownAmount) : 0;
                        $breakdownBalanceRatio = $breakdownAmount > 0 ? ($breakdownBalance / $breakdownAmount) : 0;

                        // Get actual payments by item from chrngtransitems to avoid proportional distribution
                        // This ensures payment goes only to the items that were actually paid
                        $paidItemIds = [];
                        $paymentsByItemId = [];
                        if ($breakdownPaid > 0) {
                            $paymentsetupdetailId = $item['paymentsetupdetail_id'] ?? null;
                            if ($paymentsetupdetailId) {
                                // Get the particulars without payment schedule suffix (e.g., "MISCELLANEOUS - MIDTERM" -> "MISCELLANEOUS")
                                $baseParticulars = $itemParticulars;
                                if (strpos($baseParticulars, ' - ') !== false) {
                                    $parts = explode(' - ', $baseParticulars);
                                    $baseParticulars = $parts[0];
                                }

                                $paymentsForThisDueDate = DB::table('chrngtrans as ct')
                                    ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
                                    ->join('chrngtransitems as cti', 'ct.transno', '=', 'cti.chrngtransid')
                                    ->where('ct.studid', $studid)
                                    ->where('ct.syid', $syid)
                                    ->where('cct.syid', $syid)
                                    ->where('cct.paymentsetupdetail_id', $paymentsetupdetailId)
                                    ->where('cct.classid', $itemClassId)
                                    ->where('cct.particulars', $baseParticulars)
                                    ->when($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25, function ($q) use ($semid) {
                                        $q->where(function ($subQ) use ($semid) {
                                            $subQ->where('cct.semid', $semid)->orWhereNull('cct.semid');
                                        });
                                    })
                                    ->where('ct.cancelled', 0)
                                    ->where('cct.deleted', 0)
                                    ->where('cti.deleted', 0)
                                    ->select('cti.itemid', DB::raw('SUM(cti.amount) as item_paid'))
                                    ->groupBy('cti.itemid')
                                    ->get();

                                foreach ($paymentsForThisDueDate as $paymentItem) {
                                    $paidItemIds[] = $paymentItem->itemid;
                                    $paymentsByItemId[$paymentItem->itemid] = (float) $paymentItem->item_paid;
                                }
                            }
                        }

                        foreach ($sortedAdjustmentItems as $adjItem) {
                            if ($remainingQuota <= 0) {
                                break; // Breakdown amount is fully filled
                            }

                            $itemid = $adjItem['itemid'];
                            $originalItemAmount = $adjItem['amount'];

                            // Get remaining amount for this item
                            $itemRemaining = $adjustmentNestedItemsRemaining[$adjustmentKey][$itemid] ?? $originalItemAmount;

                            if ($itemRemaining <= 0) {
                                // This item is already exhausted, skip to next
                                continue;
                            }

                            // Fill as much as possible from this item
                            $amountToFill = min($itemRemaining, $remainingQuota);

                            // Use actual payment from chrngtransitems instead of proportional distribution
                            $itemPayment = 0;
                            if (!empty($paidItemIds) && in_array($itemid, $paidItemIds)) {
                                // This item was paid - use the actual payment amount
                                $itemPayment = $paymentsByItemId[$itemid] ?? 0;
                            }

                            $itemBalance = $amountToFill - $itemPayment;

                            $breakdownTuitionItems[] = [
                                'itemid' => $itemid,
                                'particulars' => $adjItem['particulars'],
                                'amount' => round($amountToFill, 2),
                                'payment' => $itemPayment,
                                'balance' => round(max(0, $itemBalance), 2),
                            ];

                            // Update remaining amounts
                            $adjustmentNestedItemsRemaining[$adjustmentKey][$itemid] -= $amountToFill;
                            $remainingQuota -= $amountToFill;
                            $totalDistributed += $amountToFill;
                        }

                        // Ensure nested items sum to exactly the breakdown amount
                        $difference = $breakdownAmount - $totalDistributed;
                        if (abs($difference) > 0.01 && !empty($breakdownTuitionItems)) {
                            // Adjust the last nested item to make the sum exact
                            $lastIndex = count($breakdownTuitionItems) - 1;
                            $breakdownTuitionItems[$lastIndex]['amount'] = round($breakdownTuitionItems[$lastIndex]['amount'] + $difference, 2);

                            // Recalculate balance for the adjusted amount
                            // Payment remains the same (actual payment from chrngtransitems)
                            $adjustedAmount = $breakdownTuitionItems[$lastIndex]['amount'];
                            $lastItemPayment = $breakdownTuitionItems[$lastIndex]['payment'];
                            $breakdownTuitionItems[$lastIndex]['balance'] = round(max(0, $adjustedAmount - $lastItemPayment), 2);

                            // Also adjust the remaining amount tracking
                            $lastItemId = $sortedAdjustmentItems[$lastIndex]['itemid'] ?? null;
                            if ($lastItemId && isset($adjustmentNestedItemsRemaining[$adjustmentKey][$lastItemId])) {
                                $adjustmentNestedItemsRemaining[$adjustmentKey][$lastItemId] -= $difference;
                            }
                        } else if (empty($breakdownTuitionItems) && !empty($sortedAdjustmentItems)) {
                            // All items exhausted - fallback to proportional distribution
                            $totalItemsAmount = array_sum(array_column($sortedAdjustmentItems, 'amount'));

                            if ($totalItemsAmount > 0) {
                                foreach ($sortedAdjustmentItems as $adjItem) {
                                    $proportion = $adjItem['amount'] / $totalItemsAmount;
                                    $proportionalAmount = $breakdownAmount * $proportion;

                                    $itemPayment = round($proportionalAmount * $breakdownPaymentRatio, 2);
                                    $itemBalance = round($proportionalAmount * $breakdownBalanceRatio, 2);

                                    $breakdownTuitionItems[] = [
                                        'itemid' => $adjItem['itemid'],
                                        'particulars' => $adjItem['particulars'],
                                        'amount' => round($proportionalAmount, 2),
                                        'payment' => $itemPayment,
                                        'balance' => round(max(0, $itemBalance), 2),
                                    ];
                                }

                                // Ensure the sum is exact (handle rounding)
                                $proportionalSum = array_sum(array_column($breakdownTuitionItems, 'amount'));
                                $finalDifference = $breakdownAmount - $proportionalSum;
                                if (abs($finalDifference) > 0.01 && !empty($breakdownTuitionItems)) {
                                    $lastIndex = count($breakdownTuitionItems) - 1;
                                    $breakdownTuitionItems[$lastIndex]['amount'] = round($breakdownTuitionItems[$lastIndex]['amount'] + $finalDifference, 2);
                                    $adjustedAmount = $breakdownTuitionItems[$lastIndex]['amount'];
                                    $breakdownTuitionItems[$lastIndex]['payment'] = round($adjustedAmount * $breakdownPaymentRatio, 2);
                                    $breakdownTuitionItems[$lastIndex]['balance'] = round($adjustedAmount * $breakdownBalanceRatio, 2);
                                }
                            }
                        }
                    }
                } else {
                    // Fallback: If no adjustmentdetail_id, show a single item with base particulars
                    $baseParticulars = $itemParticulars;
                    if (strpos($baseParticulars, ' - ') !== false) {
                        $parts = explode(' - ', $baseParticulars);
                        $baseParticulars = $parts[0];
                    }

                    $breakdownTuitionItems[] = [
                        'particulars' => $baseParticulars,
                        'amount' => round($item['amount'] ?? 0, 2),
                        'payment' => round($item['amountpay'] ?? 0, 2),
                        'balance' => round($item['balance'] ?? 0, 2),
                    ];
                }
            } elseif ($labFeeClassId && $itemClassId == $labFeeClassId) {
                // Laboratory fees - get nested items
                // Nested items should include: lab_amount (as "LAB FEE") + items from laboratory_fee_items
                $breakdownAmount = $baseAmount; // Use baseAmount, same as school fees
                $breakdownPaid = $itemPaid;
                $breakdownBalance = $itemBalance;

                // Get laboratory_fee_id from the item if available
                $laboratoryFeeId = $item['laboratory_fee_id'] ?? null;

                // Get lab_amount from labfees table
                $labAmount = 0;
                if ($laboratoryFeeId) {
                    $labFeeData = DB::table('labfees')
                        ->where('id', $laboratoryFeeId)
                        ->where('deleted', 0)
                        ->first();

                    $labAmount = $labFeeData ? (float) ($labFeeData->lab_amount ?? 0) : 0;
                }

                // Get laboratory fee items for this student
                $laboratoryFeeItems = DB::table('laboratory_fee_items as lfi')
                    ->leftJoin('labfees as lf', 'lfi.laboratory_fee_id', '=', 'lf.id')
                    ->leftJoin('items as i', 'lfi.item_id', '=', 'i.id')
                    ->leftJoin('college_prospectus as cp', 'lf.subjid', '=', 'cp.id')
                    ->select(
                        'lfi.id',
                        'lfi.laboratory_fee_id',
                        'lfi.item_id',
                        'lfi.amount',
                        'lf.subjid',
                        'lf.lab_amount',
                        'cp.subjCode as subjcode',
                        'cp.subjDesc as subjdesc',
                        'i.description as item_description'
                    )
                    ->where('lfi.deleted', 0)
                    ->where('lf.syid', $syid)
                    ->where('lf.semid', $semid);

                // Filter by laboratory_fee_id if available
                if ($laboratoryFeeId) {
                    $laboratoryFeeItems = $laboratoryFeeItems->where('lfi.laboratory_fee_id', $laboratoryFeeId);
                }

                $laboratoryFeeItems = $laboratoryFeeItems
                    ->orderBy('lfi.laboratory_fee_id')
                    ->orderBy('lfi.id')
                    ->get();

                // Build complete list of nested items: lab_amount first, then items
                $allNestedItems = [];

                // Add lab_amount as first nested item (if > 0)
                if ($labAmount > 0) {
                    $allNestedItems[] = [
                        'itemid' => $labFeeItemId, // Use itemid from labfeesetup for laboratory fees
                        'particulars' => 'LAB FEE',
                        'amount' => $labAmount,
                        'is_lab_fee' => true, // Flag to identify this as the lab fee itself
                    ];
                }

                // Group by laboratory_fee_id and add items
                $labFeeGroups = $laboratoryFeeItems->groupBy('laboratory_fee_id');
                foreach ($labFeeGroups as $labFeeId => $items) {
                    // If we don't have lab_amount from item, try to get it from the first item in this group
                    if ($labAmount == 0 && $items->first()) {
                        $firstItem = $items->first();
                        $labAmount = (float) ($firstItem->lab_amount ?? 0);
                        if ($labAmount > 0 && empty($allNestedItems)) {
                            $allNestedItems[] = [
                                'itemid' => $labFeeItemId, // Use itemid from labfeesetup for laboratory fees
                                'particulars' => $labFeeItemDescription, // Use description from items table (e.g., "LABORATORY FEE")
                                'amount' => $labAmount,
                                'is_lab_fee' => true,
                            ];
                        }
                    }

                    foreach ($items as $labItem) {
                        $allNestedItems[] = [
                            'itemid' => $labItem->item_id,
                            'particulars' => $labItem->item_description ?? 'Laboratory Fee Item',
                            'amount' => (float) $labItem->amount,
                            'laboratory_fee_id' => $labFeeId,
                            'is_lab_fee' => false,
                        ];
                    }
                }

                // Initialize remaining amounts for laboratory fees if not set
                if ($labFeeClassId && !isset($monthlyAssessmentsNestedItemsRemaining[$labFeeClassId])) {
                    $monthlyAssessmentsNestedItemsRemaining[$labFeeClassId] = [];

                    // Initialize lab_amount remaining
                    if ($labAmount > 0) {
                        $monthlyAssessmentsNestedItemsRemaining[$labFeeClassId]['LAB_FEE'] = $labAmount;
                    }

                    // Initialize item amounts remaining
                    foreach ($allNestedItems as $nestedItem) {
                        if (!$nestedItem['is_lab_fee'] && $nestedItem['itemid']) {
                            $monthlyAssessmentsNestedItemsRemaining[$labFeeClassId][$nestedItem['itemid']] = $nestedItem['amount'];
                        }
                    }
                }

                // Use sequential priority-based filling for nested items
                $remainingQuota = $breakdownAmount;
                $breakdownTuitionItems = [];
                $totalDistributed = 0;

                $paymentsetupdetailId = $item['paymentsetupdetail_id'] ?? null;
                $labFeeParticularsBase = preg_replace('/\\s*-\\s*(DOWNPAYMENT|1ST\\s+MONTH|2ND\\s+MONTH|3RD\\s+MONTH|4TH\\s+MONTH|5TH\\s+MONTH|6TH\\s+MONTH|7TH\\s+MONTH|8TH\\s+MONTH|9TH\\s+MONTH|10TH\\s+MONTH|FINAL\\s+PAYMENT|\\d+(ST|ND|RD|TH)\\s+PAYMENT|\\d+(ST|ND|RD|TH)\\s+MONTH|ONE\\s+TIME\\s+PAYMENT|NO\\s+DUE\\s+DATE|Due\\s+\\w+\\s+\\d+,\\s+\\d{4}|January|February|March|April|May|June|July|August|September|October|November|December).*/i', '', $itemParticulars ?? '');
                $labFeeParticularsBase = trim($labFeeParticularsBase ?? '');

                $breakdownPaymentRatio = $breakdownAmount > 0 ? ($breakdownPaid / $breakdownAmount) : 0;
                $breakdownBalanceRatio = $breakdownAmount > 0 ? ($breakdownBalance / $breakdownAmount) : 0;

                foreach ($allNestedItems as $nestedItem) {
                    if ($remainingQuota <= 0) {
                        break; // Breakdown amount is fully filled
                    }

                    $itemKey = $nestedItem['is_lab_fee'] ? 'LAB_FEE' : $nestedItem['itemid'];
                    $originalAmount = $nestedItem['amount'];

                    // Get remaining amount for this item
                    // Ensure the nested array structure exists
                    if (!isset($monthlyAssessmentsNestedItemsRemaining[$labFeeClassId])) {
                        $monthlyAssessmentsNestedItemsRemaining[$labFeeClassId] = [];
                    }

                    $itemRemaining = $monthlyAssessmentsNestedItemsRemaining[$labFeeClassId][$itemKey] ?? $originalAmount;

                    if ($itemRemaining <= 0) {
                        // This item is already exhausted, skip to next
                        continue;
                    }

                    // Fill as much as possible from this item
                    $amountToFill = min($itemRemaining, $remainingQuota);

                    // For laboratory fees, use actual payment from chrngtransitems instead of proportional
                    // This ensures payment goes only to the items that were actually paid
                    $currentItemId = $nestedItem['itemid'];
                    $itemPayment = 0;
                    if ($paymentsetupdetailId !== null) {
                        $partKey = $paymentsetupdetailId . '|' . $labFeeClassId . '|' . $currentItemId . '|' . $labFeeParticularsBase;
                        if (isset($paymentsByPsdClassItemPart[$partKey])) {
                            $row = $paymentsByPsdClassItemPart[$partKey];
                            $rowBase = trim(preg_replace('/\\s*-\\s*(DOWNPAYMENT|1ST\\s+MONTH|2ND\\s+MONTH|3RD\\s+MONTH|4TH\\s+MONTH|5TH\\s+MONTH|6TH\\s+MONTH|7TH\\s+MONTH|8TH\\s+MONTH|9TH\\s+MONTH|10TH\\s+MONTH|FINAL\\s+PAYMENT|\\d+(ST|ND|RD|TH)\\s+PAYMENT|\\d+(ST|ND|RD|TH)\\s+MONTH|ONE\\s+TIME\\s+PAYMENT|NO\\s+DUE\\s+DATE|Due\\s+\\w+\\s+\\d+,\\s+\\d{4}|January|February|March|April|May|June|July|August|September|October|November|December).*/i', '', $row->particulars ?? ''));
                            if ($rowBase === $labFeeParticularsBase && trim($row->particulars ?? '') === trim($itemParticulars ?? '')) {
                                $itemPayment = (float) $row->total_paid;
                            }
                        }
                    } else {
                        // Avoid class-only fallback for laboratory fees; PSD must be present to match correctly
                    }

                    $itemBalance = $amountToFill - $itemPayment;

                    $breakdownTuitionItems[] = [
                        'itemid' => $nestedItem['itemid'],
                        'particulars' => $nestedItem['particulars'],
                        'amount' => round($amountToFill, 2),
                        'payment' => round($itemPayment, 2),
                        'balance' => round(max(0, $itemBalance), 2),
                        'classid' => $labFeeClassId, // Use classid from labfeesetup (nested items use classid only)
                    ];

                    // Update remaining amounts - ensure the key exists before subtracting
                    if (!isset($monthlyAssessmentsNestedItemsRemaining[$labFeeClassId][$itemKey])) {
                        $monthlyAssessmentsNestedItemsRemaining[$labFeeClassId][$itemKey] = $originalAmount;
                    }
                    $monthlyAssessmentsNestedItemsRemaining[$labFeeClassId][$itemKey] -= $amountToFill;
                    $remainingQuota -= $amountToFill;
                    $totalDistributed += $amountToFill;
                }

                // Ensure nested items sum to exactly the breakdown amount
                $difference = $breakdownAmount - $totalDistributed;
                if (abs($difference) > 0.01 && !empty($breakdownTuitionItems)) {
                    // Adjust the last nested item to make the sum exact
                    $lastIndex = count($breakdownTuitionItems) - 1;
                    $breakdownTuitionItems[$lastIndex]['amount'] = round($breakdownTuitionItems[$lastIndex]['amount'] + $difference, 2);

                    // Recalculate balance for the adjusted amount
                    // Payment remains the same (actual payment from chrngtransitems)
                    $adjustedAmount = $breakdownTuitionItems[$lastIndex]['amount'];
                    $lastItemPayment = $breakdownTuitionItems[$lastIndex]['payment'];
                    $breakdownTuitionItems[$lastIndex]['balance'] = round(max(0, $adjustedAmount - $lastItemPayment), 2);

                    // Also adjust the remaining amount tracking
                    $lastItemKey = $allNestedItems[$lastIndex]['is_lab_fee'] ? 'LAB_FEE' : $allNestedItems[$lastIndex]['itemid'];
                    if (isset($monthlyAssessmentsNestedItemsRemaining[$labFeeClassId][$lastItemKey])) {
                        $monthlyAssessmentsNestedItemsRemaining[$labFeeClassId][$lastItemKey] -= $difference;
                    }
                } else if (empty($breakdownTuitionItems) && !empty($allNestedItems)) {
                    // All items exhausted - fallback to proportional distribution
                    $totalItemsAmount = array_sum(array_column($allNestedItems, 'amount'));

                    if ($totalItemsAmount > 0) {
                        foreach ($allNestedItems as $nestedItem) {
                            $proportion = $nestedItem['amount'] / $totalItemsAmount;
                            $proportionalAmount = $breakdownAmount * $proportion;

                            $itemPayment = round($proportionalAmount * $breakdownPaymentRatio, 2);
                            $itemBalance = round($proportionalAmount * $breakdownBalanceRatio, 2);

                            $breakdownTuitionItems[] = [
                                'itemid' => $nestedItem['itemid'],
                                'particulars' => $nestedItem['particulars'],
                                'amount' => round($proportionalAmount, 2),
                                'payment' => $itemPayment,
                                'balance' => round(max(0, $itemBalance), 2),
                                'classid' => $labFeeClassId, // Use classid from labfeesetup (nested items use classid only)
                            ];
                        }

                        // Ensure the sum is exact (handle rounding)
                        $proportionalSum = array_sum(array_column($breakdownTuitionItems, 'amount'));
                        $finalDifference = $breakdownAmount - $proportionalSum;
                        if (abs($finalDifference) > 0.01 && !empty($breakdownTuitionItems)) {
                            $lastIndex = count($breakdownTuitionItems) - 1;
                            $breakdownTuitionItems[$lastIndex]['amount'] = round($breakdownTuitionItems[$lastIndex]['amount'] + $finalDifference, 2);
                            $adjustedAmount = $breakdownTuitionItems[$lastIndex]['amount'];
                            $breakdownTuitionItems[$lastIndex]['payment'] = round($adjustedAmount * $breakdownPaymentRatio, 2);
                            $breakdownTuitionItems[$lastIndex]['balance'] = round($adjustedAmount * $breakdownBalanceRatio, 2);
                        }
                    }
                }
            } elseif ($itemClassId && isset($tuitionItemsByClassId[$itemClassId])) {
                // Skip tuition nesting for item management entries; they must stay flat
                if (!empty($item['is_item_management'])) {
                    continue;
                }
                // Regular tuition fees - get breakdown amount and balance
                // Use baseAmount (excluding adjustments) to match school fees calculation
                $breakdownAmount = $baseAmount; // Use baseAmount, same as school fees
                $breakdownPaid = $itemPaid;
                $breakdownBalance = $itemBalance;

                // Sort tuition items by payment_priority_sequence to match school_fees order
                $sortedTuitionItems = $tuitionItemsByClassId[$itemClassId];
                usort($sortedTuitionItems, function ($a, $b) {
                    $priorityA = $a['payment_priority_sequence'] ?? 9999;
                    $priorityB = $b['payment_priority_sequence'] ?? 9999;
                    if ($priorityA != $priorityB) {
                        return $priorityA <=> $priorityB;
                    }
                    // Fallback to createddatetime if priorities are equal
                    $dateA = $a['createddatetime'] ?? null;
                    $dateB = $b['createddatetime'] ?? null;
                    if ($dateA && $dateB) {
                        return strtotime($dateA) <=> strtotime($dateB);
                    }
                    if ($dateA)
                        return -1;
                    if ($dateB)
                        return 1;
                    return 0;
                });

                // Initialize remaining amounts for this classid if not set
                if (!isset($monthlyAssessmentsNestedItemsRemaining[$itemClassId])) {
                    $monthlyAssessmentsNestedItemsRemaining[$itemClassId] = [];
                    foreach ($sortedTuitionItems as $tuitionItem) {
                        $monthlyAssessmentsNestedItemsRemaining[$itemClassId][$tuitionItem['itemid']] = $tuitionItem['amount'];
                    }
                }

                // Use sequential priority-based filling to distribute nested items (same as school_fees)
                $breakdownTuitionItems = $this->distributeNestedItemsSequentially(
                    $sortedTuitionItems,
                    $breakdownAmount,
                    $monthlyAssessmentsNestedItemsRemaining[$itemClassId]
                );

                // Override parent items with the distributed nested items
                $item['items'] = $breakdownTuitionItems;
            }

            // Check if there are cascaded payments from the payment schedule
            // If so, add them to the first nested item's payment field
            $cascadedPaymentAmount = 0;
            if (isset($item['payment_details']) && is_array($item['payment_details']) && !empty($item['payment_details'])) {
                // Calculate total cascaded payment from payment_details
                foreach ($item['payment_details'] as $paymentDetail) {
                    $cascadedPaymentAmount += $paymentDetail['amount'] ?? 0;
                }

                // If there are nested items and cascaded payment, add the cascaded amount to the first nested item
                if ($cascadedPaymentAmount > 0 && !empty($breakdownTuitionItems)) {
                    $breakdownTuitionItems[0]['payment'] = round(($breakdownTuitionItems[0]['payment'] ?? 0) + $cascadedPaymentAmount, 2);
                    $breakdownTuitionItems[0]['balance'] = round(max(0, ($breakdownTuitionItems[0]['amount'] ?? 0) - $breakdownTuitionItems[0]['payment']), 2);
                }
            }

            // Use the same amount calculation as school fees (baseAmount, excluding adjustments)
            $assessmentsByPaymentSetupDetail[$groupKey]['items'][] = [
                'classid' => $itemClassId,
                'particulars' => $itemParticulars,
                'amount' => round($baseAmount, 2), // Use baseAmount, same as school fees
                'payment' => round($itemPaid, 2),
                'balance' => round($parentBreakdownBalance, 2), // Use saved parent balance, not $itemBalance which may be corrupted by nested loops
                'items' => $breakdownTuitionItems, // Add nested tuition items with payment info (now includes cascaded payments)
                'paymentsetupdetail_id' => $paymentsetupdetailId, // Add ID for frontend matching
                'id' => $item['tuitiondetail_id'] ?? $item['id'] ?? null, // Add tuitiondetail_id for payment processing
                'tuitiondetail_id' => $item['tuitiondetail_id'] ?? $item['id'] ?? null, // Add tuitiondetail_id for payment processing
                'itemid' => $item['itemid'] ?? null, // Add itemid for payment processing
                'laboratory_fee_id' => $item['laboratory_fee_id'] ?? null, // Add laboratory_fee_id for laboratory fees
                'item_management_id' => $itemManagementId,
                'is_item_management' => $isItemManagement
            ];
        }

        $monthlyAssessments = array_map(function ($assessment) use ($currentDate) {
            $duedate = $assessment['due_date'];

            // IMPORTANT: Recalculate balance from breakdown items to ensure accuracy
            // The balance from payment schedule calculation may be incorrect due to payment attribution issues
            // Sum up the balance from each breakdown item instead
            $recalculatedBalance = 0;
            $originalBalance = $assessment['balance'] ?? 0;
            if (isset($assessment['items']) && is_array($assessment['items'])) {
                foreach ($assessment['items'] as $breakdownItem) {
                    $recalculatedBalance += $breakdownItem['balance'] ?? 0;
                }
            }

            $balance = round($recalculatedBalance, 2);

            // Determine status
            $status = 'pending';
            if ($balance <= 0) {
                $status = 'paid';
            } elseif ($duedate && $duedate !== 'No Date' && $duedate < $currentDate && $balance > 0) {
                $status = 'overdue';
            }

            // Get description for the assessment group
            // First, try to get it from paymentsetupdetail_id if available
            $assessmentLabel = $duedate; // Default to due date
            $paymentsetupdetailId = $assessment['paymentsetupdetail_id'] ?? null;

            if ($paymentsetupdetailId) {
                // Get description directly from paymentsetupdetail table using the ID
                $paymentDescription = DB::table('paymentsetupdetail')
                    ->where('id', $paymentsetupdetailId)
                    ->where('deleted', 0)
                    ->value('description');

                if ($paymentDescription) {
                    $assessmentLabel = $paymentDescription;
                }
            }

            // If still no label, try to extract from breakdown items
            if ($assessmentLabel === $duedate && !empty($assessment['items'])) {
                // Try to extract description from the first breakdown item's particulars
                $firstItem = $assessment['items'][0];
                if (isset($firstItem['particulars']) && strpos($firstItem['particulars'], ' - ') !== false) {
                    // Extract description from format like "TUITION FEE | 20 Units - 1ST MONTH"
                    $parts = explode(' - ', $firstItem['particulars']);
                    if (count($parts) >= 2) {
                        $description = trim(end($parts));
                        $assessmentLabel = $description;
                    }
                }

                // If extraction didn't work, try the original method with paymentno/pschemeid
                if ($assessmentLabel === $duedate) {
                    foreach ($assessment['items'] as $itemIndex => $item) {
                        if (isset($item['paymentno']) && isset($item['pschemeid']) && $item['paymentno'] && $item['pschemeid']) {
                            $paymentDescription = DB::table('paymentsetupdetail')
                                ->where('paymentid', $item['pschemeid'])
                                ->where('paymentno', $item['paymentno'])
                                ->where('deleted', 0)
                                ->value('description');

                            if ($paymentDescription) {
                                $assessmentLabel = $paymentDescription;
                                break;
                            } else {
                                // Fallback: use payment number to create description
                                $paymentDescriptions = [
                                    1 => 'DOWNPAYMENT',
                                    2 => '1ST MONTH',
                                    3 => '2ND MONTH',
                                    4 => '3RD MONTH',
                                    5 => '4TH MONTH',
                                    6 => '5TH MONTH',
                                    7 => '6TH MONTH',
                                    8 => '7TH MONTH',
                                    9 => '8TH MONTH',
                                    10 => '9TH MONTH',
                                    11 => '10TH MONTH',
                                    12 => 'FINAL PAYMENT'
                                ];

                                $fallbackDescription = $paymentDescriptions[$item['paymentno']] ?? "PAYMENT {$item['paymentno']}";
                                $assessmentLabel = $fallbackDescription;
                                break;
                            }
                        }
                    }

                    // Recompute parent payment/balance for laboratory fee breakdown items from exact nested item payments
                    foreach ($assessment['items'] as $itemIndex => $item) {
                        if (isset($item['items']) && is_array($item['items']) && !empty($item['items'])) {
                            $breakdownTuitionItems = $item['items'];
                            $breakdownAmount = $item['amount'] ?? 0;
                            $exactPaid = array_sum(array_column($breakdownTuitionItems, 'payment'));
                            $itemPaid = round($exactPaid, 2);
                            $itemBalance = round(max(0, $breakdownAmount - $exactPaid), 2);

                            // Update the item's payment and balance based on nested items
                            $assessment['items'][$itemIndex]['payment'] = $itemPaid;
                            $assessment['items'][$itemIndex]['balance'] = $itemBalance;
                        }
                    }
                }

            }

            return [
                'paymentsetupdetail_id' => $assessment['paymentsetupdetail_id'] ?? null,
                'due_date' => $duedate,
                'assessment_label' => $assessmentLabel,
                'total_due' => round($assessment['total_due'], 2),
                'total_paid' => round($assessment['total_paid'], 2),
                'balance' => $balance,
                'status' => $status,
                'breakdown' => $assessment['items'],
            ];
        }, array_values($assessmentsByPaymentSetupDetail));

        // Ensure breakdown items expose nested_items for frontend
        foreach ($monthlyAssessments as &$ma) {
            if (isset($ma['breakdown']) && is_array($ma['breakdown'])) {
                foreach ($ma['breakdown'] as &$bd) {
                    if (!isset($bd['nested_items'])) {
                        $bd['nested_items'] = $bd['items'] ?? [];
                    }
                }
                unset($bd);
            }
        }
        unset($ma);

        // Promote item management IDs to monthly assessment entries for frontend use
        foreach ($monthlyAssessments as &$ma) {
            $foundMgmtId = null;
            if (!empty($ma['breakdown'])) {
                foreach ($ma['breakdown'] as $bd) {
                    if (!empty($bd['item_management_id'])) {
                        $foundMgmtId = $bd['item_management_id'];
                        break;
                    }
                }
            }
            if ($foundMgmtId) {
                $ma['item_management_id'] = $foundMgmtId;
                $ma['is_item_management'] = true;
            }
        }
        unset($ma);

        // Propagate item management identifiers to assessment headers for frontend ease-of-use
        foreach ($monthlyAssessments as &$assessment) {
            $foundItemMgmtId = null;
            if (isset($assessment['items']) && is_array($assessment['items'])) {
                foreach ($assessment['items'] as $bd) {
                    if (!empty($bd['item_management_id'])) {
                        $foundItemMgmtId = $bd['item_management_id'];
                        break;
                    }
                }
            }
            if (!$foundItemMgmtId && isset($assessment['breakdown']) && is_array($assessment['breakdown'])) {
                foreach ($assessment['breakdown'] as $bd) {
                    if (!empty($bd['item_management_id'])) {
                        $foundItemMgmtId = $bd['item_management_id'];
                        break;
                    }
                }
            }
            if ($foundItemMgmtId) {
                $assessment['item_management_id'] = $foundItemMgmtId;
                $assessment['is_item_management'] = true;
            }
        }
        unset($assessment);

        // Sync monthly assessment payments from school_fees when the schedule/class (and item) matches
        $schoolFeePsdMap = [];
        foreach ($schoolFees as $fee) {
            if (!isset($fee['items']) || !is_array($fee['items'])) continue;
            $feeClassid = $fee['classid'] ?? null;
            foreach ($fee['items'] as $itm) {
                $psd = $itm['paymentsetupdetail_id'] ?? null;
                $cid = $itm['classid'] ?? $feeClassid;
                $itmId = $itm['itemid'] ?? null;
                if ($psd !== null && $cid !== null) {
                    $key = $psd . '|' . $cid;
                    if (!isset($schoolFeePsdMap[$key])) {
                        $schoolFeePsdMap[$key] = [
                            'amount' => 0,
                            'payment' => 0,
                            'balance' => 0,
                            'items' => []
                        ];
                    }
                    $amountVal = $itm['amount'] ?? 0;
                    $paymentVal = $itm['payment'] ?? 0;
                    $balanceVal = $itm['balance'] ?? max(0, $amountVal - $paymentVal);
                    $schoolFeePsdMap[$key]['amount'] += $amountVal;
                    $schoolFeePsdMap[$key]['payment'] += $paymentVal;
                    $schoolFeePsdMap[$key]['balance'] += $balanceVal;

                    if ($itmId !== null) {
                        $itemKey = $psd . '|' . $cid . '|' . $itmId;
                        $schoolFeePsdMap[$itemKey] = [
                            'amount' => $amountVal,
                            'payment' => $paymentVal,
                            'balance' => $balanceVal,
                        ];
                    }
                }
            }
        }

        if (!empty($schoolFeePsdMap)) {
            foreach ($monthlyAssessments as &$ma) {
                if (!isset($ma['breakdown']) || !is_array($ma['breakdown'])) continue;
                $totalDue = 0;
                $totalPaid = 0;
                $totalBal = 0;
                foreach ($ma['breakdown'] as &$bd) {
                    $psd = $bd['paymentsetupdetail_id'] ?? null;
                    $cid = $bd['classid'] ?? null;
                    $itmId = $bd['itemid'] ?? null;
                    $keyItem = ($psd !== null && $cid !== null && $itmId !== null) ? ($psd . '|' . $cid . '|' . $itmId) : null;
                    $keyClass = ($psd !== null && $cid !== null) ? ($psd . '|' . $cid) : null;
                    $map = null;
                    if ($keyItem && isset($schoolFeePsdMap[$keyItem])) {
                        $map = $schoolFeePsdMap[$keyItem];
                    } elseif ($keyClass && isset($schoolFeePsdMap[$keyClass])) {
                        $map = $schoolFeePsdMap[$keyClass];
                    }
                    if ($map) {
                        $bd['amount'] = round($map['amount'] ?? ($bd['amount'] ?? 0), 2);
                        $bd['payment'] = round($map['payment'] ?? 0, 2);
                        $bd['balance'] = round($map['balance'] ?? max(0, ($bd['amount'] ?? 0) - ($bd['payment'] ?? 0)), 2);
                    }
                    $totalDue += $bd['amount'] ?? 0;
                    $totalPaid += $bd['payment'] ?? 0;
                    $totalBal += $bd['balance'] ?? 0;
                }
                unset($bd);
                // Update header totals
                $ma['total_due'] = round($totalDue, 2);
                $ma['total_paid'] = round($totalPaid, 2);
                $ma['balance'] = round($totalBal, 2);
                $ma['status'] = ($ma['balance'] ?? 0) > 0 ? 'pending' : 'paid';
            }
            unset($ma);
        }

        // Reconcile cascaded payments into school_fees using monthly assessments (which already include cascades)
        $maBreakdownMap = [];
        $maItemMgmtMap = [];
        foreach ($monthlyAssessments as $ma) {
            if (!isset($ma['breakdown']) || !is_array($ma['breakdown']))
                continue;
            foreach ($ma['breakdown'] as $bd) {
                $psd = $bd['paymentsetupdetail_id'] ?? 'none';
                $cid = $bd['classid'] ?? null;
                if ($cid !== null) {
                    $key = $psd . '|' . $cid;
                    $maBreakdownMap[$key] = [
                        'payment' => $bd['payment'] ?? 0,
                        'amount' => $bd['amount'] ?? 0,
                        'balance' => $bd['balance'] ?? 0,
                    ];
                }

                // Track item management breakdowns separately (No Date entries will have psd = 'none')
                $imId = $bd['item_management_id'] ?? null;
                if ($imId) {
                    if (!isset($maItemMgmtMap[$imId])) {
                        $maItemMgmtMap[$imId] = [
                            'payment' => 0,
                            'amount' => 0,
                            'balance' => 0,
                        ];
                    }
                    $maItemMgmtMap[$imId]['payment'] += $bd['payment'] ?? 0;
                    $maItemMgmtMap[$imId]['amount'] += $bd['amount'] ?? 0;
                    $maItemMgmtMap[$imId]['balance'] += $bd['balance'] ?? 0;
                }
            }
        }

        if (!empty($maBreakdownMap)) {
            foreach ($schoolFees as &$fee) {
                // For item management groups, keep the in-memory (cascaded) amounts; don't overwrite with assessment maps
                if (!empty($fee['is_item_management'])) {
                    $feeTotalAmount = 0;
                    $feeTotalPaid = 0;
                    $feeTotalBalance = 0;
                    if (isset($fee['items']) && is_array($fee['items'])) {
                        foreach ($fee['items'] as &$itm) {
                            $feeTotalAmount += $itm['amount'] ?? 0;
                            $feeTotalPaid += $itm['payment'] ?? 0;
                            $feeTotalBalance += $itm['balance'] ?? 0;
                        }
                        unset($itm);
                    }
                    $fee['total_amount'] = round($feeTotalAmount, 2);
                    $fee['total_paid'] = round($feeTotalPaid, 2);
                    $fee['total_balance'] = round($feeTotalBalance, 2);
                    continue;
                }

                // For laboratory fees, skip this recomputation - their totals were already set by the override logic above
                // This preserves the correct payment allocation from labFeeSchedulePaid
                if (!empty($fee['is_laboratory_fee'])) {
                    continue;
                }

                if (!isset($fee['items']) || !is_array($fee['items']))
                    continue;
                $feeTotalAmount = 0;
                $feeTotalPaid = 0;
                $feeTotalBalance = 0;

                foreach ($fee['items'] as &$itm) {
                    $psd = $itm['paymentsetupdetail_id'] ?? null;
                    $cid = $itm['classid'] ?? ($fee['classid'] ?? null);
                    $key = ($cid !== null) ? (($psd ?? 'none') . '|' . $cid) : null;
                    $imId = $itm['item_management_id'] ?? ($fee['item_management_id'] ?? null);

                    // Apply monthly assessment payment if available
                    if ($imId && isset($maItemMgmtMap[$imId])) {
                        // Fallback for item management entries without paymentsetupdetail_id
                        $mapPayment = $maItemMgmtMap[$imId]['payment'] ?? 0;
                        $mapAmount = $maItemMgmtMap[$imId]['amount'] ?? ($itm['amount'] ?? 0);
                        $itmAmount = $itm['amount'] ?? $mapAmount;
                        $itmPayment = min($itmAmount, $mapPayment);
                        $itmBalance = max(0, $itmAmount - $itmPayment);

                        $itm['amount'] = round($itmAmount, 2);
                        $itm['payment'] = round($itmPayment, 2);
                        $itm['balance'] = round($itmBalance, 2);
                    } elseif ($key && isset($maBreakdownMap[$key])) {
                        $mapPayment = $maBreakdownMap[$key]['payment'] ?? 0;
                        $mapAmount = $maBreakdownMap[$key]['amount'] ?? ($itm['amount'] ?? 0);

                        $itmAmount = $itm['amount'] ?? $mapAmount;
                        $itmPayment = min($itmAmount, $mapPayment);
                        $itmBalance = max(0, $itmAmount - $itmPayment);

                        $itm['payment'] = round($itmPayment, 2);
                        $itm['balance'] = round($itmBalance, 2);

                        // Distribute payment across nested items sequentially
                        if (isset($itm['items']) && is_array($itm['items']) && !empty($itm['items'])) {
                            $remaining = $itmPayment;
                            foreach ($itm['items'] as &$sub) {
                                $subAmount = $sub['amount'] ?? 0;
                                $pay = min($subAmount, $remaining);
                                $sub['payment'] = round($pay, 2);
                                $sub['balance'] = round(max(0, $subAmount - $pay), 2);
                                $remaining -= $pay;
                            }
                            unset($sub);
                        }
                    }

                    $feeTotalAmount += $itm['amount'] ?? 0;
                    $feeTotalPaid += $itm['payment'] ?? 0;
                    $feeTotalBalance += $itm['balance'] ?? 0;
                }
                unset($itm);

                // Update fee totals
                $fee['total_amount'] = round($feeTotalAmount, 2);
                $fee['total_paid'] = round($feeTotalPaid, 2);
                $fee['total_balance'] = round($feeTotalBalance, 2);
            }
            unset($fee);
        }

        // In-memory cascade: use surplus from other classifications to pay item management rows
        $surplusPool = 0;

        // Include non-cash overpayment (amounttendered - totalamount) as available surplus
        $cashPaymentTypeId = DB::table('paymenttype')->where('description', 'CASH')->value('id');
        $nonCashExcessTotal = DB::table('chrngtrans')
            ->select(DB::raw('SUM(GREATEST(amounttendered - totalamount, 0)) as excess'))
            ->where('studid', $studid)
            ->where('syid', $syid)
            ->when($studentInfo && $studentInfo->levelid >= 14, function ($q) use ($semid) {
                // For semester-based levels, allow semid match or NULL
                $q->where(function ($sub) use ($semid) {
                    $sub->where('semid', $semid)->orWhereNull('semid');
                });
            })
            ->where('cancelled', 0)
            ->where(function ($q) use ($cashPaymentTypeId) {
                $q->whereNull('paymenttype_id')
                    ->orWhere('paymenttype_id', '<>', $cashPaymentTypeId);
            })
            ->value('excess');
        if ($nonCashExcessTotal) {
            $surplusPool += (float) $nonCashExcessTotal;
        }
        foreach ($schoolFees as $fee) {
            if (!empty($fee['is_item_management'])) {
                continue;
            }
            $feeSurplus = max(0, ($fee['total_paid'] ?? 0) - ($fee['total_amount'] ?? 0));
            $surplusPool += $feeSurplus;
        }

        if ($surplusPool > 0) {
            foreach ($schoolFees as &$fee) {
                if (empty($fee['is_item_management'])) {
                    continue;
                }

                $balance = $fee['total_balance'] ?? 0;
                if ($balance <= 0) {
                    continue;
                }

                $apply = min($surplusPool, $balance);
                $surplusPool -= $apply;

                // Distribute to child items sequentially
                if (isset($fee['items']) && is_array($fee['items'])) {
                    $remaining = $apply;
                    foreach ($fee['items'] as &$itm) {
                        $itemBalance = $itm['balance'] ?? max(0, ($itm['amount'] ?? 0) - ($itm['payment'] ?? 0));
                        if ($itemBalance <= 0)
                            continue;

                        $pay = min($itemBalance, $remaining);
                        $itm['payment'] = round(($itm['payment'] ?? 0) + $pay, 2);
                        $itm['balance'] = round(max(0, $itemBalance - $pay), 2);
                        $remaining -= $pay;
                        if ($remaining <= 0)
                            break;
                    }
                    unset($itm);
                }

                $fee['total_paid'] = round(($fee['total_paid'] ?? 0) + $apply, 2);
                $fee['total_balance'] = round(max(0, ($fee['total_amount'] ?? 0) - $fee['total_paid']), 2);

                if ($surplusPool <= 0)
                    break;
            }
            unset($fee);
        }

        // Sync item management monthly assessments with updated school_fees totals
        $itemMgmtTotals = [];
        foreach ($schoolFees as $fee) {
            if (!empty($fee['is_item_management']) && !empty($fee['item_management_id'])) {
                $itemMgmtTotals[$fee['item_management_id']] = [
                    'total_amount' => $fee['total_amount'] ?? 0,
                    'total_paid' => $fee['total_paid'] ?? 0,
                    'total_balance' => $fee['total_balance'] ?? 0,
                ];
            }
        }
        if (!empty($itemMgmtTotals)) {
            foreach ($monthlyAssessments as &$ma) {
                $imId = $ma['item_management_id'] ?? null;
                if ($imId && isset($itemMgmtTotals[$imId])) {
                    $tot = $itemMgmtTotals[$imId];
                    $ma['total_due'] = round($tot['total_amount'], 2);
                    $ma['total_paid'] = round($tot['total_paid'], 2);
                    $ma['balance'] = round($tot['total_balance'], 2);
                    $ma['status'] = ($ma['balance'] ?? 0) > 0 ? 'pending' : 'paid';

                    if (isset($ma['breakdown']) && is_array($ma['breakdown'])) {
                        foreach ($ma['breakdown'] as &$bd) {
                            $bd['amount'] = $ma['total_due'];
                            $bd['payment'] = $ma['total_paid'];
                            $bd['balance'] = $ma['balance'];
                        }
                        unset($bd);
                    }
                }
            }
            unset($ma);
        }

        // Keep remaining surplus (if any) for adjustments cascading
        $remainingSurplus = $surplusPool;

        // Re-sync adjustment payments from schedule after cascading (in-memory only)
        $adjustmentUpdateMap = [];
        foreach ($dueDates as $schedItem) {
            $isAdj = (!empty($schedItem['is_adjustment']) || (!empty($schedItem['is_standalone_adjustment'])));
            if (!$isAdj)
                continue;
            $classid = $schedItem['classid'] ?? null;
            if ($classid === null)
                continue;

            $particulars = $schedItem['particulars'] ?? 'ADJUSTMENT';
            $baseParticulars = $particulars;
            if (strpos($particulars, ' - ') !== false) {
                $baseParticulars = substr($particulars, 0, strpos($particulars, ' - '));
            }
            $key = $classid . '::' . $baseParticulars;

            if (!isset($adjustmentUpdateMap[$key])) {
                $adjustmentUpdateMap[$key] = [
                    'type' => 'debit',
                    'classid' => $classid,
                    'particulars' => $baseParticulars,
                    'amount' => 0,
                    'paid' => 0,
                    'balance' => 0,
                    'items' => [],
                ];
            }

            $adjAmount = $schedItem['amount'] ?? 0;
            $adjPaid = $schedItem['amountpay'] ?? 0;
            $adjBalance = $schedItem['balance'] ?? max(0, $adjAmount - $adjPaid);

            // Apply remaining surplus to adjustment balances (in-memory cascade)
            if ($remainingSurplus > 0 && $adjBalance > 0) {
                $applyAdj = min($remainingSurplus, $adjBalance);
                $adjPaid += $applyAdj;
                $adjBalance = max(0, $adjBalance - $applyAdj);
                $remainingSurplus -= $applyAdj;

                // Distribute to nested items if present
                if (isset($schedItem['items']) && is_array($schedItem['items'])) {
                    $nestedRemaining = $applyAdj;
                    foreach ($schedItem['items'] as &$nItem) {
                        $nBalance = $nItem['balance'] ?? max(0, ($nItem['amount'] ?? 0) - ($nItem['payment'] ?? 0));
                        if ($nBalance <= 0)
                            continue;
                        $nPay = min($nBalance, $nestedRemaining);
                        $nItem['payment'] = round(($nItem['payment'] ?? 0) + $nPay, 2);
                        $nItem['balance'] = round(max(0, $nBalance - $nPay), 2);
                        $nestedRemaining -= $nPay;
                        if ($nestedRemaining <= 0)
                            break;
                    }
                    unset($nItem);
                }
            }

            $adjustmentUpdateMap[$key]['amount'] += $adjAmount;
            $adjustmentUpdateMap[$key]['paid'] += $adjPaid;
            $adjustmentUpdateMap[$key]['balance'] += $adjBalance;

            $adjustmentUpdateMap[$key]['items'][] = [
                'particulars' => $particulars,
                'amount' => $adjAmount,
                'payment' => $adjPaid,
                'balance' => $adjBalance,
                'paymentsetupdetail_id' => $schedItem['paymentsetupdetail_id'] ?? null,
                'items' => $schedItem['items'] ?? [],
            ];
        }

        if (!empty($adjustmentUpdateMap)) {
            $newAdjustments = [];

            // Update existing adjustments with refreshed paid/balance values
            foreach ($adjustments as $adj) {
                $key = ($adj['classid'] ?? '') . '::' . ($adj['particulars'] ?? '');
                if (isset($adjustmentUpdateMap[$key])) {
                    $update = $adjustmentUpdateMap[$key];
                    $adj['amount'] = round($update['amount'], 2);
                    $adj['paid'] = round($update['paid'], 2);
                    $adj['balance'] = round($update['balance'], 2);
                    $adj['items'] = $update['items'];
                    unset($adjustmentUpdateMap[$key]); // mark as consumed
                }
                $newAdjustments[] = $adj;
            }

            // Add any adjustments that weren't in the original list
            foreach ($adjustmentUpdateMap as $update) {
                $update['amount'] = round($update['amount'], 2);
                $update['paid'] = round($update['paid'], 2);
                $update['balance'] = round($update['balance'], 2);
                $newAdjustments[] = $update;
            }

            $adjustments = $newAdjustments;
        }

        // Apply any remaining surplus to debit adjustments (in-memory, sequential)
        if (!empty($adjustments) && isset($remainingSurplus) && $remainingSurplus > 0) {
            foreach ($adjustments as &$adj) {
                if (($adj['type'] ?? '') !== 'debit') {
                    continue;
                }
                $adjBalance = $adj['balance'] ?? 0;
                if ($adjBalance <= 0) {
                    continue;
                }

                $apply = min($remainingSurplus, $adjBalance);
                $remainingSurplus -= $apply;

                // Distribute to nested items if present
                if (isset($adj['items']) && is_array($adj['items']) && !empty($adj['items'])) {
                    $nestedRemaining = $apply;
                    foreach ($adj['items'] as &$ai) {
                        $aiBal = $ai['balance'] ?? max(0, ($ai['amount'] ?? 0) - ($ai['payment'] ?? 0));
                        if ($aiBal <= 0)
                            continue;
                        $pay = min($aiBal, $nestedRemaining);
                        $ai['payment'] = round(($ai['payment'] ?? 0) + $pay, 2);
                        $ai['balance'] = round(max(0, $aiBal - $pay), 2);
                        $nestedRemaining -= $pay;
                        if ($nestedRemaining <= 0)
                            break;
                    }
                    unset($ai);
                    $applyUsed = $apply - $nestedRemaining;
                } else {
                    $applyUsed = $apply;
                }

                $adj['paid'] = round(($adj['paid'] ?? 0) + $applyUsed, 2);
                $adj['balance'] = round(max(0, $adjBalance - $applyUsed), 2);

                if ($remainingSurplus <= 0) {
                    break;
                }
            }
            unset($adj);
        }

        // Add forwarded old accounts to school_fees
        $forwardedOldAccounts = self::getForwardedOldAccountsData($studid, $syid, $semid);

        // // Merge forwarded old accounts into school_fees
        // if (!empty($forwardedOldAccounts)) {
        //     $schoolFees = array_merge($schoolFees, $forwardedOldAccounts);
        // }

        // Debug log final schoolFees before return
        \Log::info('SCHOOL-FEES-BEFORE-RETURN', [
            'count' => count($schoolFees),
            'misc_count' => count(array_filter($schoolFees, function ($fee) {
                return isset($fee['classid']) && (int) $fee['classid'] == 9;
            })),
            'classids' => array_column($schoolFees, 'classid')
        ]);

        // Build a quick lookup for one-time schedule totals (e.g., misc fees) from payment schedules
        $oneTimeScheduleTotals = [];
        foreach ($dueDates as $dd) {
            if (!empty($dd['is_one_time']) && isset($dd['classid'])) {
                $cid = $dd['classid'];
                if (!isset($oneTimeScheduleTotals[$cid])) {
                    $oneTimeScheduleTotals[$cid] = ['amount' => 0, 'paid' => 0, 'balance' => 0];
                }
                $oneTimeScheduleTotals[$cid]['amount'] += $dd['amount'] ?? 0;
                $oneTimeScheduleTotals[$cid]['paid'] += $dd['amountpay'] ?? 0;
                // Prefer explicit balance from schedule; otherwise derive
                $oneTimeScheduleTotals[$cid]['balance'] += $dd['balance'] ?? max(0, ($dd['amount'] ?? 0) - ($dd['amountpay'] ?? 0));
            }
        }

        // Final pass: enforce itemid-based payments on school_fees output (no proportional splits)
        $psdMap = isset($paymentsByPsdClassItem) ? $paymentsByPsdClassItem : collect();
        $classMap = isset($paymentsByClassItem) ? $paymentsByClassItem : collect();
        foreach ($schoolFees as &$fee) {
            // Keep laboratory fee payments as forced from the schedule; do not remap them here
            if (!empty($fee['is_laboratory_fee'])) {
                continue;
            }
            // If we have an exact one-time schedule total (e.g., misc), respect it and skip remap
            if (isset($fee['classid']) && isset($oneTimeScheduleTotals[$fee['classid']])) {
                $tot = $oneTimeScheduleTotals[$fee['classid']];
                $fee['total_amount'] = round($tot['amount'], 2);
                $fee['total_paid'] = round(min($tot['amount'], $tot['paid']), 2);
                $fee['total_balance'] = round(max(0, $fee['total_amount'] - $fee['total_paid']), 2);
                // Align the first parent item to reflect the schedule totals for display
                if (!empty($fee['items']) && is_array($fee['items'])) {
                    $fee['items'][0]['payment'] = $fee['total_paid'];
                    $fee['items'][0]['balance'] = $fee['total_balance'];
                }
                continue;
            }
            // Preserve precomputed misc fee payments/balances (these already match the schedule)
            if (($fee['classid'] ?? null) == 2 && isset($fee['particulars']) && stripos($fee['particulars'], 'misc') !== false) {
                continue;
            }
            if (!isset($fee['items']) || !is_array($fee['items']))
                continue;
            $totalAmount = 0;
            $totalPaid = 0;
            $totalBal = 0;
            foreach ($fee['items'] as &$itm) {
                $parentAmt = $itm['amount'] ?? 0;
                $parentPaid = 0;
                $existingParentPaid = $itm['payment'] ?? 0;

                // If parent already has a computed payment/balance (from schedule/cascade), keep it
                if ($existingParentPaid > 0 || isset($itm['balance'])) {
                    $totalAmount += $parentAmt;
                    $totalPaid += $existingParentPaid;
                    $totalBal += $itm['balance'] ?? max(0, $parentAmt - $existingParentPaid);
                    continue;
                }

                if (isset($itm['items']) && is_array($itm['items'])) {
                    foreach ($itm['items'] as &$sub) {
                        $psdId = $itm['paymentsetupdetail_id'] ?? null;
                        $mapKey = ($psdId ?? 'none') . '|' . ($itm['classid'] ?? $fee['classid'] ?? '') . '|' . ($sub['itemid'] ?? '');
                        $fallbackKey = ($itm['classid'] ?? $fee['classid'] ?? '') . '|' . ($sub['itemid'] ?? '');
                        $paidExact = 0;
                        if ($psdId !== null && $psdMap->has($mapKey)) {
                            $paidExact = (float) $psdMap[$mapKey]->total_paid;
                        } elseif ($psdId === null && $classMap->has($fallbackKey)) {
                            $paidExact = (float) $classMap[$fallbackKey]->total_paid;
                        }
                        $subAmt = $sub['amount'] ?? 0;
                        // Preserve previously computed payments (e.g., cascaded) when there is no exact DB match
                        $existingSubPay = $sub['payment'] ?? 0;
                        if ($paidExact > 0) {
                            // Prefer exact DB payment; cap at amount
                            $subPay = min($subAmt, $paidExact);
                        } else {
                            $subPay = min($subAmt, $existingSubPay);
                        }
                        $sub['payment'] = round($subPay, 2);
                        $sub['balance'] = round(max(0, $subAmt - $subPay), 2);
                        $parentPaid += $subPay;
                    }
                    unset($sub);
                    // Parent gets the sum of its children only; avoid double counting cascaded amounts
                    $itm['payment'] = round($parentPaid, 2);
                    $itm['balance'] = round(max(0, $parentAmt - $parentPaid), 2);
                } else {
                    // No nested items: keep existing payment/balance if already set (e.g., cascaded)
                    $itm['payment'] = round($existingParentPaid, 2);
                    $itm['balance'] = round(max(0, $parentAmt - $existingParentPaid), 2);
                }
                $totalAmount += $itm['amount'] ?? 0;
                $totalPaid += $itm['payment'] ?? 0;
                $totalBal += $itm['balance'] ?? 0;
            }
            unset($itm);
            $fee['total_amount'] = round($totalAmount, 2);
            $fee['total_paid'] = round($totalPaid, 2);
            $fee['total_balance'] = round($totalBal, 2);
        }
        unset($fee);

        // Add item management charges to school_fees (no payment schedule, only total amounts)
        $isBasicLevel = ($studentInfo && (($studentInfo->levelid >= 1 && $studentInfo->levelid <= 13) || $studentInfo->levelid == 16));

        $itemManagementCharges = DB::table('item_management as im')
            ->leftJoin('item_management_setup as ims', function ($join) {
                $join->on('im.classid', '=', 'ims.classid')
                    ->on('im.itemid', '=', 'ims.itemid')
                    ->where('ims.deleted', '=', 0);
            })
            ->leftJoin('itemclassification as ic', 'im.classid', '=', 'ic.id')
            ->leftJoin('items as i', 'im.itemid', '=', 'i.id')
            ->select(
                'im.id as item_management_id',
                'im.classid',
                'im.itemid',
                'im.amount',
                'im.isAidPlan',
                'ic.description as class_description',
                'i.description as item_description'
            )
            ->distinct() // Add distinct to avoid duplicates from item_management_setup join
            ->where('im.studid', $studid)
            ->where('im.syid', $syid)
            ->where('im.deleted', 0);

        // Apply semester filter only for non-basic level students
        if (!is_null($semid) && !$isBasicLevel) {
            $itemManagementCharges->where('im.semid', $semid);
        }

        $itemManagementCharges = $itemManagementCharges->get();

        // Group item management by item_management_id (not classid) to keep each item separate
        $itemMgmtByClass = [];
        foreach ($itemManagementCharges as $itemCharge) {
            if ($itemCharge->amount > 0) {
                $classid = $itemCharge->classid;
                $itemMgmtId = $itemCharge->item_management_id;

                // Determine classification name based on isAidPlan flag
                $particulars = 'OTHER ITEMS';
                if ($itemCharge->isAidPlan == 1) {
                    $particulars = 'AID PLAN';
                }

                // Use item_management_id as the key to keep each item separate
                if (!isset($itemMgmtByClass[$itemMgmtId])) {
                    $itemMgmtByClass[$itemMgmtId] = [
                        'classid' => $classid,
                        'particulars' => $particulars,
                        'total_amount' => 0,
                        'total_paid' => 0,
                        'total_balance' => 0,
                        'items' => [],
                        'itemid' => $itemCharge->itemid,
                        'item_management_id' => $itemMgmtId,
                        'is_item_management' => true
                    ];
                }

                // Get payments for this item management charge
                // Use item_management_id from chrngcashtrans for precise matching
                $itemPayments = DB::table('chrngtrans as ct')
                    ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
                    ->where('ct.studid', $studid)
                    ->where('ct.syid', $syid)
                    ->where('cct.syid', $syid)
                    ->where('cct.item_management_id', $itemMgmtId)
                    ->when($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25, function ($q) use ($semid) {
                        $q->where(function ($subQ) use ($semid) {
                            $subQ->where('cct.semid', $semid)->orWhereNull('cct.semid');
                        });
                    })
                    ->where('ct.cancelled', 0)
                    ->where('cct.deleted', 0)
                    ->sum('cct.amount');

                $itemPayments = (float) $itemPayments;
                $itemBalance = max(0, (float) $itemCharge->amount - $itemPayments);

                // Add as a breakdown item (no nested items for item management)
                $itemDescription = $itemCharge->item_description ?: 'Item Management';
                $itemMgmtByClass[$itemMgmtId]['items'][] = [
                    'particulars' => $itemDescription,
                    'amount' => (float) $itemCharge->amount,
                    'payment' => $itemPayments,
                    'balance' => $itemBalance,
                    'classid' => $classid,
                    'itemid' => $itemCharge->itemid,
                    'paymentsetupdetail_id' => null,
                    'items' => [], // No nested items
                    'item_management_id' => $itemMgmtId,
                    'is_item_management' => true
                ];

                // Accumulate totals
                $itemMgmtByClass[$itemMgmtId]['total_amount'] += (float) $itemCharge->amount;
                $itemMgmtByClass[$itemMgmtId]['total_paid'] += $itemPayments;
                $itemMgmtByClass[$itemMgmtId]['total_balance'] += $itemBalance;
            }
        }

        // Enrich monthly assessments with item management identifiers so frontend can tag payments correctly
        if (!empty($itemMgmtByClass) && !empty($monthlyAssessments)) {
            $normalizeParticulars = function ($text) {
                $text = $text ?? '';
                // Strip common suffixes like "- NO DUE DATE" for consistent matching
                $text = preg_replace('/\\s*-\\s*NO\\s+DUE\\s+DATE\\s*$/i', '', $text);
                return strtolower(trim($text));
            };

            $itemMgmtLookup = [];
            foreach ($itemMgmtByClass as $mgmt) {
                $cls = $mgmt['classid'] ?? null;
                $partKey = $normalizeParticulars($mgmt['particulars'] ?? '');
                if ($cls !== null && $partKey !== '') {
                    $itemMgmtLookup[$cls][$partKey] = $mgmt['item_management_id'];
                }
            }

            foreach ($monthlyAssessments as &$assessment) {
                if (!isset($assessment['items']) || !is_array($assessment['items'])) {
                    continue;
                }
                foreach ($assessment['items'] as &$bd) {
                    $cls = $bd['classid'] ?? null;
                    $bdPartKey = $normalizeParticulars($bd['particulars'] ?? '');
                    if ($cls !== null && isset($itemMgmtLookup[$cls])) {
                        if (isset($itemMgmtLookup[$cls][$bdPartKey])) {
                            $bd['item_management_id'] = $itemMgmtLookup[$cls][$bdPartKey];
                            $bd['is_item_management'] = true;
                        }
                    }
                }
                unset($bd);
            }
            unset($assessment);
        }

        // Add item management charges to school_fees
        // BUT skip if they're already in the schoolFees array from payment schedule (feesByParticular)
        // Check using item_management_id for exact match
        foreach ($itemMgmtByClass as $itemMgmtFee) {
            $itemMgmtId = $itemMgmtFee['item_management_id'] ?? null;

            // Check if this item_management_id already exists in schoolFees (from feesByParticular)
            $alreadyExists = false;
            $existingIndex = null;
            if ($itemMgmtId) {
                foreach ($schoolFees as $idx => $existingFee) {
                    if (isset($existingFee['item_management_id']) && $existingFee['item_management_id'] == $itemMgmtId) {
                        $alreadyExists = true;
                        $existingIndex = $idx;
                        break;
                    }
                }
            }

            // Add or update
            if (!$alreadyExists) {
                $schoolFees[] = [
                    'classid' => $itemMgmtFee['classid'],
                    'particulars' => $itemMgmtFee['particulars'],
                    'total_amount' => round($itemMgmtFee['total_amount'], 2),
                    'total_paid' => round($itemMgmtFee['total_paid'], 2),
                    'total_balance' => round($itemMgmtFee['total_balance'], 2),
                    'items' => $itemMgmtFee['items'],
                    'itemid' => $itemMgmtFee['itemid'],
                    'item_management_id' => $itemMgmtId,
                    'is_item_management' => true
                ];
            } else {
                // Update existing entry with precise totals/payments from itemMgmtFee
                $schoolFees[$existingIndex]['total_amount'] = round($itemMgmtFee['total_amount'], 2);
                $schoolFees[$existingIndex]['total_paid'] = round($itemMgmtFee['total_paid'], 2);
                $schoolFees[$existingIndex]['total_balance'] = round($itemMgmtFee['total_balance'], 2);
                $schoolFees[$existingIndex]['items'] = $itemMgmtFee['items'];
            }
        }

        // Align item management totals between school_fees and monthly_assessments and deduplicate
        $itemMgmtTotals = [];
        // Prefer the already-built school_fees amounts (they include itemid-based payments),
        // fallback to raw itemMgmtByClass if not present in school_fees.
        foreach ($schoolFees as $fee) {
            if (!empty($fee['item_management_id'])) {
                $id = $fee['item_management_id'];
                $itemMgmtTotals[$id] = [
                    'total_amount' => $fee['total_amount'] ?? 0,
                    'total_paid' => $fee['total_paid'] ?? 0,
                    'total_balance' => $fee['total_balance'] ?? 0,
                ];
            }
        }
        foreach ($itemMgmtByClass as $mgmt) {
            if (!empty($mgmt['item_management_id']) && !isset($itemMgmtTotals[$mgmt['item_management_id']])) {
                $itemMgmtTotals[$mgmt['item_management_id']] = $mgmt;
            }
        }

        $seenItemMgmt = [];
        $alignedMonthly = [];
        foreach ($monthlyAssessments as $ma) {
            $imId = $ma['item_management_id'] ?? null;
            if ($imId) {
                // Skip duplicates: keep first occurrence only
                if (isset($seenItemMgmt[$imId])) {
                    continue;
                }
                $seenItemMgmt[$imId] = true;

                // If we have authoritative totals, sync them
                if (isset($itemMgmtTotals[$imId])) {
                    $tot = $itemMgmtTotals[$imId];
                    $ma['total_due'] = round($tot['total_amount'] ?? 0, 2);
                    $ma['total_paid'] = round($tot['total_paid'] ?? 0, 2);
                    $ma['balance'] = round($tot['total_balance'] ?? 0, 2);
                    $ma['status'] = ($ma['balance'] ?? 0) > 0 ? 'pending' : 'paid';

                    // Update breakdown or items to match the same totals
                    if (isset($ma['breakdown']) && is_array($ma['breakdown'])) {
                        foreach ($ma['breakdown'] as &$bd) {
                            $bd['amount'] = $ma['total_due'];
                            $bd['payment'] = $ma['total_paid'];
                            $bd['balance'] = $ma['balance'];
                        }
                        unset($bd);
                    } elseif (isset($ma['items']) && is_array($ma['items'])) {
                        foreach ($ma['items'] as &$bd) {
                            $bd['amount'] = $ma['total_due'];
                            $bd['payment'] = $ma['total_paid'];
                            $bd['balance'] = $ma['balance'];
                        }
                        unset($bd);
                    }
                }
            }
            $alignedMonthly[] = $ma;
        }
        $monthlyAssessments = $alignedMonthly;

        // Add missing item management monthly entries (flat, no nested breakdown)
        // Build lookup for particulars/classid from school_fees first, then raw itemMgmtByClass
        $itemMgmtMeta = [];
        foreach ($schoolFees as $fee) {
            if (!empty($fee['item_management_id'])) {
                $itemMgmtMeta[$fee['item_management_id']] = [
                    'particulars' => $fee['particulars'] ?? 'Item Management',
                    'classid' => $fee['classid'] ?? null,
                ];
            }
        }
        foreach ($itemMgmtByClass as $mgmt) {
            if (!empty($mgmt['item_management_id']) && !isset($itemMgmtMeta[$mgmt['item_management_id']])) {
                $itemMgmtMeta[$mgmt['item_management_id']] = [
                    'particulars' => $mgmt['particulars'] ?? 'Item Management',
                    'classid' => $mgmt['classid'] ?? null,
                ];
            }
        }
        foreach ($itemMgmtTotals as $imId => $tot) {
            if (isset($seenItemMgmt[$imId])) {
                continue;
            }
            $meta = $itemMgmtMeta[$imId] ?? ['particulars' => 'Item Management', 'classid' => null];
            $totalAmount = $tot['total_amount'] ?? 0;
            $totalPaid = $tot['total_paid'] ?? 0;
            $balance = round($tot['total_balance'] ?? ($totalAmount - $totalPaid), 2);

            $monthlyAssessments[] = [
                'paymentsetupdetail_id' => null,
                'due_date' => 'No Date',
                'assessment_label' => $meta['particulars'],
                'total_due' => round($totalAmount, 2),
                'total_paid' => round($totalPaid, 2),
                'balance' => $balance,
                'status' => $balance > 0 ? 'pending' : 'paid',
                'breakdown' => [
                    [
                        'classid' => $meta['classid'],
                        'particulars' => $meta['particulars'],
                        'amount' => round($totalAmount, 2),
                        'payment' => round($totalPaid, 2),
                        'balance' => $balance,
                        'items' => [],
                        'paymentsetupdetail_id' => null,
                        'id' => null,
                        'tuitiondetail_id' => null,
                        'itemid' => null,
                        'laboratory_fee_id' => null,
                        'item_management_id' => $imId,
                        'is_item_management' => true,
                    ],
                ],
                'item_management_id' => $imId,
                'is_item_management' => true,
            ];
        }

        // Add book entries to school_fees with monthly breakdown
        foreach ($bookEntriesFromDB as $book) {
            $bookClassId = $book->classid; // Use actual classid from bookentries
            $bookItemId = $book->id; // Use bookentries.id as itemid
            $bookAmount = (float) $book->amount;
            $mopid = $book->mopid;

            // Get payment schedule for this book entry
            $paymentSchedule = DB::table('paymentsetupdetail')
                ->where('paymentid', $mopid)
                ->where('deleted', 0)
                ->orderBy('paymentno')
                ->get();

            // Get total payments for this book entry by matching itemid from chrngtransitems
            $bookPayments = DB::table('chrngtrans as ct')
                ->join('chrngtransitems as cti', 'ct.id', '=', 'cti.chrngtransid')
                ->where('ct.studid', $studid)
                ->where('ct.syid', $syid)
                ->where('ct.cancelled', 0)
                ->where('cti.deleted', 0)
                ->where('cti.itemid', $bookItemId)
                ->where('cti.classid', $bookClassId)
                ->when($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25, function ($q) use ($semid) {
                    $q->where(function ($subQ) use ($semid) {
                        $subQ->where('cti.semid', $semid)->orWhereNull('cti.semid');
                    });
                })
                ->sum('cti.amount');

            // Cap total book payments to the book amount to avoid over-allocation
            $bookPayments = min((float) $bookPayments, $bookAmount);
            $bookBalance = max(0, $bookAmount - $bookPayments);

            // If no payment schedule exists, treat as one-time payment
            if ($paymentSchedule->isEmpty()) {
                // Get payment for one-time payment
                $oneTimePayment = DB::table('chrngtrans as ct')
                    ->join('chrngtransitems as cti', 'ct.id', '=', 'cti.chrngtransid')
                    ->where('ct.studid', $studid)
                    ->where('ct.syid', $syid)
                    ->where('ct.cancelled', 0)
                    ->where('cti.deleted', 0)
                    ->where('cti.itemid', $bookItemId)
                    ->where('cti.classid', $bookClassId)
                    ->when($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25, function ($q) use ($semid) {
                        $q->where(function ($subQ) use ($semid) {
                            $subQ->where('cti.semid', $semid)->orWhereNull('cti.semid');
                        });
                    })
                    ->sum('cti.amount');

                $oneTimePayment = (float) $oneTimePayment;
                $oneTimeBalance = max(0, $bookAmount - $oneTimePayment);

                $schoolFees[] = [
                    'classid' => $bookClassId,
                    'particulars' => $book->particulars,
                    'total_amount' => round($bookAmount, 2),
                    'total_paid' => round($oneTimePayment, 2),
                    'total_balance' => round($oneTimeBalance, 2),
                    'itemid' => $bookItemId,
                    'is_book_entry' => true,
                    'mopid' => $mopid
                ];
                continue;
            }

            // Create monthly breakdown for book entry
            $numberOfPayments = $paymentSchedule->count();
            $amountPerPayment = $bookAmount / $numberOfPayments;
            $bookMonthlyItems = [];
            $remainingBookPayment = $bookPayments;

            foreach ($paymentSchedule as $schedule) {
                $paymentNo = $schedule->paymentno;
                $dueDate = $schedule->duedate;
                $description = $schedule->description ?: $this->getPaymentDescriptionFallback($paymentNo);

                // Get payment for this specific payment schedule
                $schedulePayment = DB::table('chrngtrans as ct')
                    ->join('chrngtransitems as cti', 'ct.id', '=', 'cti.chrngtransid')
                    ->join('chrngcashtrans as cct', function ($join) {
                        $join->on('ct.transno', '=', 'cct.transno')
                            ->on('cti.itemid', '=', 'cct.itemid')
                            ->on('cti.classid', '=', 'cct.classid');
                    })
                    ->where('ct.studid', $studid)
                    ->where('ct.syid', $syid)
                    ->where('ct.cancelled', 0)
                    ->where('cti.deleted', 0)
                    ->where('cct.deleted', 0)
                    ->where('cti.itemid', $bookItemId)
                    ->where('cti.classid', $bookClassId)
                    ->where('cct.paymentsetupdetail_id', $schedule->id)
                    ->when($studentInfo->levelid >= 14 && $studentInfo->levelid <= 25, function ($q) use ($semid) {
                        $q->where(function ($subQ) use ($semid) {
                            $subQ->where('cct.semid', $semid)->orWhereNull('cct.semid');
                        });
                    })
                    ->sum('cct.amount');

                // Allocate payments across the schedule using total payments to ensure book entry payments are reflected
                $schedulePayment = (float) $schedulePayment;
                // Sequentially allocate total book payments across schedule rows (ignore per-psd gaps)
                $allocatedPayment = min($amountPerPayment, $remainingBookPayment);
                $remainingBookPayment = max(0, $remainingBookPayment - $allocatedPayment);
                $scheduleBalance = max(0, $amountPerPayment - $allocatedPayment);

                // Create label for this payment
                $itemLabel = $book->particulars . ' - ' . strtoupper($description);

                // Create nested item showing the book entry for this payment
                $nestedItem = [
                    'itemid' => $bookItemId,
                    'particulars' => $book->particulars,
                    'amount' => round($amountPerPayment, 2),
                    'payment' => round($allocatedPayment, 2),
                    'balance' => round($scheduleBalance, 2),
                    'classid' => $bookClassId,
                    'is_book_entry' => true
                ];

                // Add monthly item
                $bookMonthlyItems[] = [
                    'particulars' => $itemLabel,
                    'amount' => round($amountPerPayment, 2),
                    'payment' => round($allocatedPayment, 2),
                    'balance' => round($scheduleBalance, 2),
                    'classid' => $bookClassId,
                    'itemid' => $bookItemId,
                    'paymentsetupdetail_id' => $schedule->id,
                    'laboratory_fee_id' => null,
                    'is_book_entry' => true,
                    'items' => [$nestedItem]
                ];

                // Add to monthly assessment breakdown
                $assessmentIndex = null;
                foreach ($monthlyAssessments as $idx => $assessment) {
                    if ($assessment['paymentsetupdetail_id'] == $schedule->id) {
                        $assessmentIndex = $idx;
                        break;
                    }
                }

                if ($assessmentIndex !== null) {
                    // Add book entry to existing monthly assessment
                    $monthlyAssessments[$assessmentIndex]['breakdown'][] = [
                        'classid' => $bookClassId,
                        'particulars' => $itemLabel,
                        'amount' => round($amountPerPayment, 2),
                        'payment' => round($allocatedPayment, 2),
                        'balance' => round($scheduleBalance, 2),
                        'items' => [$nestedItem],
                        'paymentsetupdetail_id' => $schedule->id,
                        'id' => $book->id,
                        'tuitiondetail_id' => null,
                        'itemid' => $bookItemId,
                        'laboratory_fee_id' => null,
                        'item_management_id' => null,
                        'is_item_management' => false,
                        'is_book_entry' => true,
                        'nested_items' => [$nestedItem]
                    ];

                    // Update monthly assessment totals
                    $monthlyAssessments[$assessmentIndex]['total_due'] = round(
                        $monthlyAssessments[$assessmentIndex]['total_due'] + $amountPerPayment,
                        2
                    );
                    $monthlyAssessments[$assessmentIndex]['total_paid'] = round(
                        ($monthlyAssessments[$assessmentIndex]['total_paid'] ?? 0) + $allocatedPayment,
                        2
                    );
                    $monthlyAssessments[$assessmentIndex]['balance'] = round(
                        $monthlyAssessments[$assessmentIndex]['balance'] + $scheduleBalance,
                        2
                    );
                }
            }

            // Add to school_fees with monthly breakdown
            $schoolFees[] = [
                'classid' => $bookClassId,
                'particulars' => $book->particulars,
                'total_amount' => round($bookAmount, 2),
                'total_paid' => round($bookPayments, 2),
                'total_balance' => round($bookBalance, 2),
                'items' => $bookMonthlyItems,
                'itemid' => $bookItemId,
                'laboratory_fee_id' => null,
                'is_laboratory_fee' => false,
                'item_management_id' => null,
                'is_item_management' => false,
                'is_book_entry' => true,
                'mopid' => $mopid
            ];
        }

        // Validate and flag school fees that don't have items setup
        // Only check fees from tuitionheader (not book entries, item management, or lab fees)
        foreach ($schoolFees as &$fee) {
            $classid = $fee['classid'] ?? null;
            $isFromTuitionHeader = !($fee['is_book_entry'] ?? false) &&
                !($fee['is_item_management'] ?? false) &&
                !($fee['is_laboratory_fee'] ?? false);

            if ($isFromTuitionHeader && $classid) {
                // Check if this classification has items setup in tuitionitems
                $hasItemsSetup = isset($tuitionItemsByClassId[$classid]) &&
                    !empty($tuitionItemsByClassId[$classid]);

                $fee['has_items_setup'] = $hasItemsSetup;

                // If no items setup, add warning message
                if (!$hasItemsSetup) {
                    $fee['items_setup_warning'] = 'There are no item/s found in ' . $fee['particulars'] . '.' . ' Contact the Finance Department to fix.';
                }
            } else {
                // For non-tuitionheader fees, assume items are setup (they have their own item structure)
                $fee['has_items_setup'] = true;
            }
        }
        unset($fee);

        // Debug school fees before return for student 2
        if ($studid == 2) {
            $labFees = array_filter($schoolFees, function ($fee) {
                return isset($fee['is_laboratory_fee']) && $fee['is_laboratory_fee'];
            });
            \Log::debug('[SCHOOL-FEES-FINAL] Lab fees before return', [
                'lab_fees_count' => count($labFees),
                'lab_fees' => array_map(function ($fee) {
                    return [
                        'laboratory_fee_id' => $fee['laboratory_fee_id'] ?? 'NOT SET',
                        'particulars' => substr($fee['particulars'] ?? '', 0, 60),
                        'total_paid' => $fee['total_paid'] ?? 0,
                        'total_balance' => $fee['total_balance'] ?? 0
                    ];
                }, $labFees)
            ]);
        }

        // Return the collected adjustments
        return [
            'school_fees' => $schoolFees,
            'monthly_assessments' => $monthlyAssessments,
            'discounts_adjustments' => $adjustments,
            'total_overpayment' => round($totalOverpayment, 2),
            'debug_due_date_items' => $debugDueDateItems,
            'debug_lab_fee_schedule_summary' => $labFeeScheduleSummary,
        ];
    }

    private function getOldAccountBalances($studid, $oldAccountTerms, $studentInfo)
    {
        $oldAccounts = [];

        // Get active SY/Sem to check for forwarded balances
        $activeSY = DB::table('sy')->where('isactive', 1)->first();
        $activeSem = DB::table('semester')->where('isactive', 1)->first();

        // Get balance forward setup for active term
        $balForwardSetup = null;
        if ($activeSY) {
            $balForwardSetup = DB::table('balforwardsetup')
                ->where('syid', $activeSY->id)
                ->where('semid', $activeSem ? $activeSem->id : null)
                ->first();
        }

        foreach ($oldAccountTerms as $term) {
            $syid = $term['syid'];
            $semid = $term['semid'];

            // For basic ed (levelid < 14), ignore semid when pulling schedules/payments so payments from any sem are considered
            $isBasicLevel = ($studentInfo && (($studentInfo->levelid >= 1 && $studentInfo->levelid <= 13) || $studentInfo->levelid == 16));
            $effectiveSemid = $isBasicLevel ? null : $semid;

            $schedules = self::getStudentPaymentSchedules([$studid], $syid, $effectiveSemid);

            if (empty($schedules[$studid])) {
                continue;
            }

            $schedule = $schedules[$studid];
            $dueDates = $schedule['due_dates'] ?? [];

            $totalBalance = 0;
            foreach ($dueDates as $dueDate) {
                $totalBalance += $dueDate['balance'] ?? 0;
            }

            if ($totalBalance > 0) {
                // Check if this term's balance was forwarded to the active term
                $wasForwarded = false;
                if ($balForwardSetup) {
                    $forwardedBalance = DB::table('studledger')
                        ->where('studid', $studid)
                        ->where('syid', $syid)
                        ->where('semid', $semid)
                        ->where('classid', $balForwardSetup->classid)
                        ->where('deleted', 0)
                        ->where('particulars', 'LIKE', '%FORWARDED TO%')
                        ->sum('payment');

                    // If a payment was made to forward this balance, don't show it as old account
                    if ($forwardedBalance > 0) {
                        $wasForwarded = true;
                    }
                }

                // Only add to old accounts if balance wasn't forwarded
                if (!$wasForwarded) {
                    $syDesc = DB::table('sy')->where('id', $syid)->value('sydesc');
                    $semDesc = $semid ? DB::table('semester')->where('id', $semid)->value('semester') : null;

                    // Create breakdown items grouped by classid (total amount for each fee type)
                    $breakdownItems = [];
                    $groupedFees = [];

                    foreach ($dueDates as $dueDate) {
                        $classid = $dueDate['classid'] ?? null;
                        $particulars = $dueDate['particulars'] ?? 'Fee Item';

                        if (!isset($groupedFees[$classid])) {
                            $groupedFees[$classid] = [
                                'description' => $particulars,
                                'amount' => 0,
                                'paid' => 0,
                                'balance' => 0,
                                'classid' => $classid,
                                'is_adjustment' => $dueDate['is_standalone_adjustment'] ?? false,
                                'is_discount' => false,
                            ];
                        }

                        // Accumulate full scheduled amount (even if already paid)
                        $groupedFees[$classid]['amount'] += round($dueDate['amount'] ?? 0, 2);
                    }

                    // Calculate payments for old accounts directly from chrngcashtrans
                    // Old account payments are identified by paymentsetupdetail_id IS NULL
                    // and must match the specific syid/semid for this old account term
                    // Note: Old account payments may be in transactions with different syid/semid in chrngtrans,
                    // but chrngcashtrans has the correct syid/semid for the old account term
                    foreach ($groupedFees as $classid => &$fee) {
                        $oldAccountPaymentQuery = DB::table('chrngcashtrans as cct')
                            ->join('chrngtrans as ct', 'cct.transno', '=', 'ct.transno')
                            ->where('cct.studid', $studid)
                            ->where('ct.studid', $studid)
                            ->where('cct.syid', $syid)
                            ->where('cct.classid', $classid)
                            ->whereNull('cct.paymentsetupdetail_id') // Old accounts don't have payment schedules
                            ->where('cct.deleted', 0)
                            ->where('ct.cancelled', 0);

                        // Handle semid filter - if semid is provided, match it; otherwise allow NULL
                        if (!is_null($semid)) {
                            $oldAccountPaymentQuery->where('cct.semid', $semid);
                        } else {
                            $oldAccountPaymentQuery->whereNull('cct.semid');
                        }

                        $oldAccountPayment = $oldAccountPaymentQuery->sum('cct.amount');

                        // Set paid amount from chrngcashtrans query
                        $fee['paid'] = round((float) $oldAccountPayment, 2);
                        // Recalculate balance based on actual payment
                        $fee['balance'] = round(max(0, $fee['amount'] - $fee['paid']), 2);
                    }
                    unset($fee); // Unset reference

                    // Cascade any overpayment from one classification to others with balances (within the same old term)
                    $surplusPool = 0;
                    foreach ($groupedFees as &$fee) {
                        if (($fee['paid'] ?? 0) > ($fee['amount'] ?? 0)) {
                            $over = ($fee['paid'] - $fee['amount']);
                            $surplusPool += $over;
                            // Cap paid at amount for display, zero-out balance
                            $fee['paid'] = round($fee['amount'], 2);
                            $fee['balance'] = 0;
                        }
                    }
                    unset($fee);

                    if ($surplusPool > 0) {
                        // Apply surplus to other classids with remaining balance (ascending classid)
                        uasort($groupedFees, function ($a, $b) {
                            return ($a['classid'] ?? 0) <=> ($b['classid'] ?? 0);
                        });
                        foreach ($groupedFees as &$fee) {
                            if ($surplusPool <= 0) {
                                break;
                            }
                            $balance = $fee['balance'] ?? 0;
                            if ($balance <= 0) {
                                continue;
                            }
                            $apply = min($surplusPool, $balance);
                            $fee['paid'] = round(($fee['paid'] ?? 0) + $apply, 2);
                            $fee['balance'] = round($balance - $apply, 2);
                            $surplusPool -= $apply;
                        }
                        unset($fee);
                    }

                    // Apply transaction-level overpayments (amountpaid minus recorded cashtrans) to remaining balances
                    $overpaymentTotal = 0;
                    $transactions = DB::table('chrngtrans as ct')
                        ->where('ct.studid', $studid)
                        ->where('ct.syid', $syid)
                        ->where('ct.cancelled', 0)
                        ->when(!is_null($semid), function ($q) use ($semid) {
                            $q->where('ct.semid', $semid);
                        }, function ($q) {
                            $q->whereNull('ct.semid');
                        })
                        ->select('ct.transno', 'ct.amountpaid', 'ct.change_amount')
                        ->get();

                    foreach ($transactions as $txn) {
                        $cashTransTotal = DB::table('chrngcashtrans')
                            ->where('transno', $txn->transno)
                            ->where('studid', $studid)
                            ->where('syid', $syid)
                            ->when(!is_null($semid), function ($q) use ($semid) {
                                $q->where('semid', $semid);
                            }, function ($q) {
                                $q->whereNull('semid');
                            })
                            ->where('deleted', 0)
                            ->sum('amount');

                        $amountPaid = (float) ($txn->amountpaid ?? 0);
                        $changeAmt = (float) ($txn->change_amount ?? 0);
                        $overpay = $amountPaid - (float) $cashTransTotal - $changeAmt;
                        if ($overpay > 0.01) {
                            $overpaymentTotal += $overpay;
                        }
                    }

                    if ($overpaymentTotal > 0) {
                        // Allocate overpayment to fees by ascending classid priority
                        uasort($groupedFees, function ($a, $b) {
                            return ($a['classid'] ?? 0) <=> ($b['classid'] ?? 0);
                        });

                        foreach ($groupedFees as &$fee) {
                            if ($overpaymentTotal <= 0) {
                                break;
                            }
                            $balance = $fee['balance'] ?? 0;
                            if ($balance <= 0) {
                                continue;
                            }
                            $apply = min($overpaymentTotal, $balance);
                            $fee['paid'] += $apply;
                            $fee['balance'] = round(max(0, $balance - $apply), 2);
                            $overpaymentTotal -= $apply;
                        }
                        unset($fee);
                    }

                    // Fetch tuition items for old accounts
                    // Get enrollment to find feesid for this old term
                    $enrollmentTables = [
                        'enrolledstud' => ['levelid' => [1, 13]],
                        'sh_enrolledstud' => ['levelid' => [14, 15]],
                        'college_enrolledstud' => ['levelid' => [17, 25]],
                        'tesda_enrolledstud' => ['levelid' => [26]],
                    ];

                    $oldFeesId = null;
                    foreach ($enrollmentTables as $table => $config) {
                        $levelRange = $config['levelid'] ?? [];
                        // Safety check: ensure levelRange is an array with at least one element
                        if (empty($levelRange) || !is_array($levelRange)) {
                            continue;
                        }
                        // For single-element arrays (like tesda), use the same value for min and max
                        $minLevel = $levelRange[0] ?? null;
                        $maxLevel = isset($levelRange[1]) ? $levelRange[1] : ($levelRange[0] ?? null);

                        if (
                            $minLevel !== null && $maxLevel !== null &&
                            $studentInfo->levelid >= $minLevel && $studentInfo->levelid <= $maxLevel
                        ) {
                            $query = DB::table($table)
                                ->where('studid', $studid)
                                ->where('syid', $syid)
                                ->where('deleted', 0);

                            // Add semester filter for tables that use semid
                            if ($table !== 'enrolledstud' && $table !== 'tesda_enrolledstud' && !is_null($semid)) {
                                $query->where('semid', $semid);
                            }

                            $enrollment = $query->first();
                            if ($enrollment && isset($enrollment->feesid)) {
                                $oldFeesId = $enrollment->feesid;
                                break;
                            }
                        }
                    }

                    // If we found the tuitionheader, fetch tuition items for each classification
                    $tuitionItemsByClassId = [];
                    if ($oldFeesId) {
                        $tuitionDetailsWithItems = DB::table('tuitiondetail as td')
                            ->leftJoin('tuitionitems as ti', function ($join) {
                                $join->on('td.id', '=', 'ti.tuitiondetailid')
                                    ->where('ti.deleted', '=', 0);
                            })
                            ->leftJoin('items as i', 'ti.itemid', '=', 'i.id')
                            ->where('td.headerid', $oldFeesId)
                            ->where('td.deleted', 0)
                            ->select([
                                'td.classificationid as classid',
                                'ti.id as tuitionitem_id',
                                'ti.itemid',
                                'i.description as item_description',
                                'ti.amount as item_amount'
                            ])
                            ->get();

                        // Group tuition items by classid
                        foreach ($tuitionDetailsWithItems as $row) {
                            if ($row->tuitionitem_id) {
                                if (!isset($tuitionItemsByClassId[$row->classid])) {
                                    $tuitionItemsByClassId[$row->classid] = [];
                                }
                                $tuitionItemsByClassId[$row->classid][] = [
                                    'itemid' => $row->itemid,
                                    'description' => $row->item_description ?? 'Unknown Item',
                                    'amount' => round((float) $row->item_amount, 2),
                                ];
                            }
                        }
                    }

                    // Convert grouped fees to breakdown items and add tuition items
                    foreach ($groupedFees as $classid => $fee) {
                        // Add tuition items if available for this classid
                        $items = [];
                        if (isset($tuitionItemsByClassId[$classid])) {
                            // Calculate total original amount of all nested items for this classid
                            $totalNestedItemsAmount = 0;
                            foreach ($tuitionItemsByClassId[$classid] as $item) {
                                $totalNestedItemsAmount += $item['amount'];
                            }

                            // Calculate the proportion of parent balance to total nested items amount
                            // This represents what portion of the original fees is still unpaid
                            $proportion = $totalNestedItemsAmount > 0 ? ($fee['balance'] / $totalNestedItemsAmount) : 0;

                            // Fetch actual payments for each item from chrngcashtrans
                            // Use cct.syid and cct.semid to filter payments for THIS old account term only
                            foreach ($tuitionItemsByClassId[$classid] as $item) {
                                $itemOriginalAmount = $item['amount'];

                                // Get actual payment for this specific item from chrngcashtrans
                                // Filter by cct.syid/semid to avoid cross-term contamination
                                $itemPaymentQuery = DB::table('chrngtrans as ct')
                                    ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
                                    ->join('chrngtransitems as cti', function ($join) {
                                        $join->on('ct.id', '=', 'cti.chrngtransid')
                                            ->on('cct.classid', '=', 'cti.classid');
                                    })
                                    ->where('ct.studid', $studid)
                                    ->where('cct.syid', $syid)
                                    ->where('cct.semid', $semid)
                                    ->where('cct.classid', $classid)
                                    ->where('cti.itemid', $item['itemid'])
                                    ->where('ct.cancelled', 0)
                                    ->where('cct.deleted', 0)
                                    ->where('cti.deleted', 0)
                                    ->whereNull('cct.paymentsetupdetail_id') // Old accounts don't have payment schedules
                                    ->sum('cti.amount');

                                // Calculate the remaining portion of this item based on the proportion
                                // This shows only the unpaid portion, not the full original amount
                                $itemRemainingAmount = round($itemOriginalAmount * $proportion, 2);

                                // Payment is the paid portion (original minus remaining), capped by actual payments
                                $itemPaidPortion = round(max(0, $itemOriginalAmount - $itemRemainingAmount), 2);
                                $itemPayment = min(round($itemPaymentQuery, 2), $itemPaidPortion);

                                // Balance is the remaining amount minus any payments made
                                $itemBalance = round(max(0, $itemRemainingAmount), 2);

                                // Only include items with remaining balance > 0
                                if ($itemRemainingAmount > 0) {
                                    $items[] = [
                                        'itemid' => $item['itemid'],
                                        'particulars' => $item['description'],
                                        'amount' => $itemRemainingAmount,  // Show remaining portion, not original amount
                                        'payment' => $itemPayment,
                                        'balance' => $itemBalance,
                                    ];
                                }
                            }
                        }

                        $breakdownItems[] = array_merge($fee, ['items' => $items]);
                    }

                    // Recalculate total balance from updated breakdown items
                    $recalculatedTotalBalance = 0;
                    foreach ($breakdownItems as $breakdownItem) {
                        $recalculatedTotalBalance += round($breakdownItem['balance'] ?? 0, 2);
                    }

                    // Get old student info for this specific sy/semid
                    $oldStudentInfo = $this->getOldStudentInfo($studid, $syid, $semid);

                    $oldAccounts[] = [
                        'syid' => $syid,
                        'semid' => $semid,
                        'sy_description' => $syDesc,
                        'sem_description' => $semDesc,
                        'balance' => round($recalculatedTotalBalance, 2),
                        'breakdown' => $breakdownItems,
                        'old_student_info' => $oldStudentInfo,
                    ];
                }
            }
        }

        // Add non-forwarded old accounts from old_student_accounts table
        $studentOldAccountsData = self::getStudentOldAccountsData($studid);

        if (!empty($studentOldAccountsData)) {
            // Add as a separate entry in old_accounts array
            $totalBalance = 0;
            $breakdownItems = [];

            foreach ($studentOldAccountsData as $classData) {
                $totalBalance += $classData['balance'];

                $items = [];
                foreach ($classData['items'] as $item) {
                    $items[] = [
                        'itemid' => $item['itemid'],
                        'particulars' => $item['particulars'],
                        'amount' => $item['amount'],
                        'payment' => $item['payment'],
                        'balance' => $item['balance'],
                    ];
                }

                $breakdownItems[] = [
                    'description' => $classData['description'],
                    'amount' => $classData['amount'],
                    'paid' => $classData['payment'],
                    'balance' => $classData['balance'],
                    'classid' => $classData['classid'],
                    'is_adjustment' => false,
                    'is_discount' => false,
                    'items' => $items,
                ];
            }

            if ($totalBalance > 0) {
                $oldAccounts[] = [
                    'syid' => null,
                    'semid' => null,
                    'sy_description' => 'Previous School Years',
                    'sem_description' => 'Old Accounts (Not Forwarded)',
                    'balance' => round($totalBalance, 2),
                    'breakdown' => $breakdownItems,
                    'old_student_info' => null,
                ];
            }
        }

        // Sort by SY desc, then Sem desc
        usort($oldAccounts, function ($a, $b) {
            // Null SY values (old accounts from old_student_accounts) should appear last
            if ($a['syid'] === null && $b['syid'] !== null) {
                return 1;
            }
            if ($a['syid'] !== null && $b['syid'] === null) {
                return -1;
            }
            if ($a['syid'] !== $b['syid']) {
                return $b['syid'] <=> $a['syid'];
            }
            return ($b['semid'] ?? 0) <=> ($a['semid'] ?? 0);
        });

        return $oldAccounts;
    }

    /**
     * Get old student information for a specific school year and semester
     */
    private function getOldStudentInfo($studid, $syid, $semid)
    {
        // Get student info from studinfo table
        $studentInfo = DB::table('studinfo as si')
            ->leftJoin('gradelevel as gl', 'si.levelid', '=', 'gl.id')
            ->leftJoin('college_courses as cc', 'si.courseid', '=', 'cc.id')
            ->leftJoin('sh_strand as ss', 'si.strandid', '=', 'ss.id')
            ->leftJoin('grantee as g', 'si.grantee', '=', 'g.id')
            ->where('si.id', $studid)
            ->select([
                'si.id',
                'si.sid',
                'si.firstname',
                'si.middlename',
                'si.lastname',
                'si.levelid',
                'si.courseid',
                'si.strandid',
                'si.grantee',
                'gl.levelname',
                'cc.courseabrv as course_name',
                'ss.strandcode as strand_name',
                'g.description as grantee_description'
            ])
            ->first();

        if (!$studentInfo) {
            return null;
        }

        // Get section information for this specific sy/semid
        $sectionInfo = null;
        $levelid = $studentInfo->levelid;

        // Determine which enrollment table to use based on level
        $enrollTable = null;
        if ($levelid == 14 || $levelid == 15) {
            $enrollTable = 'sh_enrolledstud';
        } elseif ($levelid >= 17 && $levelid <= 25) {
            $enrollTable = 'college_enrolledstud';
        } elseif ($levelid == 26) {
            $enrollTable = 'tesda_enrolledstud';
        } else {
            $enrollTable = 'enrolledstud';
        }

        // Get enrollment info for this specific sy/semid
        if ($enrollTable) {
            $enrollmentQuery = DB::table($enrollTable . ' as es')
                ->where('es.studid', $studid)
                ->where('es.syid', $syid)
                ->where('es.deleted', 0);

            // Add semester filter for college/SHS
            if ($levelid >= 14 && $levelid <= 25) {
                $enrollmentQuery->where('es.semid', $semid);
            }

            // Join with appropriate section table based on level
            if ($levelid >= 17 && $levelid <= 25) {
                // College student - use college_sections
                $enrollmentQuery->leftJoin('college_sections as cs', 'es.sectionID', '=', 'cs.id')
                    ->select([
                        'cs.sectionDesc as section_name',
                        'es.studstatus as status',
                        'es.feesid'
                    ]);
            } else {
                // K-10 and SHS - use sections
                $enrollmentQuery->leftJoin('sections as s', 'es.sectionid', '=', 's.id')
                    ->select([
                        's.sectionname as section_name',
                        'es.studstatus as status',
                        'es.feesid'
                    ]);
            }

            $enrollment = $enrollmentQuery->first();

            if ($enrollment) {
                $sectionInfo = [
                    'section_name' => $enrollment->section_name,
                    'status' => $enrollment->status,
                    'feesid' => $enrollment->feesid
                ];
            }
        }

        // Build full name
        $first = trim($studentInfo->firstname ?? '');
        $last = trim($studentInfo->lastname ?? '');
        $middle = trim($studentInfo->middlename ?? '');
        $nameParts = array_filter([$first, $last, $middle], fn($part) => $part !== '');
        $fullname = implode(' ', $nameParts);

        return [
            'id' => $studentInfo->id,
            'sid' => $studentInfo->sid,
            'firstname' => $first,
            'lastname' => $last,
            'middlename' => $middle,
            'fullname' => $fullname,
            'levelid' => $studentInfo->levelid,
            'levelname' => $studentInfo->levelname,
            'courseid' => $studentInfo->courseid,
            'course_name' => $studentInfo->course_name,
            'strandid' => $studentInfo->strandid,
            'strand_name' => $studentInfo->strand_name,
            'grantee' => $studentInfo->grantee,
            'grantee_description' => $studentInfo->grantee_description,
            'section_info' => $sectionInfo,
            'syid' => $syid,
            'semid' => $semid
        ];
    }

    private function getDiscountsAndAdjustmentsForTerm($studid, $syid, $semid, $levelid)
    {
        // Get posted discounts with payment information
        $discounts = DB::table('studdiscounts as sd')
            ->leftJoin('itemclassification as ic', 'sd.classid', '=', 'ic.id')
            ->leftJoin('users as u', 'sd.createdby', '=', 'u.id')
            ->where('sd.studid', $studid)
            ->where('sd.syid', $syid)
            ->where('sd.deleted', 0)
            ->where('sd.posted', 1)
            ->when($levelid >= 14 && $levelid <= 25, function ($q) use ($semid) {
                $q->where('sd.semid', $semid);
            })
            ->select([
                'sd.classid',
                'ic.description as particulars',
                'sd.discamount as amount',
                'sd.id as discount_id',
                'sd.createddatetime as transaction_date',
                'u.name as created_by',
                'sd.deleted as is_voided'
            ])
            ->get()
            ->map(function ($discount) use ($studid, $syid, $semid, $levelid) {
                // Discounts are reductions in amount owed, not payments
                // They don't have a "paid" amount - they simply reduce the balance
                // Display them as view-only informational items
                $amount = (float) $discount->amount;

                return [
                    'classid' => $discount->classid,
                    'particulars' => $discount->particulars ?? 'Discount',
                    'amount' => round($amount, 2),
                    'paid' => 0, // Discounts are not payments
                    'balance' => 0, // Discounts are applied immediately, no balance
                    'discount_id' => $discount->discount_id,
                    'transaction_date' => $discount->transaction_date,
                    'created_by' => $discount->created_by,
                    'is_voided' => $discount->is_voided,
                ];
            })
            ->toArray();

        // Get debit adjustments with payment information and breakdown by payment schedule
        $debitAdjustmentsRaw = DB::table('adjustments as a')
            ->join('adjustmentdetails as ad', 'a.id', '=', 'ad.headerid')
            ->leftJoin('itemclassification as ic', 'a.classid', '=', 'ic.id')
            ->leftJoin('users as u', 'a.createdby', '=', 'u.id')
            ->where('ad.studid', $studid)
            ->where('a.syid', $syid)
            ->where('a.isdebit', 1)
            ->where('a.amount', '>', 0)
            ->where('ad.deleted', 0)
            ->where('a.deleted', 0)
            ->when($levelid >= 14 && $levelid <= 25, function ($q) use ($semid) {
                $q->where('a.semid', $semid);
            })
            ->select([
                'a.classid',
                'a.description as adjustment_desc',
                'ic.description as particulars',
                'a.amount',
                'a.mop',
                'a.id as adjustment_id',
                'a.createddatetime as transaction_date',
                'u.name as created_by',
                'a.adjstatus',
                'a.deleted as is_voided'
            ])
            ->get();

        $debitAdjustments = [];
        foreach ($debitAdjustmentsRaw as $adj) {
            // Get payments for this debit adjustment
            $adjustmentDesc = $adj->adjustment_desc ?? $adj->particulars ?? 'Debit Adjustment';

            $payments = DB::table('chrngcashtrans as cct')
                ->join('chrngtrans as ct', 'cct.transno', '=', 'ct.transno')
                ->where('cct.studid', $studid)
                ->where('ct.studid', $studid)
                ->where('cct.syid', $syid)
                ->where('cct.classid', $adj->classid)
                ->where('cct.particulars', 'LIKE', '%' . $adjustmentDesc . '%')
                ->where('ct.cancelled', 0)
                ->when($levelid >= 14 && $levelid <= 25, function ($q) use ($syid, $semid) {
                    $q->where('ct.syid', $syid)
                        ->where('ct.semid', $semid)
                        ->where(function ($subQ) use ($semid) {
                            $subQ->where('cct.semid', $semid)->orWhereNull('cct.semid');
                        });
                })
                ->when($levelid < 17 || $levelid > 25, function ($q) use ($syid) {
                    $q->where('ct.syid', $syid);
                })
                ->sum('cct.amount');

            $totalPaid = (float) ($payments ?? 0);
            $amount = (float) $adj->amount;
            $balance = max(0, $amount - $totalPaid);

            // If adjustment has payment schedule (mop), add breakdown entries
            if ($adj->mop) {
                $paymentSchedule = DB::table('paymentsetupdetail')
                    ->select('id', 'paymentno', 'duedate', 'percentamount', 'description')
                    ->where('paymentid', $adj->mop)
                    ->where('deleted', 0)
                    ->orderBy('paymentno')
                    ->get();

                if ($paymentSchedule->isNotEmpty()) {
                    // Build breakdown items array
                    $breakdownItems = [];
                    $remainingPaid = $totalPaid;
                    foreach ($paymentSchedule as $schedule) {
                        $scheduleAmount = ($amount * $schedule->percentamount) / 100;
                        $schedulePaid = min($scheduleAmount, max(0, $remainingPaid));
                        $remainingPaid -= $schedulePaid;
                        $scheduleBalance = max(0, $scheduleAmount - $schedulePaid);

                        $breakdownParticulars = 'ADJUSTMENT: ' . $adjustmentDesc . ($schedule->description ? ' - ' . $schedule->description : '');

                        $breakdownItems[] = [
                            'particulars' => $breakdownParticulars,
                            'amount' => round($scheduleAmount, 2),
                            'payment' => round($schedulePaid, 2),
                            'balance' => round($scheduleBalance, 2),
                            'paymentsetupdetail_id' => $schedule->id ?? null,
                            'paymentsetupdetailId' => $schedule->id ?? null, // camelCase for frontend compatibility
                            'items' => [
                                [
                                    'particulars' => 'ADJUSTMENT: ' . $adjustmentDesc,
                                    'amount' => round($scheduleAmount, 2),
                                    'payment' => round($schedulePaid, 2),
                                    'balance' => round($scheduleBalance, 2),
                                ]
                            ]
                        ];
                    }

                    // Add the parent entry with all breakdown items
                    $debitAdjustments[] = [
                        'classid' => $adj->classid,
                        'particulars' => 'ADJUSTMENT: ' . $adjustmentDesc,
                        'amount' => round($amount, 2),
                        'paid' => round($totalPaid, 2),
                        'balance' => round($balance, 2),
                        'adjustment_id' => $adj->adjustment_id,
                        'transaction_date' => $adj->transaction_date,
                        'created_by' => $adj->created_by,
                        'adjstatus' => $adj->adjstatus,
                        'is_voided' => $adj->is_voided,
                        'mop' => $adj->mop,
                        'items' => $breakdownItems
                    ];
                } else {
                    // No payment schedule found, add as single entry
                    $debitAdjustments[] = [
                        'classid' => $adj->classid,
                        'particulars' => 'ADJUSTMENT: ' . $adjustmentDesc,
                        'amount' => round($amount, 2),
                        'paid' => round($totalPaid, 2),
                        'balance' => round($balance, 2),
                        'adjustment_id' => $adj->adjustment_id,
                        'transaction_date' => $adj->transaction_date,
                        'created_by' => $adj->created_by,
                        'adjstatus' => $adj->adjstatus,
                        'is_voided' => $adj->is_voided,
                        'mop' => $adj->mop,
                        'items' => []
                    ];
                }
            } else {
                // No mop (one-time payment), add as single entry
                $debitAdjustments[] = [
                    'classid' => $adj->classid,
                    'particulars' => 'ADJUSTMENT: ' . $adjustmentDesc,
                    'amount' => round($amount, 2),
                    'paid' => round($totalPaid, 2),
                    'balance' => round($balance, 2),
                    'adjustment_id' => $adj->adjustment_id,
                    'transaction_date' => $adj->transaction_date,
                    'created_by' => $adj->created_by,
                    'adjstatus' => $adj->adjstatus,
                    'is_voided' => $adj->is_voided,
                    'mop' => $adj->mop,
                    'items' => []
                ];
            }
        }

        // Get credit adjustments with payment information
        $creditAdjustments = DB::table('adjustments as a')
            ->join('adjustmentdetails as ad', 'a.id', '=', 'ad.headerid')
            ->leftJoin('itemclassification as ic', 'a.classid', '=', 'ic.id')
            ->leftJoin('users as u', 'a.createdby', '=', 'u.id')
            ->where('ad.studid', $studid)
            ->where('a.syid', $syid)
            ->where('a.iscredit', 1)
            ->where('a.amount', '>', 0)
            ->where('ad.deleted', 0)
            ->where('a.deleted', 0)
            ->when($levelid >= 14 && $levelid <= 25, function ($q) use ($semid) {
                $q->where('a.semid', $semid);
            })
            ->select([
                'a.classid',
                'a.description as adjustment_desc',
                'ic.description as particulars',
                'a.amount',
                'a.mop',
                'a.id as adjustment_id',
                'a.createddatetime as transaction_date',
                'u.name as created_by',
                'a.adjstatus',
                'a.deleted as is_voided'
            ])
            ->get()
            ->map(function ($adj) use ($studid, $syid, $semid, $levelid) {
                // Credit adjustments are reductions in amount owed (like discounts)
                // They don't have a "paid" amount - they simply reduce the balance
                // Display them as view-only informational items
                $amount = (float) $adj->amount;

                return [
                    'classid' => $adj->classid,
                    'particulars' => $adj->adjustment_desc ?? $adj->particulars ?? 'Credit Adjustment',
                    'amount' => round($amount, 2),
                    'paid' => 0, // Credit adjustments are not payments
                    'balance' => 0, // Credit adjustments are applied immediately, no balance
                    'adjustment_id' => $adj->adjustment_id,
                    'transaction_date' => $adj->transaction_date,
                    'created_by' => $adj->created_by,
                    'adjstatus' => $adj->adjstatus,
                    'is_voided' => $adj->is_voided,
                    'mop' => $adj->mop,
                ];
            })
            ->toArray();

        return [
            'discounts' => $discounts,
            'debit_adjustments' => $debitAdjustments,
            'credit_adjustments' => $creditAdjustments,
        ];
    }

    /**
     * Cascade excess discount to next priority fee classification
     * 
     * @param array $remainingDiscountsByClass
     * @param string $currentClassid
     * @param float $excessDiscount
     * @return void
     */
    private static function cascadeDiscountToNextPriority(&$remainingDiscountsByClass, $currentClassid, $excessDiscount)
    {
        // Define priority order for fee classifications
        // NOTE: Use integers, not strings, to match database classid values
        $priorityOrder = [
            1, // TUITION FEE (highest priority)
            5, // TUITION FEE (alternative classid)
            4, // REGISTRATION FEE
            6, // LABORATORY FEES
            9, // COMPUTER FEE
            31, // SIM SYSTEM
            26, // INTRAMURAL'S CONTRIBUTION
            3, // OTHER FEES
            7, // ROBOTICS
        ];

        // Find current classid position in priority order
        $currentIndex = array_search($currentClassid, $priorityOrder);

        if ($currentIndex !== false) {
            // Try to distribute excess discount to next priority classifications
            for ($i = $currentIndex + 1; $i < count($priorityOrder); $i++) {
                $nextClassid = $priorityOrder[$i];

                // If this classification has remaining discount capacity and excess discount
                if (isset($remainingDiscountsByClass[$nextClassid]) && $remainingDiscountsByClass[$nextClassid] > 0 && $excessDiscount > 0) {
                    // Distribute excess discount to this classification
                    $distributedAmount = min($excessDiscount, $remainingDiscountsByClass[$nextClassid]);
                    $remainingDiscountsByClass[$nextClassid] -= $distributedAmount;
                    $excessDiscount -= $distributedAmount;

                    // If excess discount is exhausted, stop cascading
                    if ($excessDiscount <= 0) {
                        break;
                    }
                }
            }
        }

        // If there's still excess discount after cascading, it becomes overpayment
        if ($excessDiscount > 0) {
            // Store excess discount as overpayment for the original classid
            if (!isset($remainingDiscountsByClass['overpayment'])) {
                $remainingDiscountsByClass['overpayment'] = 0;
            }
            $remainingDiscountsByClass['overpayment'] += $excessDiscount;
        }
    }

    /**
     * Cascade excess discount to next priority schedule
     * 
     * @param float $remainingDiscount
     * @param object $currentSchedule
     * @param float $currentDueAmount
     * @return void
     */
    private static function cascadeDiscountToNextSchedule(&$remainingDiscount, $currentSchedule, $currentDueAmount)
    {
        // For payment schedules, we'll distribute excess discount to future schedules
        // This is a simplified approach - in a real implementation, you might want to
        // get the next schedules from the database and distribute accordingly

        // For now, we'll mark the excess as overpayment
        if ($remainingDiscount > 0) {
            // Store excess discount as overpayment
            if (!isset($GLOBALS['cascadedOverpayment'])) {
                $GLOBALS['cascadedOverpayment'] = 0;
            }
            $GLOBALS['cascadedOverpayment'] += $remainingDiscount;
            $remainingDiscount = 0; // Reset to prevent double application
        }
    }

    /**
     * Cascade remaining discounts within the same classification across multiple payment numbers
     *
     * For example, if Payment 7 of ROBOTICS has remaining discount after being applied,
     * it should be applied to Payment 8, Payment 9, etc. of the same ROBOTICS classification
     *
     * @param array $studentSchedule
     * @param array $remainingDiscountsByClass - Tracks remaining discount for each classification
     * @return void
     */
    private static function cascadeDiscountsWithinClassification(&$studentSchedule, &$remainingDiscountsByClass)
    {
        // Get classification names for discount source labels
        $classificationNames = DB::table('itemclassification')
            ->select('id', 'description')
            ->get()
            ->keyBy('id')
            ->map(function ($item) {
                return $item->description;
            })
            ->toArray();

        // Group items by classid
        $itemsByClass = [];
        foreach ($studentSchedule as $key => &$item) {
            $classid = $item['classid'];
            if (!isset($itemsByClass[$classid])) {
                $itemsByClass[$classid] = [];
            }
            $itemsByClass[$classid][] = ['key' => $key, 'item' => &$item];
        }
        unset($item); // Break reference after grouping to prevent corruption

        // For each classification, process items in payment number order
        foreach ($itemsByClass as $classid => $items) {
            // Skip if no remaining discount for this classification
            if (!isset($remainingDiscountsByClass[$classid]) || $remainingDiscountsByClass[$classid] <= 0) {
                continue;
            }

            $remainingDiscountPool = $remainingDiscountsByClass[$classid];

            // Get the source classification name
            $sourceClassName = $classificationNames[$classid] ?? 'Classification ' . $classid;

            // Sort items by payment number to process sequentially
            usort($items, function ($a, $b) {
                $paymentNoA = $a['item']['paymentno'] ?? PHP_INT_MAX;
                $paymentNoB = $b['item']['paymentno'] ?? PHP_INT_MAX;
                return $paymentNoA - $paymentNoB;
            });

            // Apply accumulated discounts to subsequent payments
            foreach ($items as &$itemRef) {
                $item = &$itemRef['item'];
                // Skip item management entries – they should not receive discount cascading
                if (!empty($item['is_item_management'])) {
                    continue;
                }
                $itemBalance = $item['balance'] ?? 0;

                if ($remainingDiscountPool > 0 && $itemBalance > 0) {
                    $discountToApply = min($remainingDiscountPool, $itemBalance);

                    // Apply the cascaded discount
                    $item['balance'] -= $discountToApply;
                    $item['amountpay'] = ($item['amountpay'] ?? 0) + $discountToApply;
                    $item['discount'] = ($item['discount'] ?? 0) + $discountToApply;

                    // Add to payment details
                    if (!isset($item['payment_details'])) {
                        $item['payment_details'] = [];
                    }
                    $item['payment_details'][] = [
                        'particulars' => 'Cascaded Discount',
                        'amount' => $discountToApply,
                        'discount_source' => $sourceClassName  // Same source as it's within class
                    ];

                    $remainingDiscountPool -= $discountToApply;
                }
            }
            unset($itemRef); // Break reference to prevent corruption
            unset($item); // Break reference to prevent corruption

            // Update the remaining discount for this classification
            $remainingDiscountsByClass[$classid] = $remainingDiscountPool;
            if ($classid == 7) {
                \Log::debug('[ROBOTICS-WITHIN-CLASS] After within-class cascade: remaining = ' . $remainingDiscountPool);
            }
        }
    }

    /**
     * Cascade any remaining discounts to other fee classifications
     *
     * @param array $remainingDiscountsByClass
     * @param array $studentSchedule
     * @return void
     */
    private static function cascadeRemainingDiscounts(&$remainingDiscountsByClass, &$studentSchedule)
    {
        // Define priority order for fee classifications
        // NOTE: Use integers, not strings, to match database classid values
        $priorityOrder = [
            1, // TUITION FEE (highest priority)
            5, // TUITION FEE (alternative classid)
            4, // REGISTRATION FEE
            6, // LABORATORY FEES
            9, // COMPUTER FEE
            31, // SIM SYSTEM
            26, // INTRAMURAL'S CONTRIBUTION
            3, // OTHER FEES
            7, // ROBOTICS
        ];

        // Find classifications with remaining discounts
        $classificationsWithDiscounts = [];
        foreach ($remainingDiscountsByClass as $classid => $remainingDiscount) {
            if ($remainingDiscount > 0 && $classid !== 'overpayment') {
                $classificationsWithDiscounts[$classid] = $remainingDiscount;
                if ($classid == 7) {
                    \Log::debug('[ROBOTICS-CASCADE-DEBUG] ROBOTICS has remaining: ' . $remainingDiscount);
                }
            }
        }
        \Log::debug('[CASCADE-DEBUG] Classifications with remaining discounts: ' . json_encode(array_keys($classificationsWithDiscounts)));

        // Helper function to recalculate classifications with balances from current schedule
        $recalculateBalances = function ($schedule) {
            $balances = [];
            $allClassids = [];
            foreach ($schedule as $scheduleItem) {
                // Skip item management entries when determining discount targets
                if (!empty($scheduleItem['is_item_management'])) {
                    continue;
                }
                $classid = $scheduleItem['classid'];
                $balance = $scheduleItem['balance'] ?? 0;

                // Track all classifications in schedule for cascading purposes
                if (!isset($allClassids[$classid])) {
                    $allClassids[$classid] = true;
                }

                if ($balance > 0) {
                    if (!isset($balances[$classid])) {
                        $balances[$classid] = 0;
                    }
                    $balances[$classid] += $balance;
                }
            }
            return [$balances, $allClassids];
        };

        // Find classifications with remaining balances (from student schedule)
        // Also track all classifications that exist in schedule so cascading can reach them
        list($classificationsWithBalances, $allClassificationsInSchedule) = $recalculateBalances($studentSchedule);

        // CRITICAL: Save the ORIGINAL balance calculations before any cascading modifies them
        // Each classid will cascade based on ORIGINAL balances, ensuring fair distribution
        $originalClassificationsWithBalances = $classificationsWithBalances;
        $originalAllClassificationsInSchedule = $allClassificationsInSchedule;

        // Sort by priority order
        $sortedClassifications = [];
        foreach ($priorityOrder as $classid) {
            if (isset($classificationsWithDiscounts[$classid])) {
                $sortedClassifications[$classid] = $classificationsWithDiscounts[$classid];
            }
        }

        // Track cascaded discounts per classification
        // Initialize for priority order classifications AND all other classifications in schedule (including book entries and adjustments)
        $cascadedDiscounts = [];
        foreach ($priorityOrder as $classid) {
            $cascadedDiscounts[$classid] = 0;
        }
        // Also initialize for any other classids in the schedule (book entries, standalone adjustments, etc.)
        foreach (array_keys($allClassificationsInSchedule) as $classid) {
            if (!isset($cascadedDiscounts[$classid])) {
                $cascadedDiscounts[$classid] = 0;
            }
        }

        // Track cascade source mapping: toClassid => fromClassid
        $cascadeSourceMap = [];

        // Get classification names for discount source labels
        $classificationNames = DB::table('itemclassification')
            ->select('id', 'description')
            ->get()
            ->keyBy('id')
            ->map(function ($item) {
                return $item->description;
            })
            ->toArray();

        // Apply discounts directly to payment schedules of the same classification first
        foreach ($sortedClassifications as $fromClassid => $discountAmount) {
            if ($fromClassid == 7) {
                \Log::debug('[ROBOTICS-CASCADE-DEBUG] Processing ROBOTICS with discount amount: ' . $discountAmount);
            }
            if ($discountAmount <= 0)
                continue;

            // Get the source classification name
            $sourceClassName = $classificationNames[$fromClassid] ?? 'Classification ' . $fromClassid;

            // Apply the discount directly to payment schedules of the same classification
            $remainingDiscount = $discountAmount;
            $discountAppliedToSameClass = 0;

            foreach ($studentSchedule as &$scheduleItem) {
                // Skip item management entries
                if (!empty($scheduleItem['is_item_management'])) {
                    continue;
                }
                if ($scheduleItem['classid'] == $fromClassid) {
                    $currentBalance = $scheduleItem['balance'] ?? 0;
                    if ($currentBalance > 0 && $remainingDiscount > 0) {
                        $discountToApply = min($remainingDiscount, $currentBalance);
                        $scheduleItem['balance'] = $currentBalance - $discountToApply;
                        $scheduleItem['amountpay'] = ($scheduleItem['amountpay'] ?? 0) + $discountToApply;
                        $remainingDiscount -= $discountToApply;
                        $discountAppliedToSameClass += $discountToApply;

                        // Add discount to payment details
                        if (!isset($scheduleItem['payment_details'])) {
                            $scheduleItem['payment_details'] = [];
                        }
                        $scheduleItem['payment_details'][] = [
                            'particulars' => 'Discount',
                            'amount' => $discountToApply,
                            'discount_source' => $sourceClassName  // Add source
                        ];
                    }
                }
            }
            unset($scheduleItem);

            // If discount was NOT fully applied to same class, reduce the discount field on source items
            // This prevents cascaded-OUT discounts from inflating overpayment calculations
            $discountCascadedOut = $discountAmount - $discountAppliedToSameClass;
            if ($discountCascadedOut > 0) {
                foreach ($studentSchedule as &$scheduleItem) {
                    if (!empty($scheduleItem['is_item_management'])) {
                        continue;
                    }
                    if ($scheduleItem['classid'] == $fromClassid) {
                        // Reduce or clear the discount field since it cascaded out
                        $currentDiscount = $scheduleItem['discount'] ?? 0;
                        $scheduleItem['discount'] = max(0, $currentDiscount - $discountCascadedOut);
                    }
                }
                unset($scheduleItem);
            }

            // Use ORIGINAL balances for cascading decisions to ensure fair distribution
            // Don't recalculate based on current state, as that would give advantage to classids processed later
            $classificationsWithBalances = $originalClassificationsWithBalances;
            $allClassificationsInSchedule = $originalAllClassificationsInSchedule;

            // If there's still remaining discount, cascade to other classifications
            if ($remainingDiscount > 0) {
                \Log::debug('[DISCOUNT-CASCADE-CROSS-CLASS] Classid ' . $fromClassid . ' has remaining discount: ' . $remainingDiscount);
                $fromIndex = array_search($fromClassid, $priorityOrder);
                \Log::debug('[DISCOUNT-CASCADE-CROSS-CLASS] Classid ' . $fromClassid . ' index in priority: ' . ($fromIndex === false ? 'NOT FOUND' : $fromIndex));
                if ($fromIndex !== false) {
                    // First, cascade through remaining priority order classids
                    for ($i = $fromIndex + 1; $i < count($priorityOrder); $i++) {
                        $toClassid = $priorityOrder[$i];

                        // Check if this classification exists in the schedule (even if balance is 0 or negative)
                        // This allows discounts to be applied to any classification in the schedule
                        if (isset($allClassificationsInSchedule[$toClassid])) {
                            // Get the remaining balance for this classification
                            $toClassBalance = isset($classificationsWithBalances[$toClassid]) ? $classificationsWithBalances[$toClassid] : 0;

                            // If this classification has any remaining balance, transfer discount
                            if ($toClassBalance > 0) {
                                $transferAmount = min($remainingDiscount, $toClassBalance);

                                // Store the cascaded discount for this classification
                                $cascadedDiscounts[$toClassid] += $transferAmount;

                                // Track the source of this cascaded discount
                                $cascadeSourceMap[$toClassid] = $fromClassid;

                                $remainingDiscount -= $transferAmount;

                                // If all discount is transferred, stop cascading to other priority classids
                                if ($remainingDiscount <= 0) {
                                    break;  // Break only the for loop, allow next classid to process
                                }
                            }
                        }
                    }

                    // If still have remaining discount, cascade to non-priority classids (book entries, adjustments, etc.)
                    if ($remainingDiscount > 0) {
                        \Log::debug('[DISCOUNT-CASCADE-NONPRI] From classid ' . $fromClassid . ', remaining discount: ' . $remainingDiscount . ', non-priority classids: ' . json_encode(array_keys($allClassificationsInSchedule)));

                        if ($fromClassid == 7) {
                            \Log::debug('[ROBOTICS-CASCADE-DEBUG] About to cascade ' . $remainingDiscount . ' to non-priority classids');
                        }
                        foreach ($allClassificationsInSchedule as $toClassid => $exists) {
                            // Skip if already in priority order
                            if (in_array($toClassid, $priorityOrder)) {
                                \Log::debug('[DISCOUNT-CASCADE-NONPRI] Skipping classid ' . $toClassid . ' (in priority order)');
                                continue;
                            }

                            // Get the remaining balance for this classification
                            $toClassBalance = isset($classificationsWithBalances[$toClassid]) ? $classificationsWithBalances[$toClassid] : 0;

                            \Log::debug('[DISCOUNT-CASCADE-NONPRI] Checking classid ' . $toClassid . ', balance: ' . $toClassBalance . ', remaining: ' . $remainingDiscount);

                            // If this classification has any remaining balance, transfer discount
                            if ($toClassBalance > 0) {
                                $transferAmount = min($remainingDiscount, $toClassBalance);

                                // Store the cascaded discount for this classification
                                $cascadedDiscounts[$toClassid] += $transferAmount;

                                // Track the source of this cascaded discount
                                $cascadeSourceMap[$toClassid] = $fromClassid;

                                \Log::debug('[DISCOUNT-CASCADE-NONPRI] Cascading ' . $transferAmount . ' from classid ' . $fromClassid . ' to ' . $toClassid . ' (balance ' . $toClassBalance . ')');

                                if ($fromClassid == 7) {
                                    \Log::debug('[ROBOTICS-CASCADE-DEBUG] Cascading ' . $transferAmount . ' to ' . $toClassid . ' (balance ' . $toClassBalance . ')');
                                }
                                $remainingDiscount -= $transferAmount;

                                // If all discount is transferred, stop
                                if ($remainingDiscount <= 0) {
                                    break;
                                }
                            }
                        }

                        \Log::debug('[DISCOUNT-CASCADE-NONPRI] After non-priority cascade, remaining discount: ' . $remainingDiscount);
                    }
                }
            }

            // If there's still remaining discount, mark as overpayment
            if ($remainingDiscount > 0) {
                if (!isset($remainingDiscountsByClass['overpayment'])) {
                    $remainingDiscountsByClass['overpayment'] = 0;
                }
                $remainingDiscountsByClass['overpayment'] += $remainingDiscount;
            }

            // Reset the original classification discount
            $remainingDiscountsByClass[$fromClassid] = 0;
        }

        \Log::debug('[CASCADE-DEBUG] Final cascadedDiscounts: ' . json_encode($cascadedDiscounts));

        // Apply cascaded discounts sequentially to payment schedules
        // Create a map of schedule items by their position for updating the original array
        $scheduleMap = [];
        foreach ($studentSchedule as $index => $item) {
            // Include tuitiondetail_id or adjustmentdetail_id to make the key unique
            // This prevents standalone adjustments from being confused with regular fees
            $uniqueId = '';
            if (isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment'] === true) {
                $uniqueId = '_adj_' . ($item['adjustmentdetail_id'] ?? $index);
            } else {
                $uniqueId = '_fee_' . ($item['tuitiondetail_id'] ?? $item['laboratory_fee_id'] ?? $item['item_management_id'] ?? $index);
            }
            $key = $item['classid'] . '_' . ($item['duedate'] ?? 'null') . '_' . ($item['paymentno'] ?? 'null') . $uniqueId;
            $scheduleMap[$key] = $index;
        }

        // Sort payment schedules by due date to ensure proper sequential application
        $sortedSchedule = $studentSchedule;
        usort($sortedSchedule, function ($a, $b) {
            $dateA = $a['duedate'] ?? '9999-12-31';
            $dateB = $b['duedate'] ?? '9999-12-31';
            return strcmp($dateA, $dateB);
        });

        // Track how much cascaded discount has been applied to each classification
        // Initialize for priority order AND all other classids in schedule
        $cascadedDiscountApplied = [];
        foreach ($priorityOrder as $classid) {
            $cascadedDiscountApplied[$classid] = 0;
        }
        // Also initialize for any other classids in the schedule (book entries, standalone adjustments, etc.)
        foreach (array_keys($allClassificationsInSchedule) as $classid) {
            if (!isset($cascadedDiscountApplied[$classid])) {
                $cascadedDiscountApplied[$classid] = 0;
            }
        }

        foreach ($sortedSchedule as &$scheduleItem) {
            $classid = $scheduleItem['classid'];
            // Skip item management entries
            if (!empty($scheduleItem['is_item_management'])) {
                continue;
            }
            $currentBalance = $scheduleItem['balance'] ?? 0;

            // If this classification received cascaded discounts
            if ($currentBalance > 0 && isset($cascadedDiscounts[$classid]) && $cascadedDiscounts[$classid] > 0) {
                // Calculate how much discount can be applied to this specific payment schedule
                $availableDiscount = $cascadedDiscounts[$classid] - $cascadedDiscountApplied[$classid];
                $discountToApply = min($availableDiscount, $currentBalance);

                if ($discountToApply > 0) {
                    // Update the balance
                    $scheduleItem['balance'] = $currentBalance - $discountToApply;

                    // Update the amountpay to include the cascaded discount
                    $scheduleItem['amountpay'] = ($scheduleItem['amountpay'] ?? 0) + $discountToApply;

                    // Track how much cascaded discount has been applied to this classification
                    $cascadedDiscountApplied[$classid] += $discountToApply;

                    // Add discount to payment details if it doesn't exist
                    if (!isset($scheduleItem['payment_details'])) {
                        $scheduleItem['payment_details'] = [];
                    }

                    // Check if discount already exists in payment details
                    $discountExists = false;
                    if (isset($scheduleItem['payment_details']) && is_array($scheduleItem['payment_details'])) {
                        foreach ($scheduleItem['payment_details'] as &$paymentDetail) {
                            if (isset($paymentDetail['particulars']) && strpos($paymentDetail['particulars'], 'Discount') !== false) {
                                $paymentDetail['amount'] += $discountToApply;
                                $discountExists = true;
                                break;
                            }
                        }
                    }

                    // If discount doesn't exist, add it
                    if (!$discountExists) {
                        // Get the source classification name from the cascade source map
                        $sourceClassid = $cascadeSourceMap[$classid] ?? null;
                        $discountSourceName = $sourceClassid
                            ? ($classificationNames[$sourceClassid] ?? 'Classification ' . $sourceClassid)
                            : 'Unknown Source';

                        $scheduleItem['payment_details'][] = [
                            'particulars' => 'Cascaded Discount',
                            'amount' => $discountToApply,
                            'discount_source' => $discountSourceName
                        ];
                    }
                }
            }
        }
        unset($scheduleItem); // Break reference

        // Update the original $studentSchedule with cascaded discount changes
        foreach ($sortedSchedule as $sortedItem) {
            // Use the same unique key generation as above
            $uniqueId = '';
            if (isset($sortedItem['is_standalone_adjustment']) && $sortedItem['is_standalone_adjustment'] === true) {
                // Find the original index for this adjustment
                foreach ($studentSchedule as $idx => $schedItem) {
                    if (
                        isset($schedItem['is_standalone_adjustment']) && $schedItem['is_standalone_adjustment'] === true &&
                        $schedItem['classid'] == $sortedItem['classid'] &&
                        $schedItem['duedate'] == $sortedItem['duedate'] &&
                        $schedItem['paymentno'] == $sortedItem['paymentno'] &&
                        ($schedItem['adjustmentdetail_id'] ?? null) == ($sortedItem['adjustmentdetail_id'] ?? null)
                    ) {
                        $uniqueId = '_adj_' . ($sortedItem['adjustmentdetail_id'] ?? $idx);
                        break;
                    }
                }
            } else {
                $uniqueId = '_fee_' . ($sortedItem['tuitiondetail_id'] ?? $sortedItem['laboratory_fee_id'] ?? $sortedItem['item_management_id'] ?? 0);
            }

            $key = $sortedItem['classid'] . '_' . ($sortedItem['duedate'] ?? 'null') . '_' . ($sortedItem['paymentno'] ?? 'null') . $uniqueId;
            if (isset($scheduleMap[$key])) {
                $originalIndex = $scheduleMap[$key];
                $originalBalance = $studentSchedule[$originalIndex]['balance'] ?? 0;
                $newBalance = $sortedItem['balance'] ?? 0;
                $appliedCascadedDiscount = $originalBalance - $newBalance;

                // Update balance, amountpay, and payment details
                $studentSchedule[$originalIndex]['balance'] = $newBalance;
                $studentSchedule[$originalIndex]['amountpay'] = $sortedItem['amountpay'];
                $studentSchedule[$originalIndex]['payment_details'] = $sortedItem['payment_details'] ?? [];

                // Update discount to include cascaded amount
                if ($appliedCascadedDiscount > 0) {
                    $studentSchedule[$originalIndex]['discount'] = ($studentSchedule[$originalIndex]['discount'] ?? 0) + $appliedCascadedDiscount;
                }
            }
        }
    }

    /**
     * Cascade credit adjustments within the same classification
     *
     * @param array $studentSchedule
     * @param array $remainingCreditAdjustmentsByClass
     * @return void
     */
    private static function cascadeCreditAdjustmentsWithinClassification(&$studentSchedule, &$remainingCreditAdjustmentsByClass)
    {
        // Get classification names for credit adjustment source labels
        $classificationNames = DB::table('itemclassification')
            ->select('id', 'description')
            ->get()
            ->keyBy('id')
            ->map(function ($item) {
                return $item->description;
            })
            ->toArray();

        // Group items by classid
        $itemsByClass = [];
        foreach ($studentSchedule as $key => &$item) {
            $classid = $item['classid'];
            if (!isset($itemsByClass[$classid])) {
                $itemsByClass[$classid] = [];
            }
            $itemsByClass[$classid][] = ['key' => $key, 'item' => &$item];
        }
        unset($item); // Break reference after grouping to prevent corruption

        // For each classification, process items in payment number order
        foreach ($itemsByClass as $classid => $items) {
            // Skip if no remaining credit adjustment for this classification
            if (!isset($remainingCreditAdjustmentsByClass[$classid]) || $remainingCreditAdjustmentsByClass[$classid] <= 0) {
                continue;
            }

            $remainingCreditPool = $remainingCreditAdjustmentsByClass[$classid];

            // Get the source classification name
            $sourceClassName = $classificationNames[$classid] ?? 'Classification ' . $classid;

            // Sort items by payment number to process sequentially
            usort($items, function ($a, $b) {
                $paymentNoA = $a['item']['paymentno'] ?? PHP_INT_MAX;
                $paymentNoB = $b['item']['paymentno'] ?? PHP_INT_MAX;
                return $paymentNoA - $paymentNoB;
            });

            // Apply accumulated credit adjustments to subsequent payments
            foreach ($items as &$itemRef) {
                $item = &$itemRef['item'];
                $itemBalance = $item['balance'] ?? 0;

                if ($remainingCreditPool > 0 && $itemBalance > 0) {
                    $creditToApply = min($remainingCreditPool, $itemBalance);

                    // Apply the cascaded credit adjustment
                    $item['balance'] -= $creditToApply;
                    $item['amountpay'] = ($item['amountpay'] ?? 0) + $creditToApply;
                    $item['credit_adjustment'] = ($item['credit_adjustment'] ?? 0) + $creditToApply;

                    // Add to payment details
                    if (!isset($item['payment_details'])) {
                        $item['payment_details'] = [];
                    }
                    $item['payment_details'][] = [
                        'particulars' => 'Cascaded Credit Adjustment',
                        'amount' => $creditToApply,
                        'credit_source' => $sourceClassName
                    ];

                    $remainingCreditPool -= $creditToApply;
                }
            }
            unset($itemRef); // Break reference to prevent corruption
            unset($item); // Break reference to prevent corruption

            // Update the remaining credit adjustment for this classification
            $remainingCreditAdjustmentsByClass[$classid] = $remainingCreditPool;
        }
    }

    /**
     * Cascade any remaining credit adjustments to other fee classifications
     *
     * @param array $remainingCreditAdjustmentsByClass
     * @param array $studentSchedule
     * @return void
     */
    private static function cascadeRemainingCreditAdjustments(&$remainingCreditAdjustmentsByClass, &$studentSchedule)
    {
        // Define priority order for fee classifications (same as discounts)
        $priorityOrder = [
            1, // TUITION FEE (highest priority)
            2, // MISC / OTHER FEES (include item management under classid 2)
            4, // REGISTRATION FEE
            6, // LABORATORY FEES
            9, // COMPUTER FEE
            31, // SIM SYSTEM
            26, // INTRAMURAL'S CONTRIBUTION
            3, // OTHER FEES
            7, // ROBOTICS
        ];

        // Find classifications with remaining credit adjustments
        $classificationsWithCreditAdjustments = [];
        foreach ($remainingCreditAdjustmentsByClass as $classid => $remainingCredit) {
            if ($remainingCredit > 0) {
                $classificationsWithCreditAdjustments[$classid] = $remainingCredit;
            }
        }

        if (empty($classificationsWithCreditAdjustments)) {
            return; // No credit adjustments to cascade
        }

        // Get classification names for credit adjustment source labels
        $classificationNames = DB::table('itemclassification')
            ->select('id', 'description')
            ->get()
            ->keyBy('id')
            ->map(function ($item) {
                return $item->description;
            })
            ->toArray();

        // Helper function to recalculate classifications with balances
        $recalculateBalances = function ($schedule) {
            $balances = [];
            $allClassids = [];
            foreach ($schedule as $scheduleItem) {
                $classid = $scheduleItem['classid'];
                $balance = $scheduleItem['balance'] ?? 0;

                if (!isset($allClassids[$classid])) {
                    $allClassids[$classid] = true;
                }

                if ($balance > 0) {
                    if (!isset($balances[$classid])) {
                        $balances[$classid] = 0;
                    }
                    $balances[$classid] += $balance;
                }
            }
            return [$balances, $allClassids];
        };

        // Get original balances for fair distribution
        list($classificationsWithBalances, $allClassificationsInSchedule) = $recalculateBalances($studentSchedule);
        $originalClassificationsWithBalances = $classificationsWithBalances;
        $originalAllClassificationsInSchedule = $allClassificationsInSchedule;

        // Sort by priority order
        $sortedClassifications = [];
        foreach ($priorityOrder as $classid) {
            if (isset($classificationsWithCreditAdjustments[$classid])) {
                $sortedClassifications[$classid] = $classificationsWithCreditAdjustments[$classid];
            }
        }

        // Track cascaded credit adjustments per classification
        $cascadedCreditAdjustments = [];
        foreach ($priorityOrder as $classid) {
            $cascadedCreditAdjustments[$classid] = 0;
        }
        foreach (array_keys($allClassificationsInSchedule) as $classid) {
            if (!isset($cascadedCreditAdjustments[$classid])) {
                $cascadedCreditAdjustments[$classid] = 0;
            }
        }

        // Track cascade source mapping
        $cascadeSourceMap = [];

        // Apply credit adjustments directly to payment schedules of the same classification first
        foreach ($sortedClassifications as $fromClassid => $creditAmount) {
            if ($creditAmount <= 0)
                continue;

            $sourceClassName = $classificationNames[$fromClassid] ?? 'Classification ' . $fromClassid;

            // Apply the credit adjustment directly to same classification
            $remainingCredit = $creditAmount;
            $creditAppliedToSameClass = 0;

            foreach ($studentSchedule as &$scheduleItem) {
                if ($scheduleItem['classid'] == $fromClassid) {
                    $currentBalance = $scheduleItem['balance'] ?? 0;
                    if ($currentBalance > 0 && $remainingCredit > 0) {
                        $creditToApply = min($remainingCredit, $currentBalance);
                        $scheduleItem['balance'] = $currentBalance - $creditToApply;
                        $scheduleItem['amountpay'] = ($scheduleItem['amountpay'] ?? 0) + $creditToApply;
                        $remainingCredit -= $creditToApply;
                        $creditAppliedToSameClass += $creditToApply;

                        // Add credit adjustment to payment details
                        if (!isset($scheduleItem['payment_details'])) {
                            $scheduleItem['payment_details'] = [];
                        }
                        $scheduleItem['payment_details'][] = [
                            'particulars' => 'Credit Adjustment',
                            'amount' => $creditToApply,
                            'credit_source' => $sourceClassName
                        ];
                    }
                }
            }
            unset($scheduleItem);

            // Reduce credit_adjustment field on source items if cascaded out
            $creditCascadedOut = $creditAmount - $creditAppliedToSameClass;
            if ($creditCascadedOut > 0) {
                foreach ($studentSchedule as &$scheduleItem) {
                    if ($scheduleItem['classid'] == $fromClassid) {
                        $currentCredit = $scheduleItem['credit_adjustment'] ?? 0;
                        $scheduleItem['credit_adjustment'] = max(0, $currentCredit - $creditCascadedOut);
                    }
                }
                unset($scheduleItem);
            }

            // Use original balances for cascading
            $classificationsWithBalances = $originalClassificationsWithBalances;
            $allClassificationsInSchedule = $originalAllClassificationsInSchedule;

            // If credit still remains, cascade to other classifications by priority
            if ($remainingCredit > 0) {
                // Cascade to other classifications in priority order
                foreach ($priorityOrder as $toClassid) {
                    if ($remainingCredit <= 0)
                        break;
                    if ($toClassid == $fromClassid)
                        continue; // Skip same class

                    // Check if this classification has balance
                    if (!isset($classificationsWithBalances[$toClassid]) || $classificationsWithBalances[$toClassid] <= 0) {
                        continue;
                    }

                    // Calculate how much credit can be cascaded to this classification
                    $maxCascadeToClass = $classificationsWithBalances[$toClassid];
                    $creditToCascade = min($remainingCredit, $maxCascadeToClass);

                    if ($creditToCascade > 0) {
                        $cascadedCreditAdjustments[$toClassid] += $creditToCascade;
                        $remainingCredit -= $creditToCascade;
                        $cascadeSourceMap[$toClassid] = $fromClassid;
                    }
                }

                // If still remaining, cascade to all other items in schedule (including book entries, adjustments)
                if ($remainingCredit > 0) {
                    $allOtherClassids = array_diff(array_keys($allClassificationsInSchedule), $priorityOrder);
                    foreach ($allOtherClassids as $toClassid) {
                        if ($remainingCredit <= 0)
                            break;

                        if (isset($classificationsWithBalances[$toClassid]) && $classificationsWithBalances[$toClassid] > 0) {
                            $maxCascadeToClass = $classificationsWithBalances[$toClassid];
                            $creditToCascade = min($remainingCredit, $maxCascadeToClass);

                            if ($creditToCascade > 0) {
                                $cascadedCreditAdjustments[$toClassid] += $creditToCascade;
                                $remainingCredit -= $creditToCascade;
                                $cascadeSourceMap[$toClassid] = $fromClassid;
                            }
                        }
                    }
                }
            }

            // Update remaining credit adjustments
            $remainingCreditAdjustmentsByClass[$fromClassid] = $remainingCredit;
        }

        // Apply cascaded credit adjustments to schedule items by priority
        foreach ($priorityOrder as $classid) {
            if (!isset($cascadedCreditAdjustments[$classid]) || $cascadedCreditAdjustments[$classid] <= 0) {
                continue;
            }

            $creditToApply = $cascadedCreditAdjustments[$classid];
            $sourceClassid = $cascadeSourceMap[$classid] ?? null;
            $creditSourceName = $sourceClassid
                ? ($classificationNames[$sourceClassid] ?? 'Classification ' . $sourceClassid)
                : 'Unknown Source';

            foreach ($studentSchedule as &$scheduleItem) {
                if ($scheduleItem['classid'] == $classid && $creditToApply > 0) {
                    $currentBalance = $scheduleItem['balance'] ?? 0;
                    if ($currentBalance > 0) {
                        $creditApply = min($creditToApply, $currentBalance);
                        $scheduleItem['balance'] = $currentBalance - $creditApply;
                        $scheduleItem['amountpay'] = ($scheduleItem['amountpay'] ?? 0) + $creditApply;
                        $creditToApply -= $creditApply;

                        // Add to payment details
                        if (!isset($scheduleItem['payment_details'])) {
                            $scheduleItem['payment_details'] = [];
                        }
                        $scheduleItem['payment_details'][] = [
                            'particulars' => 'Cascaded Credit Adjustment',
                            'amount' => $creditApply,
                            'credit_source' => $creditSourceName
                        ];
                    }
                }
            }
            unset($scheduleItem);
        }

        // Apply to any other classifications not in priority order
        $allOtherClassids = array_diff(array_keys($allClassificationsInSchedule), $priorityOrder);
        foreach ($allOtherClassids as $classid) {
            if (!isset($cascadedCreditAdjustments[$classid]) || $cascadedCreditAdjustments[$classid] <= 0) {
                continue;
            }

            $creditToApply = $cascadedCreditAdjustments[$classid];
            $sourceClassid = $cascadeSourceMap[$classid] ?? null;
            $creditSourceName = $sourceClassid
                ? ($classificationNames[$sourceClassid] ?? 'Classification ' . $sourceClassid)
                : 'Unknown Source';

            foreach ($studentSchedule as &$scheduleItem) {
                if ($scheduleItem['classid'] == $classid && $creditToApply > 0) {
                    $currentBalance = $scheduleItem['balance'] ?? 0;
                    if ($currentBalance > 0) {
                        $creditApply = min($creditToApply, $currentBalance);
                        $scheduleItem['balance'] = $currentBalance - $creditApply;
                        $scheduleItem['amountpay'] = ($scheduleItem['amountpay'] ?? 0) + $creditApply;
                        $creditToApply -= $creditApply;

                        // Add to payment details
                        if (!isset($scheduleItem['payment_details'])) {
                            $scheduleItem['payment_details'] = [];
                        }
                        $scheduleItem['payment_details'][] = [
                            'particulars' => 'Cascaded Credit Adjustment',
                            'amount' => $creditApply,
                            'credit_source' => $creditSourceName
                        ];
                    }
                }
            }
            unset($scheduleItem);
        }
    }

    /**
     * Cascade excess payments across priority fees similar to discount cascading
     *
     * @param array $studentSchedule
     * @param int $studid Student ID
     * @param int $schoolYear School Year ID
     * @param int $semester Semester ID
     * @return array Array with 'remaining' and 'original_excess' keys
     */
    private static function cascadeExcessPayments(&$studentSchedule, $studid, $schoolYear, $semester)
    {
        // Debug: Count standalone adjustments at start
        $adjCountStart = 0;
        foreach ($studentSchedule as $item) {
            if (isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment'] === true) {
                $adjCountStart++;
                \Log::debug('[CASCADE-EXCESS-START] Found adjustment: classid=' . ($item['classid'] ?? 'none') . ', particulars=' . ($item['particulars'] ?? 'none'));
            }
        }
        \Log::debug('[CASCADE-EXCESS-START] Schedule count: ' . count($studentSchedule) . ', Adjustments: ' . $adjCountStart);

        // Define priority order for fee classifications (same as discount priority)
        $priorityOrder = [
            1, // TUITION FEE (highest priority)
            2, // MISC / OTHER FEES (include item management under classid 2)
            4, // REGISTRATION FEE
            6, // LABORATORY FEES
            9, // COMPUTER FEE
            31, // SIM SYSTEM
            26, // INTRAMURAL'S CONTRIBUTION
            3, // OTHER FEES
            7, // ROBOTICS
        ];

        // Get classification names for tracking
        $classificationNames = DB::table('itemclassification')
            ->select('id', 'description')
            ->get()
            ->keyBy('id')
            ->map(function ($item) {
                return $item->description;
            })
            ->toArray();

        // Get payment type information to identify CASH vs non-CASH payments
        $cashPaymentTypeId = DB::table('paymenttype')
            ->where('description', 'CASH')
            ->value('id');

        // Calculate overpayment from non-cash excess amounts (amounttendered - totalamount)
        // For non-cash payments, excess should be treated as overpayment
        // For cash payments, excess is treated as change (not overpayment)
        $transactionsWithExcess = DB::table('chrngtrans as ct')
            ->select(
                'ct.transno',
                'ct.ornum',
                'ct.totalamount',
                'ct.amounttendered',
                'ct.paymenttype_id',
                'ct.other_paymenttype_ids',
                DB::raw('(ct.amounttendered - ct.totalamount) as excess')
            )
            ->where('ct.studid', $studid)
            ->where('ct.syid', $schoolYear)
            ->where(function ($q) use ($semester) {
                // For basic level students (semester is null), match all transactions
                // For semester-based students, match specified semester OR null semester
                if ($semester !== null) {
                    $q->where('ct.semid', $semester)
                        ->orWhereNull('ct.semid');
                }
                // If semester is null, don't filter by semid (match all)
            })
            ->where('ct.cancelled', 0)
            ->whereRaw('(ct.amounttendered - ct.totalamount) > 0') // Only transactions with excess
            ->get();

        // Get the first classid for each transaction (the one that should receive the overpayment)
        $firstClassidByTransno = [];
        foreach ($transactionsWithExcess as $transaction) {
            if (!isset($firstClassidByTransno[$transaction->transno])) {
                // Get the first classid for this transaction from chrngcashtrans
                $firstCashTrans = DB::table('chrngcashtrans')
                    ->where('transno', $transaction->transno)
                    ->where('syid', $schoolYear)
                    ->where('deleted', 0)
                    ->where('classid', '>', 0)
                    ->where('particulars', 'NOT LIKE', '%BOOK%')
                    ->when($semester !== null, function ($q) use ($semester) {
                        $q->where(function ($subQ) use ($semester) {
                            $subQ->where('semid', $semester)->orWhereNull('semid');
                        });
                    })
                    ->orderBy('id', 'asc')
                    ->first();

                if ($firstCashTrans) {
                    $firstClassidByTransno[$transaction->transno] = $firstCashTrans->classid;
                }
            }
        }

        // Process non-cash excess payments and add to excessPaymentsByClass
        $nonCashExcessByClass = [];
        foreach ($transactionsWithExcess as $transaction) {
            $excessAmount = (float) $transaction->excess;
            if ($excessAmount <= 0) {
                continue;
            }

            // Check if this is a non-cash payment
            $isCashPayment = false;
            $paymentTypeIds = [];

            // Check primary payment type
            if ($transaction->paymenttype_id) {
                $paymentTypeIds[] = $transaction->paymenttype_id;
                if ($transaction->paymenttype_id == $cashPaymentTypeId) {
                    $isCashPayment = true;
                }
            }

            // Check other payment types
            if ($transaction->other_paymenttype_ids) {
                $otherIds = json_decode($transaction->other_paymenttype_ids, true);
                if (is_array($otherIds)) {
                    $paymentTypeIds = array_merge($paymentTypeIds, $otherIds);
                    if (in_array($cashPaymentTypeId, $otherIds)) {
                        $isCashPayment = true;
                    }
                }
            }

            // Only process non-cash payments (excess from cash is treated as change, not overpayment)
            if (!$isCashPayment) {
                $targetClassid = $firstClassidByTransno[$transaction->transno] ?? null;

                if ($targetClassid) {
                    if (!isset($nonCashExcessByClass[$targetClassid])) {
                        $nonCashExcessByClass[$targetClassid] = 0;
                    }
                    $nonCashExcessByClass[$targetClassid] += $excessAmount;

                    \Log::debug("[NON-CASH-EXCESS] OR {$transaction->ornum}: Excess={$excessAmount}, Target Classid={$targetClassid}, PaymentTypes=" . json_encode($paymentTypeIds));
                } else {
                    \Log::debug("[NON-CASH-EXCESS] OR {$transaction->ornum}: Excess={$excessAmount}, but no target classid found");
                }
            }
        }

        // Get RAW payment totals from chrngcashtrans (before distribution)
        // Exclude BOOK payments (they have particulars containing "BOOK")
        $rawPayments = DB::table('chrngtrans as ct')
            ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
            ->select('cct.classid', DB::raw('SUM(cct.amount) as total_paid'))
            ->where('ct.studid', $studid)
            ->where('ct.syid', $schoolYear)
            ->where('cct.syid', $schoolYear)
            ->where(function ($q) use ($semester) {
                // For basic level students (semester is null), match all transactions
                // For semester-based students, match specified semester OR null semester
                if ($semester !== null) {
                    $q->where('ct.semid', $semester)
                        ->orWhereNull('ct.semid');
                }
                // If semester is null, don't filter by semid (match all)
            })
            ->when($semester !== null, function ($q) use ($semester) {
                $q->where(function ($subQ) use ($semester) {
                    $subQ->where('cct.semid', $semester)
                        ->orWhereNull('cct.semid');
                });
            })
            ->where('ct.cancelled', 0)
            ->where('cct.deleted', 0)
            ->where('cct.classid', '>', 0) // Exclude outside fees
            ->where('cct.particulars', 'NOT LIKE', '%BOOK%') // Exclude book payments
            ->groupBy('cct.classid')
            ->get()
            ->keyBy('classid');

        // Get RAW payment totals for NON-CASH transactions only (primary AND other payment types exclude CASH)
        $rawNonCashPayments = DB::table('chrngtrans as ct')
            ->join('chrngcashtrans as cct', 'ct.transno', '=', 'cct.transno')
            ->select('cct.classid', DB::raw('SUM(cct.amount) as total_paid'))
            ->where('ct.studid', $studid)
            ->where('ct.syid', $schoolYear)
            ->where('cct.syid', $schoolYear)
            ->where(function ($q) use ($semester) {
                // For basic level students (semester is null), match all transactions
                // For semester-based students, match specified semester OR null semester
                if ($semester !== null) {
                    $q->where('ct.semid', $semester)
                        ->orWhereNull('ct.semid');
                }
                // If semester is null, don't filter by semid (match all)
            })
            ->when($semester !== null, function ($q) use ($semester) {
                $q->where(function ($subQ) use ($semester) {
                    $subQ->where('cct.semid', $semester)
                        ->orWhereNull('cct.semid');
                });
            })
            ->where('ct.cancelled', 0)
            ->where('cct.deleted', 0)
            ->where('cct.classid', '>', 0)
            ->where('cct.particulars', 'NOT LIKE', '%BOOK%')
            ->where(function ($q) use ($cashPaymentTypeId) {
                $q->whereNull('ct.paymenttype_id')
                    ->orWhere('ct.paymenttype_id', '<>', $cashPaymentTypeId);
            })
            ->where(function ($q) use ($cashPaymentTypeId) {
                $q->whereNull('ct.other_paymenttype_ids')
                    ->orWhereRaw('(ct.other_paymenttype_ids NOT LIKE ? AND ct.other_paymenttype_ids NOT LIKE ?)', ['%"' . $cashPaymentTypeId . '"%', '%' . $cashPaymentTypeId . '%']);
            })
            ->groupBy('cct.classid')
            ->get()
            ->keyBy('classid');

        \Log::debug('[RAW-PAYMENTS] Total payments by class: ' . json_encode($rawPayments));

        // Calculate excess payments per classification
        $excessPaymentsByClass = [];
        $itemsByClass = [];
        $classIsLaboratory = [];

        // Group items by classid
        // NOTE: Standalone adjustments ARE included so they can RECEIVE cascaded payments
        // BUT they won't generate excess payments (handled in the calculation loop below)
        foreach ($studentSchedule as $key => &$item) {
            $classid = $item['classid'];

            if (!isset($itemsByClass[$classid])) {
                $itemsByClass[$classid] = [];
            }
            $itemsByClass[$classid][] = ['key' => $key, 'item' => &$item];

            // Track laboratory classifications (any schedule item with laboratory_fee_id flag)
            if (!isset($classIsLaboratory[$classid])) {
                if (!empty($item['laboratory_fee_id']) || (!empty($item['is_laboratory_fee']))) {
                    $classIsLaboratory[$classid] = true;
                }
            }

            // Debug: Log item details for classid 2 and 3
            if (($classid == 2 || $classid == 3) && $studid == 1) {
                \Log::debug("[ITEMS-BY-CLASS-DEBUG] Student {$studid}, classid {$classid}: " . json_encode([
                    'key' => $key,
                    'particulars' => substr($item['particulars'] ?? '', 0, 50),
                    'balance' => $item['balance'] ?? 0,
                    'is_item_management' => $item['is_item_management'] ?? false,
                    'item_management_id' => $item['item_management_id'] ?? null,
                    'is_standalone_adjustment' => $item['is_standalone_adjustment'] ?? false,
                ]));
            }
        }
        unset($item); // Break reference after grouping to prevent corruption

        // Calculate excess for each classification using RAW payments
        foreach ($itemsByClass as $classid => $items) {
            // Skip non-numeric classids (books have classid like "BOOK_960")
            if (!is_numeric($classid)) {
                continue;
            }

            // Check if ALL items in this classid are standalone adjustments
            // If so, skip excess calculation (adjustments don't generate excess, only receive cascaded payments)
            $allItemsAreAdjustments = true;
            foreach ($items as $itemRef) {
                if (!isset($itemRef['item']['is_standalone_adjustment']) || $itemRef['item']['is_standalone_adjustment'] !== true) {
                    $allItemsAreAdjustments = false;
                    break;
                }
            }

            if ($allItemsAreAdjustments) {
                \Log::debug("[EXCESS-CALCULATION-SKIP] Classid {$classid}: All items are standalone adjustments, skipping excess calculation");
                continue;
            }

            $totalAmount = 0;
            $totalDiscounts = 0;
            $totalCreditAdjustments = 0;

            // Sum up amounts, discounts, and credits from schedule
            // EXCLUDE standalone adjustment items from the totals (they don't participate in excess calculation)
            foreach ($items as $itemRef) {
                $item = $itemRef['item'];

                // Skip standalone adjustments when calculating totals for excess
                if (isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment'] === true) {
                    continue;
                }

                $totalAmount += $item['amount'] ?? 0;
                $totalDiscounts += $item['discount'] ?? 0;
                $totalCreditAdjustments += $item['credit_adjustment'] ?? 0;
            }

            // Get ACTUAL payment total from RAW data (not from distributed payment_details)
            $totalActualPayments = 0;
            $isLabClass = !empty($classIsLaboratory[$classid]);

            if ($isLabClass) {
                // For laboratory fees, only allow cascading when ALL payment types exclude CASH
                if (isset($rawNonCashPayments[$classid])) {
                    $totalActualPayments = (float) $rawNonCashPayments[$classid]->total_paid;
                }
            } else {
                if (isset($rawPayments[$classid])) {
                    $totalActualPayments = (float) $rawPayments[$classid]->total_paid;
                }
            }

            // Calculate excess: (actual payments) - (amount - discounts - credit adjustments)
            // Excess occurs when payments exceed the net amount due after discounts and credits
            $netAmountDue = $totalAmount - $totalDiscounts - $totalCreditAdjustments;
            $excessPayment = $totalActualPayments - $netAmountDue;

            if ($excessPayment > 0) {
                $excessPaymentsByClass[$classid] = $excessPayment;
                \Log::debug("[EXCESS-PAYMENT] Class {$classid}: Total Amount={$totalAmount}, RAW Payments={$totalActualPayments}, Discounts={$totalDiscounts}, Credits={$totalCreditAdjustments}, Net Due={$netAmountDue}, Excess={$excessPayment}");
            }
        }

        // Merge non-cash excess payments into excessPaymentsByClass
        foreach ($nonCashExcessByClass as $classid => $nonCashExcess) {
            if (!isset($excessPaymentsByClass[$classid])) {
                $excessPaymentsByClass[$classid] = 0;
            }
            $excessPaymentsByClass[$classid] += $nonCashExcess;
            \Log::debug("[NON-CASH-EXCESS-MERGED] Class {$classid}: Added non-cash excess {$nonCashExcess}, Total excess now: {$excessPaymentsByClass[$classid]}");
        }

        // Log all excess payments found
        if (!empty($excessPaymentsByClass)) {
            \Log::debug("[EXCESS-PAYMENT-SUMMARY] Found excess payments: " . json_encode($excessPaymentsByClass));
        } else {
            \Log::debug("[EXCESS-PAYMENT-SUMMARY] No excess payments found to cascade");
        }

        // Cascade excess payments to next priority fees with balances
        $cascadeSourceMap = [];
        $cascadedPayments = [];

        // Initialize cascaded payments tracking
        foreach ($priorityOrder as $classid) {
            $cascadedPayments[$classid] = 0;
        }
        foreach (array_keys($itemsByClass) as $classid) {
            if (!isset($cascadedPayments[$classid])) {
                $cascadedPayments[$classid] = 0;
            }
        }

        // Sort excess payments by priority
        $sortedExcessPayments = [];
        foreach ($priorityOrder as $classid) {
            if (isset($excessPaymentsByClass[$classid])) {
                $sortedExcessPayments[$classid] = $excessPaymentsByClass[$classid];
            }
        }

        // Also add non-priority classids with excess (they should cascade to priority classids with balances)
        foreach ($excessPaymentsByClass as $classid => $excessAmount) {
            if (!in_array($classid, $priorityOrder) && $excessAmount > 0) {
                $sortedExcessPayments[$classid] = $excessAmount;
                \Log::debug("[EXCESS-CASCADE-NONPRIORITY] Adding non-priority classid {$classid} with excess {$excessAmount}");
            }
        }

        \Log::debug("[EXCESS-CASCADE-START] Processing " . count($sortedExcessPayments) . " classifications with excess");

        // Process each classification with excess payment
        foreach ($sortedExcessPayments as $fromClassid => $excessAmount) {
            if ($excessAmount <= 0)
                continue;

            $remainingExcess = $excessAmount;
            $fromIndex = array_search($fromClassid, $priorityOrder);

            // IMPORTANT: First cascade excess to remaining unpaid balances in THE SAME classid
            // This ensures all payment numbers within the same classification are filled before moving to other priorities
            if (isset($itemsByClass[$fromClassid])) {
                $sameClassBalance = 0;
                foreach ($itemsByClass[$fromClassid] as $itemRef) {
                    $sameClassBalance += $itemRef['item']['balance'] ?? 0;
                }

                if ($sameClassBalance > 0 && $remainingExcess > 0) {
                    $transferAmount = min($remainingExcess, $sameClassBalance);
                    $cascadedPayments[$fromClassid] += $transferAmount;
                    $cascadeSourceMap[$fromClassid] = $fromClassid; // Same class cascade
                    $remainingExcess -= $transferAmount;

                    \Log::debug("[EXCESS-CASCADE-SAME-CLASS] Classid {$fromClassid}: Cascading {$transferAmount} to remaining balances in same class, Remaining excess: {$remainingExcess}");
                }
            }

            // If this is a priority classid, cascade to ANY classid with balance
            if ($fromIndex !== false) {
                // First, cascade through HIGHER priority classids (to pay overdue fees first)
                for ($i = 0; $i < $fromIndex; $i++) {
                    $toClassid = $priorityOrder[$i];

                    // Calculate remaining balance for target classification
                    $toClassBalance = 0;
                    if (isset($itemsByClass[$toClassid])) {
                        foreach ($itemsByClass[$toClassid] as $itemRef) {
                            $toClassBalance += $itemRef['item']['balance'] ?? 0;
                        }
                    }

                    \Log::debug("[EXCESS-CASCADE-UP] From {$fromClassid} to {$toClassid} (higher priority): toClassBalance={$toClassBalance}, remainingExcess={$remainingExcess}");

                    if ($toClassBalance > 0 && $remainingExcess > 0) {
                        $transferAmount = min($remainingExcess, $toClassBalance);
                        $cascadedPayments[$toClassid] += $transferAmount;
                        $cascadeSourceMap[$toClassid] = $fromClassid;
                        $remainingExcess -= $transferAmount;

                        \Log::debug("[EXCESS-CASCADE-UP] From {$fromClassid} to {$toClassid}: {$transferAmount}");

                        if ($remainingExcess <= 0) {
                            break;
                        }
                    }
                }

                // Then, cascade through LOWER priority classids
                if ($remainingExcess > 0) {
                    for ($i = $fromIndex + 1; $i < count($priorityOrder); $i++) {
                        $toClassid = $priorityOrder[$i];

                        // Calculate remaining balance for target classification
                        $toClassBalance = 0;
                        $itemCount = 0;
                        if (isset($itemsByClass[$toClassid])) {
                            $itemCount = count($itemsByClass[$toClassid]);
                            foreach ($itemsByClass[$toClassid] as $itemRef) {
                                $toClassBalance += $itemRef['item']['balance'] ?? 0;
                            }
                        }

                        \Log::debug("[EXCESS-CASCADE-CHECK] From {$fromClassid} to {$toClassid}: toClassBalance={$toClassBalance}, remainingExcess={$remainingExcess}, itemCount={$itemCount}");

                        if ($toClassBalance > 0 && $remainingExcess > 0) {
                            $transferAmount = min($remainingExcess, $toClassBalance);
                            $cascadedPayments[$toClassid] += $transferAmount;
                            $cascadeSourceMap[$toClassid] = $fromClassid;
                            $remainingExcess -= $transferAmount;

                            \Log::debug("[EXCESS-CASCADE] From {$fromClassid} to {$toClassid}: {$transferAmount}");

                            if ($remainingExcess <= 0) {
                                break;
                            }
                        }
                    }
                }

                // If still has excess, cascade to non-priority classids (books, etc.)
                if ($remainingExcess > 0) {
                    \Log::debug("[EXCESS-CASCADE-NONPRI] Student {$studid}, From classid {$fromClassid}, Remaining excess: {$remainingExcess}, Checking non-priority classids");
                    foreach (array_keys($itemsByClass) as $toClassid) {
                        if (in_array($toClassid, $priorityOrder)) {
                            continue;
                        }

                        $toClassBalance = 0;
                        $itemCount = count($itemsByClass[$toClassid]);
                        foreach ($itemsByClass[$toClassid] as $itemRef) {
                            $toClassBalance += $itemRef['item']['balance'] ?? 0;
                        }

                        \Log::debug("[EXCESS-CASCADE-NONPRI-CHECK] Student {$studid}, To classid {$toClassid}: balance={$toClassBalance}, itemCount={$itemCount}");

                        if ($toClassBalance > 0 && $remainingExcess > 0) {
                            $transferAmount = min($remainingExcess, $toClassBalance);
                            $cascadedPayments[$toClassid] += $transferAmount;
                            $cascadeSourceMap[$toClassid] = $fromClassid;
                            $remainingExcess -= $transferAmount;

                            \Log::debug("[EXCESS-CASCADE-NONPRI-TRANSFER] Student {$studid}, From {$fromClassid} to {$toClassid}: transferred={$transferAmount}, remaining={$remainingExcess}");

                            if ($remainingExcess <= 0) {
                                break;
                            }
                        }
                    }
                }
            } else {
                // This is a NON-PRIORITY classid with excess - cascade to priority classids first
                \Log::debug("[EXCESS-CASCADE-NONPRIORITY] Cascading from non-priority classid {$fromClassid} with excess {$remainingExcess}");

                // First, cascade through ALL priority order classids (starting from highest priority)
                foreach ($priorityOrder as $toClassid) {
                    if ($remainingExcess <= 0)
                        break;

                    $toClassBalance = 0;
                    if (isset($itemsByClass[$toClassid])) {
                        foreach ($itemsByClass[$toClassid] as $itemRef) {
                            $toClassBalance += $itemRef['item']['balance'] ?? 0;
                        }
                    }

                    \Log::debug("[EXCESS-CASCADE-CHECK-NONPRI] From {$fromClassid} to {$toClassid}: toClassBalance={$toClassBalance}, remainingExcess={$remainingExcess}");

                    if ($toClassBalance > 0 && $remainingExcess > 0) {
                        $transferAmount = min($remainingExcess, $toClassBalance);
                        $cascadedPayments[$toClassid] += $transferAmount;
                        $cascadeSourceMap[$toClassid] = $fromClassid;
                        $remainingExcess -= $transferAmount;

                        \Log::debug("[EXCESS-CASCADE-NONPRI] From {$fromClassid} to {$toClassid}: {$transferAmount}");
                    }
                }

                // Then, cascade to other non-priority classids with balances
                if ($remainingExcess > 0) {
                    foreach (array_keys($itemsByClass) as $toClassid) {
                        if (in_array($toClassid, $priorityOrder) || $toClassid == $fromClassid) {
                            continue;
                        }

                        $toClassBalance = 0;
                        foreach ($itemsByClass[$toClassid] as $itemRef) {
                            $toClassBalance += $itemRef['item']['balance'] ?? 0;
                        }

                        if ($toClassBalance > 0 && $remainingExcess > 0) {
                            $transferAmount = min($remainingExcess, $toClassBalance);
                            $cascadedPayments[$toClassid] += $transferAmount;
                            $cascadeSourceMap[$toClassid] = $fromClassid;
                            $remainingExcess -= $transferAmount;

                            if ($remainingExcess <= 0) {
                                break;
                            }
                        }
                    }
                }
            }
        }

        // Log final cascade allocation summary for student 1
        if ($studid == 1) {
            \Log::debug("[CASCADE-ALLOCATION-SUMMARY] Student {$studid}, Total cascaded payments calculated: " . json_encode($cascadedPayments));
        }

        // Apply cascaded payments to schedule
        $cascadedPaymentsApplied = [];
        foreach ($priorityOrder as $classid) {
            $cascadedPaymentsApplied[$classid] = 0;
        }
        foreach (array_keys($itemsByClass) as $classid) {
            if (!isset($cascadedPaymentsApplied[$classid])) {
                $cascadedPaymentsApplied[$classid] = 0;
            }
        }

        // Track how much each source classid cascaded OUT
        $cascadedOutFromSource = [];
        foreach ($priorityOrder as $classid) {
            $cascadedOutFromSource[$classid] = 0;
        }
        foreach (array_keys($itemsByClass) as $classid) {
            if (!isset($cascadedOutFromSource[$classid])) {
                $cascadedOutFromSource[$classid] = 0;
            }
        }

        foreach ($studentSchedule as &$scheduleItem) {
            $classid = $scheduleItem['classid'];

            // NOTE: We DO NOT skip standalone adjustments here
            // They should RECEIVE cascaded payments to reduce their balance

            $currentBalance = $scheduleItem['balance'] ?? 0;

            if ($currentBalance > 0 && isset($cascadedPayments[$classid]) && $cascadedPayments[$classid] > 0) {
                // Calculate available cascaded payment for this item
                $availablePayment = $cascadedPayments[$classid] - $cascadedPaymentsApplied[$classid];
                $paymentToApply = min($availablePayment, $currentBalance);

                if ($paymentToApply > 0) {
                    $scheduleItem['balance'] -= $paymentToApply;
                    $scheduleItem['amountpay'] = ($scheduleItem['amountpay'] ?? 0) + $paymentToApply;

                    // Track applied amount
                    $cascadedPaymentsApplied[$classid] += $paymentToApply;

                    // Add to payment details
                    if (!isset($scheduleItem['payment_details'])) {
                        $scheduleItem['payment_details'] = [];
                    }

                    // Get source classification name
                    $sourceClassid = $cascadeSourceMap[$classid] ?? null;
                    $sourceName = $sourceClassid
                        ? ($classificationNames[$sourceClassid] ?? 'Classification ' . $sourceClassid)
                        : 'Excess Payment';

                    // If cascading within same class, use different message
                    $particularsMessage = ($sourceClassid == $classid)
                        ? 'Payment from earlier installment'
                        : 'Cascaded Payment from ' . $sourceName;

                    $scheduleItem['payment_details'][] = [
                        'particulars' => $particularsMessage,
                        'amount' => $paymentToApply,
                        'payment_source' => $sourceName
                    ];

                    // Track how much this source cascaded out
                    if ($sourceClassid) {
                        if (!isset($cascadedOutFromSource[$sourceClassid])) {
                            $cascadedOutFromSource[$sourceClassid] = 0;
                        }
                        $cascadedOutFromSource[$sourceClassid] += $paymentToApply;
                    }
                }
            }
        }
        unset($scheduleItem);

        // Return both remaining overpayments AND original excess amounts
        // Use the tracked cascaded-out amounts from the actual cascading process
        $remainingOverpayments = [];
        foreach ($excessPaymentsByClass as $classid => $excess) {
            $cascadedOut = $cascadedOutFromSource[$classid] ?? 0;
            $remaining = $excess - $cascadedOut;
            \Log::debug("[REMAINING-CALC] Class {$classid}: excess={$excess}, cascadedOut={$cascadedOut}, remaining={$remaining}");
            if ($remaining > 0) {
                $remainingOverpayments[$classid] = $remaining;
            }
        }

        \Log::debug("[REMAINING-SUMMARY] remainingOverpayments: " . json_encode($remainingOverpayments));

        // Debug: Count standalone adjustments at end
        $adjCountEnd = 0;
        foreach ($studentSchedule as $item) {
            if (isset($item['is_standalone_adjustment']) && $item['is_standalone_adjustment'] === true) {
                $adjCountEnd++;
                \Log::debug('[CASCADE-EXCESS-END] Found adjustment: classid=' . ($item['classid'] ?? 'none') . ', particulars=' . ($item['particulars'] ?? 'none'));
            }
        }
        \Log::debug('[CASCADE-EXCESS-END] Schedule count: ' . count($studentSchedule) . ', Adjustments: ' . $adjCountEnd);

        return [
            'remaining' => $remainingOverpayments,
            'original_excess' => $excessPaymentsByClass
        ];
    }

    /**
     * Calculate overpayment for each classification and display it on the last payment
     *
     * @param array $studentSchedule
     * @return void
     */
    private static function calculateOverpaymentPerClassification(&$studentSchedule)
    {
        // Group schedule items by classification
        $itemsByClass = [];
        foreach ($studentSchedule as $key => &$item) {
            $classid = $item['classid'];
            if (!isset($itemsByClass[$classid])) {
                $itemsByClass[$classid] = [];
            }
            $itemsByClass[$classid][] = ['key' => $key, 'item' => &$item];
        }
        unset($item); // Break reference after grouping to prevent corruption

        // For each classification, calculate total overpayment and add to last payment
        foreach ($itemsByClass as $classid => $items) {
            // Calculate total amount, total ACTUAL payments, total discounts, and credit adjustments for this classification
            $totalAmount = 0;
            $totalActualPayments = 0;
            $totalDiscounts = 0;
            $totalCreditAdjustments = 0;
            $totalCascadedPayments = 0;

            foreach ($items as $itemRef) {
                $item = $itemRef['item'];
                $totalAmount += $item['amount'] ?? 0;
                $totalDiscounts += $item['discount'] ?? 0;
                $totalCreditAdjustments += $item['credit_adjustment'] ?? 0;

                // Calculate ACTUAL payments (excluding discounts) from payment_details
                // Only count payments that BELONG to this classification, not cascaded from other classes
                if (isset($item['payment_details']) && is_array($item['payment_details'])) {
                    foreach ($item['payment_details'] as $detail) {
                        // Count payments where the classid matches THIS classification AND it's not a cascaded payment
                        if (isset($detail['classid']) && $detail['classid'] == $classid) {
                            // Actual payments DON'T have 'particulars' field
                            // Cascaded payments HAVE 'particulars' field like 'Cascaded Payment from X'
                            if (!isset($detail['particulars'])) {
                                $totalActualPayments += $detail['amount'] ?? 0;
                            } else {
                                // This is a cascaded payment (either discount or payment from another classification)
                                // Count it as cascaded payment for overpayment calculation
                                if (isset($detail['payment_source'])) {
                                    $totalCascadedPayments += $detail['amount'] ?? 0;
                                }
                            }
                        }
                    }
                }
            }

            // Calculate overpayment: (actual payments + cascaded payments + discounts + credit_adjustments) - amount
            // Discounts, credit adjustments, and cascaded payments serve as payments
            // Total payment = actual payments + cascaded payments + discounts + credit adjustments
            // If total payment exceeds the amount due, that's overpayment
            $totalPaymentValue = $totalActualPayments + $totalCascadedPayments + $totalDiscounts + $totalCreditAdjustments;
            $overpayment = $totalPaymentValue - $totalAmount;

            // Only show overpayment if > 0
            if ($overpayment > 0) {
                // Find the last payment (highest payment number or last item if no payment number)
                $lastItemRef = null;
                $maxPaymentNo = -1;

                foreach ($items as $itemRef) {
                    $paymentNo = $itemRef['item']['paymentno'] ?? 0;
                    if ($paymentNo >= $maxPaymentNo) {
                        $maxPaymentNo = $paymentNo;
                        $lastItemRef = &$itemRef;
                    }
                }

                // Add overpayment to the last item
                if ($lastItemRef !== null) {
                    $lastItemRef['item']['overpayment'] = round($overpayment, 2);
                    \Log::debug("[OVERPAYMENT] Class {$classid}: overpayment {$overpayment} added to last payment");
                }
                unset($lastItemRef); // Break reference to prevent corruption
                unset($itemRef); // Break reference to prevent corruption
            }
        }
    }

    /**
     * Get the latest feesid for a student, checking studinfo then enrollments for the given term.
     */
    private function getLatestFeesId($studid, $levelid, $syid, $semid = null)
    {
        // First, check studinfo.feesid
        $studinfoFeesId = DB::table('studinfo')
            ->where('id', $studid)
            ->value('feesid');

        if ($studinfoFeesId) {
            return $studinfoFeesId;
        }

        $enrollmentTables = [
            'enrolledstud' => ['min' => 1, 'max' => 13, 'use_sem' => false],
            'sh_enrolledstud' => ['min' => 14, 'max' => 15, 'use_sem' => true],
            'college_enrolledstud' => ['min' => 17, 'max' => 25, 'use_sem' => true],
            'tesda_enrolledstud' => ['min' => 26, 'max' => 26, 'use_sem' => false],
        ];

        foreach ($enrollmentTables as $table => $cfg) {
            if ($levelid < $cfg['min'] || $levelid > $cfg['max']) {
                continue;
            }

            $q = DB::table($table)
                ->where('studid', $studid)
                ->where('deleted', 0);

            if ($syid && Schema::hasColumn($table, 'syid')) {
                $q->where('syid', $syid);
            }

            if ($cfg['use_sem'] && $semid && Schema::hasColumn($table, 'semid')) {
                $q->where('semid', $semid);
            }

            $enrollment = $q->orderByDesc('id')->first();
            if ($enrollment && isset($enrollment->feesid)) {
                return $enrollment->feesid;
            }
        }

        return null;
    }
}
