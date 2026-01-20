
        <div class="card shadow" style="border: none;">
            <div class="card-header">
                <button type="button" class="btn btn-default btn-sm export" exporttype="excel"><i class="fa fa-download"></i> Excel</button>
                <button type="button" class="btn btn-default btn-sm export" exporttype="pdf"><i class="fa fa-download"></i> PDF</button>
            </div>
            <div class="card-body" style="overflow: scroll;">
                <table id="example1" class="table table-bordered table-hover text-center">
                  <thead>
                      <tr>
                          <th style="width:5% !important;">#</th>
                          <th>ID</th>
                          <th>Student Name</th>
                          {{-- <th>Department</th> --}}
                          <th>Level</th>
                          <th style="width:5% !important;">Units</th>
                          <th>Total<br/>Assessment</th>
                          <th>Discount</th>
                          <th>Net<br/>Assessed</th>
                          <th>Total<br/>Payment</th>
                          <th>Balance</th>
                      </tr>
                      <tr>
                          <th colspan="5">TOTAL</th>
                          {{-- <th colspan="6">TOTAL</th> --}}
                          <th id="overalltotalassessment">{{number_format($overalltotalassessment,2,'.',',')}}</th>
                          <th id="overalltotaldiscount">{{number_format($overalltotaldiscount,2,'.',',')}}</th>
                          <th id="overalltotalnetassessed">{{number_format($overalltotalnetassessed,2,'.',',')}}</th>
                          <th id="overalltotalpayment">{{number_format($overalltotalpayment,2,'.',',')}}</th>
                          <th id="overalltotalbalance">{{number_format($overalltotalbalance,2,'.',',')}}</th>
                      </tr>
                  </thead>
                  <tbody id="resultscontainer">
                      @if(count($students) == 0)
                          <tr>
                              <td></td>
                              <td colspan="9" class="text-center">No students found</td>
                              {{-- <td colspan="10" class="text-center">No students found</td> --}}
                          </tr>
                      @else
                          @foreach ($students as $student)
                              <tr class="studid" id="{{$student->id}}">
                                  <td></td>
                                  <td class="sid{{$student->id}}">{{$student->sid}}</td>
                                  <td style="text-align: left !important;" class="sname{{$student->id}}">{{$student->lastname}}, {{$student->firstname}} {{$student->middlename}} {{$student->suffix}}</td>
                                  {{-- <td class="sacadprogcode{{$student->id}}">{{$student->acadprogcode}}</td> --}}
                                  <td class="slevelname{{$student->id}}">{{$student->levelname}}</td>
                                  <td class="sunits{{$student->id}}">{{$student->units}}</td>
                                  <td class="sta{{$student->id}}">&#8369; {{number_format($student->totalassessment,2,'.',',')}}</td>
                                  <td class="sd{{$student->id}}">&#8369; {{number_format($student->discount,2,'.',',')}}</td>
                                  <td class="sna{{$student->id}}">&#8369; {{number_format($student->netassessed,2,'.',',')}}</td>
                                  <td class="stp{{$student->id}}">&#8369; {{number_format($student->totalpayment,2,'.',',')}}</td>
                                  <td class="sb{{$student->id}}">&#8369; {{number_format($student->balance,2,'.',',')}}</td>
                              </tr>
                          @endforeach
                      @endif
                  </tbody>
                  <tfoot>
                      <tr>
                          <th>#</th>
                          <th>ID</th>
                          <th>Student Name</th>
                          {{-- <th>Department</th> --}}
                          <th>Level</th>
                          <th>Units</th>
                          <th>Total<br/>Assessment</th>
                          <th>Discount</th>
                          <th>Net<br/>Assessed</th>
                          <th>Total<br/>Payment</th>
                          <th>Balance</th>
                      </tr>
                      <tr>
                          <th colspan="5">TOTAL</th>
                          {{-- <th colspan="6">TOTAL</th> --}}
                          <th>{{number_format($overalltotalassessment,2,'.',',')}}</th>
                          <th>{{number_format($overalltotaldiscount,2,'.',',')}}</th>
                          <th>{{number_format($overalltotalnetassessed,2,'.',',')}}</th>
                          <th>{{number_format($overalltotalpayment,2,'.',',')}}</th>
                          <th>{{number_format($overalltotalbalance,2,'.',',')}}</th>
                      </tr>
                  </tfoot>
                </table>
            </div>
        </div>
              <script>
                    var tablecontainer = $("#example1").DataTable({
                        // pageLength : 10,
                        // lengthMenu: [[5, 10, 20, -1], [5, 10, 20, 'Show All']]
                        aLengthMenu: [
                            [25, 50, 100, 200, -1],
                            [25, 50, 100, 200, "All"]
                        ],
                        iDisplayLength: -1,
                        "order": [[ 1, 'asc' ]],
                        "bSort" : false,
                        paging: false
                    });
                    tablecontainer.on( 'order.dt search.dt', function () {
                        tablecontainer.column(0, {search:'applied', order:'applied'}).nodes().each( function (cell, i) {
                            cell.innerHTML = i+1;
                        } );
                    } ).draw();
                    $('.paginate_button').addClass('btn btn-sm btn-default')
                </script>