<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use DB;
class AccountsReceivableModel extends Model
{
    public static function allstudents($selectedschoolyear,$selecteddaterange,$selecteddepartment,$selectedgradelevel,$selectedsemester,$selectedsection,$selectedgrantee,$selectedmode)
    {
        $studinfo_1 = DB::table('studinfo')
            ->select(
                'studinfo.id',
                'studinfo.sid',
                'studinfo.firstname',
                'studinfo.middlename',
                'studinfo.lastname',
                'studinfo.suffix',
                'studinfo.gender',
                'studinfo.mol',
                'studinfo.grantee as granteeid',
                'sections.id as sectionid',
                'sections.sectionname',
                'gradelevel.id as levelid',
                'gradelevel.levelname',
                'academicprogram.id as acadprogid',
                'academicprogram.acadprogcode',
                'modeoflearning.description as mol',
                'grantee.description as grantee'
                )
            ->join('enrolledstud', 'studinfo.id','=','enrolledstud.studid')
            ->join('sections', 'studinfo.sectionid','=','sections.id')
            ->join('gradelevel', 'sections.levelid','=','gradelevel.id')
            ->join('academicprogram', 'gradelevel.acadprogid','=','academicprogram.id')
            ->leftJoin('modeoflearning', 'studinfo.mol','=','modeoflearning.id')
            ->leftJoin('grantee', 'studinfo.grantee','=','grantee.id')
            ->where('sections.deleted','0')
            ->where('studinfo.deleted','0')
            ->where('enrolledstud.deleted','0')
            ->where('studinfo.studstatus','!=','0')
            ->where('enrolledstud.syid',$selectedschoolyear)
            // ->take(5)
            ->distinct()
            ->get();
        if(count($studinfo_1) > 0)
        {
            foreach($studinfo_1 as $stud_1){
                $stud_1->units = '';
            }
        }
        $studinfo_2 = DB::table('studinfo')
            ->select(
                'studinfo.id',
                'studinfo.sid',
                'studinfo.firstname',
                'studinfo.middlename',
                'studinfo.lastname',
                'studinfo.suffix',
                'studinfo.gender',
                'studinfo.mol',
                'studinfo.grantee as granteeid',
                'sections.id as sectionid',
                'sections.sectionname',
                'gradelevel.id as levelid',
                'gradelevel.levelname',
                'academicprogram.acadprogcode',
                'academicprogram.id as acadprogid',
                'modeoflearning.description as mol',
                'grantee.description as grantee',
                'sh_enrolledstud.semid'
                )
            ->join('sh_enrolledstud', 'studinfo.id','=','sh_enrolledstud.studid')
            ->join('sections', 'studinfo.sectionid','=','sections.id')
            ->join('gradelevel', 'sections.levelid','=','gradelevel.id')
            ->join('academicprogram', 'gradelevel.acadprogid','=','academicprogram.id')
            ->leftJoin('modeoflearning', 'studinfo.mol','=','modeoflearning.id')
            ->leftJoin('grantee', 'studinfo.grantee','=','grantee.id')
            ->where('sections.deleted','0')
            ->where('studinfo.deleted','0')
            ->where('sh_enrolledstud.deleted','0')
            ->where('studinfo.studstatus','!=','0')
            ->where('sh_enrolledstud.syid',$selectedschoolyear)
            ->where('sh_enrolledstud.semid', $selectedsemester)
            // ->take(5)
            ->distinct()
            ->get();
        if(count($studinfo_2) > 0)
        {
            foreach($studinfo_2 as $stud_2){
                $stud_2->units = '';
            }
        }
        
        if($selectedsemester!=null)
        {
            $studinfo_2 = collect($studinfo_2)->where('semid', $selectedsemester)->values()->all();
        }
        
        $studinfo_3 = DB::table('studinfo')
            ->select(
                'studinfo.id',
                'studinfo.sid',
                'studinfo.firstname',
                'studinfo.middlename',
                'studinfo.lastname',
                'studinfo.suffix',
                'studinfo.gender',
                'studinfo.mol',
                'studinfo.grantee as granteeid',
                'college_enrolledstud.sectionID as sectionid',
                'college_enrolledstud.courseid',
                'college_sections.id as sectionid',
                'college_sections.sectionDesc as sectionname',
                'gradelevel.levelname',
                'gradelevel.id as levelid',
                'academicprogram.id as acadprogid',
                'academicprogram.acadprogcode',
                'modeoflearning.description as mol',
                'grantee.description as grantee',
                'college_enrolledstud.semid'
                )
            ->join('college_enrolledstud', 'studinfo.id','=','college_enrolledstud.studid')
            ->join('college_sections', 'college_enrolledstud.sectionID','=','college_sections.id')
            ->join('gradelevel', 'college_sections.yearID','=','gradelevel.id')
            ->join('academicprogram', 'gradelevel.acadprogid','=','academicprogram.id')
            ->leftJoin('modeoflearning', 'studinfo.mol','=','modeoflearning.id')
            ->leftJoin('grantee', 'studinfo.grantee','=','grantee.id')
            ->where('college_sections.deleted','0')
            ->where('studinfo.deleted','0')
            ->where('college_enrolledstud.deleted','0')
            ->where('studinfo.studstatus','!=','0')
            ->where('college_enrolledstud.syid',$selectedschoolyear)
            // ->take(5)
            ->distinct()
            ->get();
            
            if(count($studinfo_3) > 0)
            {
                foreach($studinfo_3 as $stud_3){
                    $units = 0;
                    $stud_sched = DB::table('college_studsched')
                        ->select('schedid')
                        ->where('studid', $stud_3->id)
                        ->where('deleted','0')
                        ->get();
                    if(count($stud_sched)>0)
                    {
                        foreach($stud_sched as $sched)
                        {
                            $subjects = DB::table('college_classsched')
                                ->where('id', $sched->schedid)
                                ->where('deleted','0')
                                ->get();

                            if(count($subjects) > 0)
                            {
                                foreach($subjects as $subject)
                                {
                                    $unit = DB::table('college_prospectus')
                                        ->where('subjectID', $subject->subjectID)
                                        ->where('courseID', $stud_3->courseid)
                                        ->where('yearID', $stud_3->levelid)
                                        ->where('deleted','0')
                                        ->get();
                                        
                                    if(count($unit)>0)
                                    {
                                        foreach($unit as $addunit){
                                            $units+=$addunit->lecunits;
                                            $units+=$addunit->labunits;
                                        }
                                    }
                                }
                            }

                        }
                    }
                    $stud_3->units = $units;
                }
            }
            if($selectedsemester!=null)
            {
                $studinfo_3 = collect($studinfo_3)->where('semid', $selectedsemester)->values()->all();
            }
            $allItems = collect();
            $allItems = $allItems->merge($studinfo_1);
            $allItems = $allItems->merge($studinfo_2);
            $allItems = $allItems->merge($studinfo_3);
            
            $allItems = $allItems->unique('id');

            if($selecteddepartment != null)
            {
                $allItems = $allItems->where('acadprogid', $selecteddepartment);
            }
            if($selectedgradelevel != null)
            {
                $allItems = $allItems->where('levelid', $selectedgradelevel);
            }
            if($selectedsection != null)
            {
                $allItems = $allItems->where('sectionid', $selectedsection);
            }
            if($selectedgrantee != null)
            {
                $allItems = $allItems->where('granteeid', $selectedgrantee);
            }
            if($selectedmode != null)
            {
                $allItems = $allItems->where('mol', $selectedmode);
            }
            
            if(count($allItems)>0)
            {
                foreach($allItems as $student)
                {
                    $student->totalassessment = self::gettotalassessment($student->id, $selectedsemester, $selectedschoolyear, $selecteddaterange);
                    // return $student->totalassessment;
                    $student->discount = self::gettotaldiscount($student->id, $selectedsemester, $selectedschoolyear, $selecteddaterange);
                    $student->netassessed = $student->totalassessment-$student->discount;
                    $student->totalpayment = self::gettotalpayment($student->id, $selectedsemester, $selectedschoolyear, $selecteddaterange);
                    $student->balance = $student->netassessed-$student->totalpayment;
                }
            }
            return $allItems->sortBy('lastname');
            // return $allItems;
    }
    public static function gettotalassessment($studentid,$semid,$syid,$selecteddaterange)
    {
        $assessments = Db::table('studledger')  
            ->select('amount', 'createddatetime','semid')
            ->where('studid', $studentid)
            ->where('deleted','0')
            ->where('syid',$syid)
            ->get();

        $totalassessments = collect($assessments);
        if($semid != null)
        {
            $totalassessments = $totalassessments->where('semid', $semid);
        }
        if($selecteddaterange != null)
        {
            $daterange = explode(' - ', $selecteddaterange);
            $datefrom = $daterange[0];
            $dateto = $daterange[1];
            $totalassessments = $totalassessments->filter(function ($item) use ($datefrom,$dateto) {
                if($item->createddatetime >= $datefrom && $item->createddatetime <= $dateto)
                {
                    return $item;
                }
            });
        }
        
        $totalassessments = $totalassessments->sum('amount');

        return $totalassessments;
    }

