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
use App\Models\Finance\AccountsReceivableModel;
class AccountsReceivableController extends Controller
{
    public function index()
    {
        $schoolyears = DB::table('sy')
            ->get();

        $departments = DB::table('academicprogram')
            ->get();

        $gradelevels = DB::table('gradelevel')
            ->where('deleted','0')
            ->orderBy('sortid','asc')
            ->get();

        $semesters = DB::table('semester')
            ->where('deleted','0')
            ->get();

        $modes = DB::table('modeoflearning')
            ->where('deleted','0')
            ->get();

        $grantees = DB::table('grantee')
            ->get();

        return view('finance.accountsreceivable.index')
            ->with('departments', $departments)
            ->with('gradelevels', $gradelevels)
            ->with('semesters', $semesters)
            ->with('schoolyears', $schoolyears)
            ->with('modes', $modes)
            ->with('grantees', $grantees);

    }
    public function default(Request $request)
    {
        $selectedschoolyear = $request->get('selectedschoolyear');
        // $students = array();
        $allstudents = AccountsReceivableModel::allstudents($selectedschoolyear,null,null,null,null,null,null,null);
        $overalltotalassessment     = $allstudents->sum('totalassessment');
        $overalltotaldiscount       = $allstudents->sum('discount');
        $overalltotalnetassessed    = $allstudents->sum('netassessed');
        $overalltotalpayment        = $allstudents->sum('totalpayment');
        $overalltotalbalance        = $allstudents->sum('balance');
        return view('finance.accountsreceivable.accountsreceivabletable')
            ->with('students', $allstudents)
            ->with('overalltotalassessment', $overalltotalassessment)
            ->with('overalltotaldiscount', $overalltotaldiscount)
            ->with('overalltotalnetassessed', $overalltotalnetassessed)
            ->with('overalltotalpayment', $overalltotalpayment)
            ->with('overalltotalbalance', $overalltotalbalance);
    }
    public function getsections (Request $request)
    {
        if($request->get('selectedgradelevel') != null)
        {
            $acadprogcode = Db::table('gradelevel')
                ->select('academicprogram.acadprogcode')
                ->join('academicprogram','gradelevel.acadprogid','=','academicprogram.id')
                ->where('gradelevel.id', $request->get('selectedgradelevel'))
                ->first()
                ->acadprogcode;
                
    
            if(strtolower($acadprogcode) == 'college')
            {
                $sections = DB::table('college_sections')
                    ->select('id','sectionDesc as sectionname')
                    ->where('yearID', $request->get('selectedgradelevel'))
                    ->where('deleted','0')
                    ->get();
            }else{
                $sections = DB::table('sections')
                    ->select('id','sectionname')
                    ->where('levelid', $request->get('selectedgradelevel'))
                    ->where('deleted','0')
                    ->get();
    
            }
            return $sections;
        }
    }
    public function filter(Request $request)
    {
        // return $request->all();
        $selectedschoolyear = $request->get('selectedschoolyear');
        $selecteddaterange  = $request->get('selecteddaterange');
        $selecteddepartment = $request->get('selecteddepartment');
        $selectedgradelevel = $request->get('selectedgradelevel');
        $selectedsemester   = $request->get('selectedsemester');
        $selectedsection    = $request->get('selectedsection');
        $selectedgrantee    = $request->get('selectedgrantee');
        $selectedmode       = $request->get('selectedmode'); 
        // $students = array();
        $allstudents = AccountsReceivableModel::allstudents($selectedschoolyear,$selecteddaterange,$selecteddepartment,$selectedgradelevel,$selectedsemester,$selectedsection,$selectedgrantee,$selectedmode);
        // return $allstudents;
        $overalltotalassessment     = $allstudents->sum('totalassessment');
        $overalltotaldiscount       = $allstudents->sum('discount');
        $overalltotalnetassessed    = $allstudents->sum('netassessed');
        $overalltotalpayment        = $allstudents->sum('totalpayment');
        $overalltotalbalance        = $allstudents->sum('balance');
        return view('finance.accountsreceivable.accountsreceivabletable')
            ->with('students', $allstudents)
            ->with('overalltotalassessment', $overalltotalassessment)
            ->with('overalltotaldiscount', $overalltotaldiscount)
            ->with('overalltotalnetassessed', $overalltotalnetassessed)
            ->with('overalltotalpayment', $overalltotalpayment)
            ->with('overalltotalbalance', $overalltotalbalance);
    }
    public function export(Request $request)
    {
        // return $request->all();
        date_default_timezone_set('Asia/Manila');
        $selectedschoolyear = $request->get('selectedschoolyear');
        $selecteddaterange  = $request->get('selecteddaterange');
        $selecteddepartment = $request->get('selecteddepartment');
        $selectedgradelevel = $request->get('selectedgradelevel');
        $selectedsemester   = $request->get('selectedsemester');
        $selectedsection    = $request->get('selectedsection');
        $selectedgrantee    = $request->get('selectedgrantee');
        $selectedmode       = $request->get('selectedmode'); 
        // $students = array();
        $allstudents = AccountsReceivableModel::allstudents($selectedschoolyear,$selecteddaterange,$selecteddepartment,$selectedgradelevel,$selectedsemester,$selectedsection,$selectedgrantee,$selectedmode);
        $overalltotalassessment     = $allstudents->sum('totalassessment');
        $overalltotaldiscount       = $allstudents->sum('discount');
        $overalltotalnetassessed    = $allstudents->sum('netassessed');
        $overalltotalpayment        = $allstudents->sum('totalpayment');
        $overalltotalbalance        = $allstudents->sum('balance');
        // return $item->only(['sid', 'firstname','middlename','lastname','suffix','mol','sectionname','levelname','acadprogcode','grantee','units','totalassessment','discount','netassessed','totalpayment','balance']);
        $allstudents =$allstudents->flatten();
        $allstudents = $allstudents->map(function ($user) {
            return (object)collect($user)
                ->only(['sid', 'firstname','middlename','lastname','suffix','mol','sectionname','levelname','acadprogcode','grantee','units','totalassessment','discount','netassessed','totalpayment','balance'])
                ->all();
        });
        
        $schoolinfo = Db::table('schoolinfo')
            ->select(
                'schoolinfo.schoolid',
                'schoolinfo.schoolname',
                'schoolinfo.authorized',
                'schoolinfo.picurl',
                'refcitymun.citymunDesc',
                'schoolinfo.district',
                'schoolinfo.address',
                'refregion.regDesc'
            )
            ->leftJoin('refregion','schoolinfo.region','=','refregion.regCode')
            ->leftJoin('refcitymun','schoolinfo.division','=','refcitymun.citymunCode')
            ->first();

        if($selecteddaterange != null)
        {
            $date = explode(' - ', $selecteddaterange);
            $selecteddaterange = date('M d, Y', strtotime($date[0])).' - '.date('M d, Y', strtotime($date[1]));
        }
        if($selecteddepartment != null){
            $academicprogramname = DB::table('academicprogram')
                ->where('id', $selecteddepartment)
                ->first()
                ->progname;
            $selecteddepartment = $academicprogramname; 
        }
        if($selectedgradelevel != null)
        {
            $levelname = DB::table('gradelevel')
                ->where('id', $selectedgradelevel)
                ->first()
                ->levelname;

            $selectedgradelevel = $levelname;
        }
        if($selectedsemester != null)
        {
            $semester = DB::table('semester')
                ->where('id', $selectedsemester)
                ->first()
                ->semester;

            $selectedsemester = $semester;
        }
        if($selectedgrantee != null)
        {
            $grantee = DB::table('grantee')
                ->where('id', $selectedgrantee)
                ->first()
                ->description;
            $selectedgrantee = $grantee;
        }
        if($selectedmode != null)
        {
            $mol = DB::table('modeoflearning')
                ->where('id', $selectedmode)
                ->first()
                ->description;
            $selectedmode = $mol;
        }
        $selectedschoolyear = DB::table('sy')
            ->where('id', $selectedschoolyear)
            ->first()
            ->sydesc;
        if($request->get('exporttype') == 'pdf')
        {
                $students =  $allstudents;
                $pdf = new MYPDFAccountsReceivable(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

                // set document information
                $pdf->SetCreator('CK');
                $pdf->SetAuthor('CK Children\'s Publishing');
                $pdf->SetTitle($schoolinfo->schoolname.' - Account Receivables');
                $pdf->SetSubject('Account Receivables');
                
                // set header and footer fonts
                $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
                $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
                
                // set default monospaced font
                $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
                
                // set margins
                $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
                $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
                $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
                
                // $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 4, 'color' => array(255, 0, 0)));
                // set auto page breaks
                $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
                
                // set image scale factor
                $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
                
                // set some language-dependent strings (optional)
                if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
                    require_once(dirname(__FILE__).'/lang/eng.php');
                    $pdf->setLanguageArray($l);
                }
                
                // ---------------------------------------------------------
                
                // set font
                // $pdf->SetFont('dejavusans', '', 10);
                
                
                // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
                // Print a table
                
                // add a page
                $pdf->AddPage();
                    
                $html = '
                <table style="font-size: 9px; font-weight: bold; padding-top: 5px;">
                        <tr>
                            <td>S.Y '.$selectedschoolyear.' <br/>';
                            if($selecteddaterange != null){
                                $html.='AS OF : '.strtoupper($selecteddaterange).' <br/>';
                            }
                            if($selecteddepartment != null){
                                $html.='DEPARTMENT : '.strtoupper($selecteddepartment).' <br/>';
                            }
                            if($selectedgradelevel != null){
                                $html.='GRADE LEVEL : '.strtoupper($selectedgradelevel).' <br/>';
                            }
                            $html.='</td>
                                    <td>';
                                if($selectedsemester != null)
                                {
                                    $html.='SEMESTER : '.strtoupper($selectedsemester).' <br/>';
                                }
                                if($selectedgrantee != null)
                                {
                                    $html.='GRANTEE : '.strtoupper($selectedgrantee).' <br/>';
                                }
                                if($selectedmode != null)
                                {
                                    $html.='MODE OF LEARNING : '.strtoupper($selectedmode).' <br/>';
                                }
                                $html.='</td>
                        </tr>
                    </table>';
                    $count = 1;
                    $html.='<table border="1" cellpadding="2" >
                        <thead>
                            <tr>
                                <th style="font-size: 8px !important; font-weight: bold;" width="25" align="center">#</th>
                                <th style="font-size: 8px !important; font-weight: bold;" align="center">ID</th>
                                <th style="font-size: 8px !important; font-weight: bold;" width="110" align="center">Student Name</th>
                                <th style="font-size: 8px !important; font-weight: bold;" align="center">Department</th>
                                <th style="font-size: 8px !important; font-weight: bold;" align="center">Level</th>
                                <th style="font-size: 8px !important; font-weight: bold;" width="30" align="center">Units</th>
                                <th style="font-size: 8px !important; font-weight: bold;" align="center">Total<br/>Assessment</th>
                                <th style="font-size: 8px !important; font-weight: bold;" align="center">Discount</th>
                                <th style="font-size: 8px !important; font-weight: bold;" align="center">Net<br/>Assessed</th>
                                <th style="font-size: 8px !important; font-weight: bold;" align="center">Total<br/>Payment</th>
                                <th style="font-size: 8px !important; font-weight: bold;" align="center">Balance</th>
                            </tr>
                        </thead>';
                            if(count($students)>0)
                            {
                                foreach($students as $student)
                                {
                                    $html.='<tr nobr="true">
                                        <td width="25" style="font-size: 7px !important;" align="center">'.$count.'</td>
                                        <td style="font-size: 7px !important;" align="center">'.$student->sid.'</td>
                                        <td style="font-size: 7px !important;" width="110">'.$student->lastname.', '.$student->firstname.' '.$student->middlename.' '.$student->suffix.'</td>
                                        <td style="font-size: 7px !important;" align="center">'.$student->acadprogcode.'</td>
                                        <td style="font-size: 7px !important;" align="center">'.$student->levelname.'</td>
                                        <td style="font-size: 7px !important;" width="30" align="center">'.$student->units.'</td>
                                        <td style="font-size: 7px !important;" align="center"><span style="font-family: DejaVu Sans; sans-serif;">&#8369;</span> '.number_format($student->totalassessment,2,'.',',').'</td>
                                        <td style="font-size: 7px !important;" align="center"><span style="font-family: DejaVu Sans; sans-serif;">&#8369;</span> '.number_format($student->discount,2,'.',',').'</td>
                                        <td style="font-size: 7px !important;" align="center"><span style="font-family: DejaVu Sans; sans-serif;">&#8369;</span> '.number_format($student->netassessed,2,'.',',').'</td>
                                        <td style="font-size: 7px !important;" align="center"><span style="font-family: DejaVu Sans; sans-serif;">&#8369;</span> '.number_format($student->totalpayment,2,'.',',').'</td>
                                        <td style="font-size: 7px !important;" align="center"><span style="font-family: DejaVu Sans; sans-serif;">&#8369;</span> '.number_format($student->balance,2,'.',',').'</td>
                                    </tr>';
                                    $count+=1;
                                }
                            }
                            $html.='<tr>
                                <th colspan="6" align="right" style="font-size: 9px !important; font-weight: bold;">TOTAL</th>
                                <th id="overalltotalassessment" align="center" style="font-size: 7px !important; font-weight: bold;"><span style="font-family: DejaVu Sans; sans-serif;">&#8369;</span> '.number_format($overalltotalassessment,2,'.',',').'</th>
                                <th id="overalltotaldiscount" align="center" style="font-size: 7px !important; font-weight: bold;"><span style="font-family: DejaVu Sans; sans-serif;">&#8369;</span> '.number_format($overalltotaldiscount,2,'.',',').'</th>
                                <th id="overalltotalnetassessed" align="center" style="font-size: 7px !important; font-weight: bold;"><span style="font-family: DejaVu Sans; sans-serif;">&#8369;</span> '.number_format($overalltotalnetassessed,2,'.',',').'</th>
                                <th id="overalltotalpayment" align="center" style="font-size: 7px !important; font-weight: bold;"><span style="font-family: DejaVu Sans; sans-serif;">&#8369;</span> '.number_format($overalltotalpayment,2,'.',',').'</th>
                                <th id="overalltotalbalance" align="center" style="font-size: 7px !important; font-weight: bold;"><span style="font-family: DejaVu Sans; sans-serif;">&#8369;</span> '.number_format($overalltotalbalance,2,'.',',').'</th>
                            </tr>
                    
                    </table>';
                    // output the HTML content
                    
                    set_time_limit(3000);
                    $pdf->writeHTML($html, true, false, true, false, '');
                    
                    
                    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
                    
                    // test custom bullet points for list
                    
                    
                    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
                    
                    // reset pointer to the last page
                    $pdf->lastPage();
                    
                    // ---------------------------------------------------------
                    //Close and output PDF document
                    $pdf->Output('Account Receivables.pdf', 'I');
                    
        }else{
            $allstudents = $allstudents->sortBy('lastname')->values()->all();
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();;
            $sheet = $spreadsheet->getActiveSheet();
            $borderstyle = [
                // 'alignment' => [
                //     'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                // ],
                'borders' => [
                    'allborders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        // 'color' => ['argb' => 'FFFF0000'],
                    ],
                ]
            ];

            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Logo');
            $drawing->setDescription('Logo');
            $drawing->setPath(base_path().'/public/'.$schoolinfo->picurl);
            $drawing->setHeight(80);
            $drawing->setWorksheet($sheet);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(20);
            $drawing->setOffsetY(20);
            // $drawing->setRotation(25);
            $drawing->getShadow()->setVisible(true);
            $drawing->getShadow()->setDirection(45);

            $sheet->mergeCells('C2:F2');
            $sheet->setCellValue('C2', $schoolinfo->schoolname);
            $sheet->setCellValue('C3', 'S.Y '.$selectedschoolyear);
            
            if($selecteddaterange != null)
            {
                $sheet->setCellValue('C7', 'AS OF : '.strtoupper($selecteddaterange));
            }
            if($selecteddepartment != null)
            {
                // $sheet->mergeCells('D7:E7');
                $sheet->setCellValue('C8', 'DEPARTMENT : '.strtoupper($selecteddepartment));
            }
            if($selectedgradelevel != null)
            {
                // $sheet->mergeCells('F7:G7');
                $sheet->setCellValue('C9', 'GRADE LEVEL : '.strtoupper($selectedgradelevel));
            }
            if($selectedsemester != null){
                $sheet->mergeCells('F7:I7');
                $sheet->setCellValue('F7', 'SEMESTER : '.strtoupper($selectedsemester));
            }
            if($selectedgrantee != null){
                $sheet->mergeCells('F8:I8');
                $sheet->setCellValue('F8', 'GRANTEE : '.strtoupper($selectedgrantee));
            }
            if($selectedmode != null){
                $sheet->mergeCells('F9:I9');
                $sheet->setCellValue('F9', 'MODE OF LEARNING : '.strtoupper($selectedmode));
            }
            $sheet->getStyle('D:K')->getAlignment()->setHorizontal('center');
            foreach(array('A','B','C','D','E','F','G','H','I','J','K') as $columnID) {
                $sheet->getColumnDimension($columnID)
                    ->setAutoSize(true);
            }
            $sheet->setCellValue('A10','#');
            $sheet->setCellValue('B10','Student ID');
            $sheet->setCellValue('C10','Student Name');
            $sheet->setCellValue('D10','Department');
            $sheet->setCellValue('E10','Grade Level');
            $sheet->setCellValue('F10','Units');
            $sheet->setCellValue('G10','Total Assessment');
            $sheet->setCellValue('H10','Discount');
            $sheet->setCellValue('I10','Net Assessed');
            $sheet->setCellValue('J10','Total Payment');
            $sheet->setCellValue('K10','Balance');
            $sheet->getStyle('A10:K10')->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle('A10:K10')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle('A10:K10')->getFont()->setBold(true);

            $count = 1;
            $startcell = 11;

            if(count($allstudents)>0)
            {
                foreach($allstudents as $student)
                {
                    $sheet->setCellValue('A'.$startcell,$count);
                    $sheet->setCellValue('B'.$startcell,$student->sid);
                    $sheet->setCellValue('C'.$startcell,$student->lastname.', '.$student->firstname.' '.$student->middlename.' '.$student->suffix);
                    $sheet->setCellValue('D'.$startcell,$student->acadprogcode);
                    $sheet->setCellValue('E'.$startcell,$student->levelname);
                    $sheet->setCellValue('F'.$startcell,$student->units);
                    $sheet->setCellValue('G'.$startcell,number_format($student->totalassessment,2,'.',''));
                    $sheet->setCellValue('H'.$startcell,number_format($student->discount,2,'.',''));
                    $sheet->setCellValue('I'.$startcell,number_format($student->netassessed,2,'.',''));
                    $sheet->setCellValue('J'.$startcell,number_format($student->totalpayment,2,'.',''));
                    $sheet->setCellValue('K'.$startcell,number_format($student->balance,2,'.',''));
                    $sheet->getStyle('G'.$startcell.':K'.$startcell)->getNumberFormat()->setFormatCode( ' #,##0.00_-' );
                    $count+=1;
                    $startcell+=1;
                }
            }
            $sheet->mergeCells('E'.$startcell.':F'.$startcell);
            $sheet->setCellValue('E'.$startcell,'TOTAL Student/s : ');
            $sheet->setCellValue('G'.$startcell,'=SUM(G10:G'.($startcell-1).')');
            $sheet->setCellValue('H'.$startcell,'=SUM(H10:H'.($startcell-1).')');
            $sheet->setCellValue('I'.$startcell,'=SUM(I10:I'.($startcell-1).')');
            $sheet->setCellValue('J'.$startcell,'=SUM(J10:J'.($startcell-1).')');
            $sheet->setCellValue('K'.$startcell,'=SUM(K10:K'.($startcell-1).')');
            $sheet->getStyle('G'.$startcell.':K'.$startcell)->getNumberFormat()->setFormatCode( '#,##0.00_-' );

            $sheet->getStyle('E'.$startcell)->getFont()->setBold(true);
            $sheet->getStyle('G'.$startcell.':K'.$startcell)->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle('G'.$startcell.':K'.$startcell)->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="Account Receivables - Students.xlsx"');
            $writer->save("php://output");
        }
    }
}

