@extends('finance.layouts.app')

@section('content')
<!-- DataTables -->
<link rel="stylesheet" href="{{asset('plugins/datatables-bs4/css/dataTables.bootstrap4.css')}}">
<!-- daterange picker -->
<link rel="stylesheet" href="{{asset('plugins/daterangepicker/daterangepicker.css')}}">
<style>
    table{
        font-size: 12px;
    }
    td, th {
        padding: 1px !important;
    }
</style>
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Accounts Receivable</h1>
                <!-- <h4 class="text-warning" style="text-shadow: 1px 1px 1px #000000">
                    <i class="fa fa-file-invoice nav-icon"></i> 
                    <b>STUDENT LEDGER</b></h4> -->
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item active">Accounts Receivable</li>
                </ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>
{{-- <div class="row m-2">
    <div class="col-md-12"> --}}
        <div class="card shadow" style="border: none;">
            {{-- <div class="card-header">
                <button type="button" class="btn btn-default btn-sm export" exporttype="excel"><i class="fa fa-download"></i> Excel</button>
                <button type="button" class="btn btn-default btn-sm export" exporttype="pdf"><i class="fa fa-download"></i> PDF</button>
            </div> --}}
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label>School Year</label>
                        {{-- <div class="input-group input-group-sm ">
                            <div class="input-group-prepend">
                                <span class="input-group-text form-control form-control-sm">
                                    Schoolyear
                                </span>
                            </div> --}}
                            <select class="form-control form-control-sm" id="selectedschoolyear">
                                @foreach($schoolyears as $schoolyear)
                                    <option value="{{$schoolyear->id}}" {{$schoolyear->isactive == 1 ? 'selected' : ''}}>{{$schoolyear->sydesc}}</option>
                                @endforeach
                            </select>
                        {{-- </div> --}}
                    </div>
                    <div class="col-md-3">
                        <label>Date Range</label>
                        {{-- <div class="input-group input-group-sm ">
                            <div class="input-group-prepend" >
                                <span class="input-group-text form-control form-control-sm">
                                    Date
                                </span>
                            </div> --}}
                            <input type="text" class="form-control form-control-sm" id="selecteddaterange" placeholder="Select date">
                        {{-- </div> --}}
                    </div>
                    <div class="col-md-3">
                        <label>Department</label>
                        {{-- <div class="input-group input-group-sm ">
                            <div class="input-group-prepend">
                                <span class="input-group-text form-control form-control-sm">
                                    Department
                                </span>
                            </div> --}}
                            <select class="form-control form-control-sm" id="selecteddepartment">
                                <option value="" >All</option>
                                @foreach($departments as $department)
                                    <option value="{{$department->id}}" >{{$department->acadprogcode}}</option>
                                @endforeach
                            </select>
                        {{-- </div> --}}
                    </div>
                    <div class="col-md-3">
                        <label>Grade Level</label>
                        {{-- <div class="input-group input-group-sm ">
                            <div class="input-group-prepend">
                                <span class="input-group-text form-control form-control-sm">
                                    Grade Level
                                </span>
                            </div> --}}
                            <select class="form-control form-control-sm" id="selectedgradelevel">
                                <option value="" >All</option>
                                @foreach($gradelevels as $gradelevel)
                            <option value="{{$gradelevel->id}}" >{{$gradelevel->levelname}}</option>
                                @endforeach
                            </select>
                        {{-- </div> --}}
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-3">
                        <label>Semester</label>
                        {{-- <div class="input-group input-group-sm ">
                            <div class="input-group-prepend">
                                <span class="input-group-text form-control form-control-sm">
                                    Semester
                                </span>
                            </div> --}}
                            <select class="form-control form-control-sm" id="selectedsemester">
                                @foreach($semesters as $semester)
                                    <option value="{{$semester->id}}" {{--{{$semester->isactive == 1 ? 'selected' : ''}} --}}>{{$semester->semester}}</option>
                                @endforeach
                            </select>
                        {{-- </div> --}}
                    </div>
                    <div class="col-md-3">
                        <label>Sections</label>
                        {{-- <div class="input-group input-group-sm ">
                            <div class="input-group-prepend" >
                                <span class="input-group-text form-control form-control-sm">
                                    Sections
                                </span>
                            </div> --}}
                            <select class="form-control form-control-sm" id="selectedsection">
                                <option value="" >All</option>
                            </select>
                        {{-- </div> --}}
                    </div>
                    <div class="col-md-3">
                        <label>Grantee</label>
                        {{-- <div class="input-group input-group-sm ">
                            <div class="input-group-prepend">
                                <span class="input-group-text form-control form-control-sm">
                                    Grantee
                                </span>
                            </div> --}}
                            <select class="form-control form-control-sm" id="selectedgrantee">
                                <option value="" >All</option>
                                @foreach($grantees as $grantee)
                                    <option value="{{$grantee->id}}" >{{$grantee->description}}</option>
                                @endforeach
                            </select>
                        {{-- </div> --}}
                    </div>
                    <div class="col-md-3">
                        <label>MOL</label>
                        {{-- <div class="input-group input-group-sm ">
                            <div class="input-group-prepend">
                                <span class="input-group-text form-control form-control-sm">
                                    MOL
                                </span>
                            </div> --}}
                            <select class="form-control form-control-sm" id="selectedmode">
                                <option value="" >All</option>
                                @foreach($modes as $mode)
                                    <option value="{{$mode->id}}" >{{$mode->description}}</option>
                                @endforeach
                            </select>
                        {{-- </div> --}}
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-12 text-right">
                        <button type="button" class="btn btn-primary" id="generatebutton"><i class="fa fa-sync"></i>Generate</button>
                    </div>
                </div>
            </div>
            {{-- <div class="card-footer">
                <div class="row">
                    <div class="col-12" id="selectedoptionscontainer"></div>
                </div>
                <div class="row">
                    <div class="col-12" id="resultscontainer"></div>
                </div>
            </div> --}}
        </div>
        <div id="selectedoptionscontainer"></div>
        <div id="resultscontainer"></div>
    {{-- </div>
</div> --}}
<!-- jQuery -->
<script src="{{asset('plugins/jquery/jquery.min.js')}}"></script>
<!-- Bootstrap 4 -->
<script src="{{asset('plugins/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
<!-- DataTables -->
<script src="{{asset('plugins/datatables/jquery.dataTables.js')}}"></script>
<script src="{{asset('plugins/datatables-bs4/js/dataTables.bootstrap4.js')}}"></script>
<!-- InputMask -->
<script src="{{asset('plugins/moment/moment.min.js')}}"></script>
<script src="{{asset('plugins/inputmask/min/jquery.inputmask.bundle.min.js')}}"></script>
<!-- date-range-picker -->
<script src="{{asset('plugins/daterangepicker/daterangepicker.js')}}"></script>
<script>
    $(document).ready(function(){
        $('body').addClass('sidebar-collapse')
        $('#selecteddaterange').daterangepicker({
            autoUpdateInput: false,
            locale: {
                cancelLabel: 'Clear',
                format: 'YYYY-MM-DD'
            }
        })
        var loadingtext = 'Getting ready...';
        var selectedschoolyear = $('#selectedschoolyear').val();
        var selecteddaterange = null;
        var selecteddepartment = null;
        var selectedgradelevel = null;
        var selectedsemester = $('#selectedsemester').val();
        var selectedsection = null;
        var selectedgrantee = null;
        var selectedmode = null;
        // $.ajax({
        //     url: '{{ route('acctreceivabledefault')}}',
        //     type: 'GET',
        //     data: {
        //         selectedschoolyear  : $('#selectedschoolyear').val()
        //     },
        //     success:function(data){
        //         $('#resultscontainer').empty();
        //         $('#resultscontainer').append(data)
        //         var tablecontainer = $("#example1").DataTable({
        //             // pageLength : 10,
        //             // lengthMenu: [[5, 10, 20, -1], [5, 10, 20, 'Show All']]
        //             aLengthMenu: [
        //                 [25, 50, 100, 200, -1],
        //                 [25, 50, 100, 200, "All"]
        //             ],
        //             iDisplayLength: -1,
        //             "order": [[ 1, 'asc' ]],
        //             paging: false
        //         });
        //         tablecontainer.on( 'order.dt search.dt', function () {
        //             tablecontainer.column(0, {search:'applied', order:'applied'}).nodes().each( function (cell, i) {
        //                 cell.innerHTML = i+1;
        //             } );
        //         } ).draw();
        //         $('.paginate_button').addClass('btn btn-sm btn-default')
        //     }
        // })
        $(document).on('click','.paginate_button', function(){
            $('.paginate_button').removeClass('btn btn-sm btn-default')
            $('.paginate_button').addClass('btn btn-sm btn-default')
            $(this).removeClass('btn-default')
            $(this).addClass('btn-primary')
            $(this).css('background-color','#007bff')
        })
        function filterstudents(){  
            Swal.fire({
                title: loadingtext,
                onBeforeOpen: () => {
                    Swal.showLoading()
                },
                allowOutsideClick: false
            })          
            $.ajax({
                url: '{{ route('acctreceivablefilter')}}',
                type: 'GET',
                data: {
                    selectedschoolyear  : selectedschoolyear,
                    selecteddaterange   : selecteddaterange, 
                    selecteddepartment  : selecteddepartment, 
                    selectedgradelevel  : selectedgradelevel, 
                    selectedsemester    : selectedsemester, 
                    selectedsection     : selectedsection, 
                    selectedgrantee     : selectedgrantee,
                    selectedmode        : selectedmode 
                },
                success:function(data){
                    $('#resultscontainer').empty();
                    $('#resultscontainer').append(data)
                    $(".swal2-container").remove();
                    $('body').removeClass('swal2-shown')
                    $('body').removeClass('swal2-height-auto')
                }
            })
        }
        $('#selectedschoolyear').on('change', function() {
            loadingtext = 'Loading...';
            selectedschoolyear = $(this).val();

        });
        $('#selecteddaterange').on('apply.daterangepicker', function(ev, picker) {
            loadingtext = 'Loading...';
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
            selecteddaterange = $(this).val();
        });
        $('#selecteddepartment').on('change', function() {
            loadingtext = 'Loading...';
            selecteddepartment = $(this).val()
        });
        $('#selectedgradelevel').on('change', function() {
            loadingtext = 'Loading...';
            selectedgradelevel = $(this).val()
            selectedsection = null;
            $.ajax({
                url: '{{ route('acctreceivablegetsections')}}',
                type: 'GET',
                datetype: 'json',
                data: {
                    selectedgradelevel  : selectedgradelevel
                },
                success:function(data){
                    $('#selectedsection').empty();
                    $('#selectedsection').append('<option value="">All</option>')
                    if(data.length > 0)
                    {
                        $.each(data, function(key, value){
                            $('#selectedsection').append('<option value="'+value.id+'">'+value.sectionname+'</option>')
                        })
                    }
                }
            })
        });
        $('#selectedsemester').on('change', function() {
            loadingtext = 'Loading...';
            selectedsemester = $(this).val()
        });
        $('#selectedsection').on('change', function() {
            loadingtext = 'Loading...';
            selectedsection = $(this).val()
        });
        $('#selectedgrantee').on('change', function() {
            loadingtext = 'Loading...';
            selectedgrantee = $(this).val()
        });
        $('#selectedmode').on('change', function() {
            loadingtext = 'Loading...';
            selectedmode = $(this).val()
        });
        $('#generatebutton').on('click', function(){
            filterstudents();
        })
        $(document).on('click','.export', function(){
                var exporttype = $(this).attr('exporttype')
                var paramet = {
                    selectedschoolyear  : selectedschoolyear,
                    selecteddaterange   : selecteddaterange, 
                    selecteddepartment  : selecteddepartment, 
                    selectedgradelevel  : selectedgradelevel, 
                    selectedsemester    : selectedsemester, 
                    selectedsection     : selectedsection, 
                    selectedgrantee     : selectedgrantee,
                    selectedmode        : selectedmode 
                }
				window.open("/acctreceivable/export?exporttype="+exporttype+"&"+$.param(paramet));
        })
    })

</script>
@endsection