    public static function gettotalpayment($studentid,$semid,$syid,$selecteddaterange)
    {
        $payments = Db::table('studledger')  
            ->select('payment','createddatetime','semid')
            ->where('studid', $studentid)
            ->where('deleted','0')
            ->where('syid',$syid)
            ->where('particulars', 'not like', '%DISCOUNT:%')
            ->get();


        $totalpayments = collect($payments);
        if($semid != null)
        {
            $totalpayments = $totalpayments->where('semid', $semid);
        }
        if($selecteddaterange != null)
        {
            $daterange = explode(' - ', $selecteddaterange);
            $datefrom = $daterange[0];
            $dateto = $daterange[1];
            $totalpayments = $totalpayments->filter(function ($item) use ($datefrom,$dateto) {
                if($item->createddatetime >= $datefrom && $item->createddatetime <= $dateto)
                {
                    return $item;
                }
            });
        }
        
        $totalpayments = $totalpayments->sum('payment');

        return $totalpayments;
    }

    public static function gettotaldiscount($studentid,$semid,$syid,$selecteddaterange)
    {
        $discounts = Db::table('studledger')  
            ->select('payment','createddatetime','semid')
            ->where('studid', $studentid)
            ->where('deleted','0')
            ->where('syid',$syid)
            ->where('particulars', 'like', '%DISCOUNT:%')
            ->get();


        $totaldiscount = collect($discounts);
        if($semid != null)
        {
            $totaldiscount = $totaldiscount->where('semid', $semid);
        }
        if($selecteddaterange != null)
        {
            $daterange = explode(' - ', $selecteddaterange);
            $datefrom = $daterange[0];
            $dateto = $daterange[1];
            $totaldiscount = $totaldiscount->filter(function ($item) use ($datefrom,$dateto) {
                if($item->createddatetime >= $datefrom && $item->createddatetime <= $dateto)
                {
                    return $item;
                }
            });
        }
        
        $totaldiscount = $totaldiscount->sum('payment');

        return $totaldiscount;
    }
}