class MYPDFAccountsReceivable extends TCPDF {

    //Page header
    public function Header() {
        // Logo
        // $this->Image('@'.file_get_contents('/home/xxxxxx/public_html/xxxxxxxx/uploads/logo/logo.png'),10,6,0,13);
        $schoollogo = DB::table('schoolinfo')->first();
        $picurl = explode('?', $schoollogo->picurl);
        $image_file = public_path().'/'.$picurl[0];
        $extension = explode('.', $schoollogo->picurl);
        $this->Image('@'.file_get_contents($image_file),15,9,17,17);
        
        $schoolname = $this->writeHTMLCell(false, 50, 40, 10, '<span style="font-weight: bold">'.$schoollogo->schoolname.'</span>', false, false, false, $reseth=true, $align='L', $autopadding=true);
        $schooladdress = $this->writeHTMLCell(false, 50, 40, 15, '<span style="font-weight: bold; font-size: 10px;">'.$schoollogo->address.'</span>', false, false, false, $reseth=true, $align='L', $autopadding=true);
        $title = $this->writeHTMLCell(false, 50, 40, 20, 'Account Receivables', false, false, false, $reseth=true, $align='L', $autopadding=true);
        // Ln();
    }

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().' of '.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        $this->Cell(0, 10, date('m/d/Y'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}