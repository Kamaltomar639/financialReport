import { Component,ElementRef,Input,OnInit,ViewChild } from '@angular/core';
import { ServiceService } from '../services/service.service';
import { IDropdownSettings } from 'ng-multiselect-dropdown';
import { Chart } from 'chart.js';
import { ChartType,Row } from "angular-google-charts";
import { BaseChartDirective } from 'ng2-charts';
import { Router, ActivatedRoute } from '@angular/router';
import Swal from 'sweetalert2'
import { UnaryOperator } from '@angular/compiler';
import * as XLSX from 'xlsx'; 
import * as moment from 'moment';
@Component({
  selector: 'app-financial-report',
  templateUrl: './financial-report.component.html',
  styleUrls: ['./financial-report.component.css']
})
export class FinancialReportComponent implements OnInit {
  @Input() user: any;
  
  @ViewChild('Chart',{ read: ElementRef }) myChart!: ElementRef;
  @ViewChild('panelBodyBox',{ read: ElementRef }) box!: ElementRef;
  @ViewChild('yearMultiSelect') yearDropdown: any;
  @ViewChild('promoterMultiSelect') promoterDropdown: any;
  @ViewChild('venueMultiSelect') venueDropdown: any;
  @ViewChild('pieChartPanelBody',{ read: ElementRef }) pieChartArea!: ElementRef;
  @ViewChild('pieLoader',{ read: ElementRef }) pieLoader !: ElementRef;
  @ViewChild('lineChartLoader',{ read: ElementRef }) lineChartLoader !: ElementRef;
  @ViewChild('dateAll',{ read: ElementRef }) dateAll !: ElementRef;
  @ViewChild('customDate',{ read: ElementRef }) customDate !: ElementRef;


  @ViewChild('Chart') chart!: BaseChartDirective;
  @ViewChild('eventTable',{ static: false }) eventTable: any;
  @ViewChild('transactionTable',{ static: false }) transactionTable: any;
  // @ViewChild('ageChart') ageChart!: BaseChartDirective;
  // @ViewChild('relationChart') relationChart!: BaseChartDirective;
  // @ViewChild('genderChart') genderChart!: BaseChartDirective;
  // @ViewChild(DataTableDirective, {static: false})
  // dataTableElement!: DataTableDirective;
  // dataTableOptions: DataTables.Settings = {}
  dtOptions: DataTables.Settings = {};
  // dataTableTrigger: Subject<any> = new Subject<any>();
  user_id:any = 2;
  user_id_promoter = 1854;
  window: any;
  inputDate2: any;
  inputDate1: any;
  yearList: any;
  selectedYear: any;
  promoters: any;
  venues: any;
  stripe: any = 0;
  events: any = 0;
  transfers: any = 0;
  payouts: any = 0;
  revenue: any = 4021312;
  current_month_revenue: any = 12312;
  upcoming_event_revenue: any = 312002;

  tickets: any = 0;
  userRole: any = 'Admin';
  custom_date_check = false;
  start: any;
  end: any;
  cards: boolean = false;
  // user_role: any = 'Admin'
  startDate: any = '';
  endDate: any = '';
  selectedEventIds: any = [];
  selectedVenueIds: any = [];
  dropdownSettings: IDropdownSettings = {}
  dorpdownSettingsForYear: IDropdownSettings = {}
  viewType = true;
  buttonClicked:any ='';
  loader = true;
  prevNext:boolean  = true;
  fileName: any ='';
  //loader = false;
  // Line chart initialization Code Start
  chartLabels = [];
  lineChartType = 'line';

  lineChartData = [
    {
      
      
      data: [],
      label: 'Current',
      fill: false,
      backgroundColor: '#6aebbe',
      borderColor: '#6aebbe',
      pointBackgroundColor: "#DCDCDC",
      pointBorderColor: 'black',
      pointBorderWidth: 2,
     // bezierCurve : false,
      pointRadius: 4,
      tension: 0,
      borderWidth: 3,
      //'scaleShowLabels': false,
      
    },
    {
      //data: [],
      data:[],
      label: 'Comparision',
      fill: false,
      backgroundColor: 'orange',
      borderColor: 'orange',
      pointBackgroundColor: "#DCDCDC",
      pointBorderColor: 'orange',
      pointBorderWidth: 2,
     // bezierCurve : false,
      pointRadius: 4,
      tension: 0,
      borderWidth: 3,
    }


  ];
  

  chartType = 'amount'
  chartOptions = this.getChartOptions()
 
  //currentTimeFrame: any = 'daily'
  currentTimeFrame: any = '1M';
  xValue: any = '';
  yValue: any = '';
  xCompValue: any ='';
  yCompValue: any ='';
  valueType: any = 'Revenue : ';
  
  chartWidth:any = (window.innerWidth > 1366 ? window.innerWidth-30 : window.innerWidth-10)
  
  // line chart code End

  // pie Chart initialization code start

  genderPieData = [
    {
      label: 'Gender',
      //data: [300,50,],
      data: [50,500,],
      backgroundColor: [
        'rgb(255, 99, 132)',
        'rgb(54, 162, 235)',
      ],
      borderColor: "#2B313B",
      hoverOffset: 0,
    }
  ]
  genderLabels = ['Male','Female']
  agePieData = [
    {
      label: 'Age',
      data: [130,250,120,20],
      //data: [],
      backgroundColor: [
        'rgb(255, 199, 100)',
        'rgb(115, 160, 235)',
        'rgb(255,55,155)',
        'rgb(84, 193, 69)',
      ],
      borderColor: "#2B313B",
      hoverOffset: 0,

    }
  ]
  ageLabels = ['Below 21','From 21-25','From 26-30','Above 30']

  genderPieOptions = this.getGenderPieOptions()

  relationPieData = [
    {
      label: 'Relation',
      data: [200,50,100],
      backgroundColor: [
        '#ccc',
        'rgb(255, 99, 13)',
        'rgb(10, 200, 105)',

      ],
      borderColor: "#2B313B",
      hoverOffset: 0,
    }
  ]
  relationLabels = ['Single','Dating','Complicated']
  relationChartType = ChartType.PieChart;
  relationPieOptions = this.getRelationPieOptions()

  // pie Chart Code End






  constructor(private service: ServiceService,private router:Router,private activatedRoute:ActivatedRoute) {
    //console.log(this.router.url)
    this.activatedRoute.queryParams.subscribe(params => {
      let user = params['user'];
      if(user){
        this.userRole = window.atob(user).split('&')[1];
        this.user_id = window.atob(user).split('&')[0];
      }
      
  });
    this.yearList = this.getYearList()
    this.selectedYear = new Date().getFullYear()
    this.getVenusPromoters()
    //this.getFinancialReportData()
    this.getStripeAccountData()
    let apiCall: any;
    window.onstorage = () => {
      this.startDate = '';
      this.endDate = '';
      this.loader = true;
      
      //this.loader = false;
      this.pieChartsShow = false;
      let getValue = localStorage.getItem('event_id_array')
      this.selectedEventIds = JSON.parse(getValue!)
      clearTimeout(apiCall)
      apiCall = setTimeout(() => {
        this.getFinancialReportDataAccordingToEventId()
      },2000)
    }




  }
  ngOnDestroy(): void {
    // Do not forget to unsubscribe the event

  }





  ngOnInit(): void {
    // this.getCustomerTable()
    // this.dtOptions = {
    //   pagingType: 'full_numbers',
    //   pageLength: 5,
    //   lengthMenu:[10,20,25],
    //   processing:true

    // }
    

    // multiselection dropdown Settings for venues,promoters,and year
    this.dorpdownSettingsForYear = {
      singleSelection: true,
      idField: 'item_id',
      textField: 'item_text',
      selectAllText: 'Select All',
      unSelectAllText: 'UnSelect All',
      itemsShowLimit: 1,
      allowSearchFilter: true,
      maxHeight: 200,
      closeDropDownOnSelection: false,
      limitSelection: 1,
    }
    this.dropdownSettings = {
      singleSelection: false,
      idField: 'item_id',
      textField: 'item_text',
      selectAllText: 'Select All',
      unSelectAllText: 'UnSelect All',
      itemsShowLimit: 1,
      allowSearchFilter: true,
      maxHeight: 200,
      closeDropDownOnSelection: true,
      limitSelection: -1,

    }

    // dropdown setting code end here


    // code for line generating on mouse movement on chart & showing data on chart js
    setTimeout(() => {
      let displayWidth = document.getElementById('page-content') as HTMLBodyElement
      if (displayWidth.clientWidth > 1200) {
        this.viewType = true;
      } else {
        this.viewType = true;
      }
      let checkDateSelector = this.dateAll.nativeElement
      if (checkDateSelector.checked) {
        console.log('date all working')
      }





      let canvas = this.myChart.nativeElement;
      console.log('working')
      let myChartWidth = canvas.clientWidth
      // this.chartWidth = myChartWidth;
      let box = this.box.nativeElement;
      var drawLines = function (event: any) {
        var rect = box.getBoundingClientRect();
        // console.log('working')
        if (event.pageY <= 430 || event.pageY >= 722) {
          return;
        }
        var x = event.pageX - rect.x;
        var y = event.pageY - rect.top - rect.y;

        var straightLine = box.querySelector('.straightLine');
        var hrLine = box.querySelector('.hrLine');

        var slTrans = 'translate(' + x + 'px, 0px)';
        var hrTrans = 'translate(0px, ' + y + 'px)';
        if (!straightLine) {
          straightLine = document.createElement('div');
          straightLine.classList.add('straightLine');
          straightLine.style.height = "280px";
          straightLine.style.width = '2px';
          box.appendChild(straightLine);
          // console.log('straightLine')
        }
        straightLine.style.transform = slTrans;

        if (!hrLine) {
          hrLine = document.createElement('div')
          // console.log(hrLine)
          // hrLine.style.height = "2px";
          // hrLine.classList.add('hrLine');
          // hrLine.style.width = '98%';
          // box.appendChild(hrLine);
        }
        hrLine.style.transform = hrTrans;
      }
      box.addEventListener('mousemove',function (event: any) {
        drawLines(event);
      });

    

      box.addEventListener('mousemove',(evt: any) => {
        let width = this.myChart.nativeElement.clientWidth;
        // console.log(width)

        // console.log(width,'clie')
        console.log(this.chartLabels)
        let no = width / 5;
        /*if (this.currentTimeFrame == 'daily') {
          no = width / 6;
        } */

        if (this.currentTimeFrame == '1D') {
          no = width / 6;
        }

        var mousePos = getMousePos(canvas,evt,width);
        let index = Math.round(mousePos.x / no);

        if (index > 6) {
          index = 6;
        } else if (index < 0) {
          index = 0;
        }
        // console.log(index)

        this.xValue = this.chartLabels[index]
        if (this.chartType == 'amount') {
          this.yValue = `$ ${this.lineChartData[0].data[index]}`
        }
        else {
          this.yValue = `${this.lineChartData[0].data[index]}`
        }



      },false);
      function getMousePos(canvas: any,evt: any,width: any) {
        console.log(canvas.clientWidth ,'canvas width ', width,'normal ',evt.clientX,'evt clientX')
        var rect = canvas.getBoundingClientRect();
         console.log(rect.x);
        return {
          x: evt.clientX - rect.x,
          y: evt.clientY - rect.y
        };
      }
    },500)
    // chart line and data code ends here
    var aHours = 60 * 60 * 1000;


    // this.start = new Date(new Date(new Date()).setHours((new Date().getHours() - 5),0,0,0)).getTime();
    // this.end = new Date(new Date(new Date()).setHours(new Date().getHours(),0,0,0)).getTime();

    // this.sortingButton(this.currentTimeFrame)

  }

  //buttonNameArray: Array<String> = ['hourly','daily','weekly','monthly','yearly'];
  buttonNameArray: Array<String> = ['1D','1W','1M','1Y','MTD','YTD','ALL'];
  public sortingButton(buttonName: any) {
    this.custom_date_check = false;
    this.prevNext  = true;
    this.currentTimeFrame = buttonName;
    this.next = 0;
    this.previous = 0;
    this.buttonNameArray.map((res: any) => {
      let buttonElement = document.querySelector(`#button${res}`) as HTMLButtonElement
      //console.log(res,'btn',buttonName);
      if (res == buttonName) {
        buttonElement.style.backgroundColor = 'black';
        buttonElement.style.color = '#6AEBBE';
      } else {
        buttonElement.style.backgroundColor = '#6AEBBE';
        buttonElement.style.color = 'black';
        // do nothing
      }
    })
    //this.getFinancialReportData1();
    //this.getFinancialReportData1('none');
     if(buttonName =='ALL' || buttonName =='CUSTOM' ){
       
       this.prevNext  = false;
     }
    this.onTimeFrameTabSelect('none')
    this.getLineChartData('none');
   
    //
  }
  next: number = 0;
  previous: number = 0;
  public buttonNextPreviousForLineChart(buttonClicked: any) {
    if (buttonClicked == 'next') {
      this.next++;
      this.previous--;
    }
    if (buttonClicked == 'previous') {
      this.next--;
      this.previous++;
    }
    this.onTimeFrameTabSelect(buttonClicked);
    this.getLineChartData(buttonClicked)
  }

  public getLineChartData(buttonClicked: any) {
    this.service.getChartData(this.currentTimeFrame,this.selectedEventIds,this.chartType,this.userRole,buttonClicked,this.next,this.previous,this.user_id,this.startDate,this.endDate).subscribe((res: any) => {



      this.chartLabels = res.xAxis;
      this.lineChartData[0].data = res.yAxis;
      //this.lineChartData[1].data = res.comp_xAxis;
      this.lineChartData[1].data = res.comp_yAxis;

      this.chart.chart!.config!.data!.labels = res.xAxis
      this.chart.chart!.config!.data!.datasets![0].data = res.yAxis

      this.lineChartLoader.nativeElement.style.display = 'none';
      // console.log(this.chartLabels,this.lineChartData[0].data)
      //this.loader = false;
    })
  }



  // Methods to provide Options to the charts
  public getChartOptions() {

    return {
      responsive: false,
      
    }
  }

  public getGenderPieOptions() {
    // let areaWidth = this.pieChartArea.nativeElement.clientWidth

    return {
      responsive: false,
    }
  }

  public getRelationPieOptions() {
    return {
      responsive: false
    }
  }

  // options methods Ends here

  // getDatePickerValue


  getDataAccordingToDateRange() {
    // console.log(this.inputDate1)
    this.currentTimeFrame = 'CUSTOM';
    let d1 = new Date(this.inputDate1)
    let d2 = new Date(this.inputDate2)
    // console.log(d1<d2)
    // console.log(d1,d2)
    if (d1 <= d2) {

      this.startDate = this.dateConverter(this.inputDate1.toLocaleDateString());
      this.endDate = this.dateConverter(this.inputDate2.toLocaleDateString());
      this.selectedEventIds = [];
      this.selectedYear = ''
      this.pieChartsShow = false;
      this.loader = true;
      $(this.eventTable.nativeElement).DataTable().destroy()
      $(this.transactionTable.nativeElement).DataTable().destroy()
      //this.getFinancialReportData()
      this.getFinancialReportData1(this.buttonClicked)
      this.getLineChartData('none');
    } else {
      Swal.fire('','start date should be smaller then or equal to end date','warning')
    }
  }
  public dateConverter(date: any) {
    let datearray = date.split("/");

    let newDate = datearray[2] + '/' + datearray[0] + '/' + datearray[1];

    return newDate;
  }
  // method to calculate percentage of charts data
  public calculatePercentOfData(array: any) {
    let total = 0;
    array.map((el: any) => { total += parseInt(el) })
     //console.log(total)
    let percentage = array.map((el: any) => ((parseInt(el) / total) * 100).toFixed(2))
    // console.log(percentage)
    return percentage
  }




  // methods to get data from dropdown
  public onYearSelect($event: any) { 
    this.selectedYear = $event.item_id

  }
  public onSelectAllYear($event: any) {
    this.selectedYear = []
    $event.map((res: any) => {
      this.selectedYear.push(res.item_id)
    })



  }

  public onUnSelectAllYear() {
    this.selectedYear = []
  }
  public onYearDeSelect($event: any) {
    let index = this.selectedYear.findIndex((res: any) => res == $event.item_id)
    this.selectedYear.splice(index,1)
  }

  public onVenueSelect($event: any) {
    this.venue_id.push($event.item_id)
   // console.log(this.venue_id)
  }
  public onSelectAllVenue($event: any) {
    this.venue_id = []
    $event.map((res: any) => {
      this.venue_id.push(res.item_id)

    })
    // this.promoterDropdown.toggleSelectAll()
    //console.log(this.venue_id)
  }
  public onVenueDeSelect($event: any) {
    let index = this.venue_id.findIndex((res: any) => res == $event.item_id);
    this.venue_id.splice(index,1)
  }
  public onUnSelectAllVenue() {
    this.venue_id = []
  }
  public onSelectAllPromoter($event: any) {
    this.promoter_id = []
    $event.map((res: any) => {
      this.promoter_id.push(res.item_id)
    })
  }
  public onPromoterSelect($event: any) {
    this.promoter_id.push($event.item_id)
  }
  public onUnSelectAllPromoter() {
    this.promoter_id = []
  }
  public onPromoterDeSelect($event: any) {
    let index = this.promoter_id.findIndex((res: any) => res == $event.item_id);
    this.promoter_id.splice(index,1)
  }
  public doneButtonYear() {
    this.startDate = '';
    this.endDate = '';
    if(this.promoter_id.length > 0 && this.promoter_id.length < this.promoters.length){
      this.promoterDropdown.toggleSelectAll()
      this.promoterDropdown.toggleSelectAll()
      this.promoter_id = []
    }else if(this.promoter_id.length == this.promoters.length){
      this.promoterDropdown.toggleSelectAll()
      this.promoter_id = []
    }else{
      this.promoter_id = []
    }
    if((this.venue_id.length > 0 && this.venue_id.length < this.venues.length) || this.venue_id.length == 0){
      this.venueDropdown.toggleSelectAll()
    }
    
    this.selectedEventIds = []
    $(this.eventTable.nativeElement).DataTable().destroy()
    $(this.transactionTable.nativeElement).DataTable().destroy()
    this.loader = true;
    this.pieChartsShow = false;
    this.getFinancialReportData()
  }
  public doneButtonPromoter() {
    $(this.eventTable.nativeElement).DataTable().destroy()
    $(this.transactionTable.nativeElement).DataTable().destroy()
    this.startDate = '';
    this.endDate = '';
    if(this.venue_id.length > 0 && this.venue_id.length < this.venues.length){
      this.venueDropdown.toggleSelectAll()
      this.venueDropdown.toggleSelectAll()
      this.venue_id = []
    }else if(this.venue_id.length ==  this.venues.length){
      this.venueDropdown.toggleSelectAll()
      this.venue_id = []
    }else{
      this.venue_id = []
    }
    this.selectedEventIds = []
    this.loader = true;
    this.pieChartsShow = false;
    this.getFinancialReportData()
  }
  public doneButtonVenue() {
    this.startDate = '';
    this.endDate = '';
    if(this.promoter_id.length > 0 && this.promoter_id.length < this.promoters.length){
      this.promoterDropdown.toggleSelectAll()
      this.promoterDropdown.toggleSelectAll()
      this.promoter_id = []
    }else if(this.promoter_id.length == this.promoters.length){
      this.promoterDropdown.toggleSelectAll()
      this.promoter_id = []
    }else{
      this.promoter_id = []
    }
    this.selectedEventIds = []
    $(this.eventTable.nativeElement).DataTable().destroy()
    $(this.transactionTable.nativeElement).DataTable().destroy()
    this.loader = true;
    this.pieChartsShow = false;
    this.getFinancialReportData()
  }

  // get data from dropdown ends here


  // method to provide year to the multiselection dropdown
  private getYearList() {
    let year_options: Array<object> = [];
    for (let i = 2019; i <= new Date().getFullYear(); i++) {

      year_options.push({ item_id: i,item_text: i })

    }
    return year_options;
    
  }


  // methods to get promoters and venues for dropdown
  venue_id: any = []
  promoter_id: any = [];
  selectedIdsForData: any;
  private getVenusPromoters() {
    this.service.getVenuesOrPromoters(this.user_id,this.userRole).subscribe((res: any) => {
      let venue_id,promoter_id
      if (res.venue_data && res.venue_data != null) {
        this.venues = res.venue_data.map((res: any) => ({ item_id: res.id,item_text: res.name }))
        venue_id = this.venues.map((res: any) => (res.item_id))
        this.venue_id = venue_id
        this.selectedIdsForData = venue_id
        window.localStorage.setItem(`venue_id_array`,JSON.stringify(this.selectedIdsForData))
      }
      if (res.promoter_data && res.promoter_data != null) {
        this.promoters = res.promoter_data.map((res: any) => ({ item_id: res.id,item_text: res.name }))
        promoter_id = this.promoters.map((res: any) => (res.item_id))
        window.localStorage.setItem(`promoter_id_array`,JSON.stringify(this.promoter_id))
      }



      //this.getFinancialReportData()
      this.sortingButton('1M');
       
      setTimeout(() => {
        if (this.userRole == 'Admin') {
          // this.promoterDropdown.toggleSelectAll()
        }

        this.venueDropdown.toggleSelectAll()
      },500)
    })
    
  }

  public getStripeAccountData(){
    this.service.getStripeAccountData().subscribe((res:any)=>{
      this.stripe = res.available_balance;
    })
  }
  pieChartsShow = false;
  eventTableData = []

  // methods to get and set data according to the selected events from the event table
  private getFinancialReportDataAccordingToEventId() {
    this.pieChartsShow = false;
    this.service.getFinancialReportData(this.venue_id.join(),this.selectedEventIds,'',this.selectedYear,this.startDate,this.endDate,this.userRole,this.user_id,this.currentTimeFrame).subscribe((res: any) => {
      this.genderPieData[0].data = [res.relational_data.male,res.relational_data.female];
      this.relationPieData[0].data = [res.relational_data.single,res.relational_data.dating,res.relational_data.complicated]
      this.agePieData[0].data = [res.relational_data.below_21,res.relational_data.from_21_25,res.relational_data.from_26_30,res.relational_data.above_30]
      // this.genderChart.chart!.config!.data!.datasets![0].data = this.genderPieData[0].data
      // this.relationChart.chart!.config!.data!.datasets![0].data = this.relationPieData[0].data
      // this.ageChart.chart!.config!.data!.datasets![0].data = this.agePieData[0].data
      this.tickets = res.indicators_data.total_tickets

      this.payouts = res.indicators_data.profit

      this.events = res.total_events
      this.upcoming_event_revenue = res.indicators_data.upcoming_events_revenue
      this.current_month_revenue = res.indicators_data.current_month_revenue
      if (this.userRole == 'Admin') {
        //this.revenue = res.indicators_data.past_events_revenue cmt by kamal
        this.revenue = res.indicators_data.client_revenue 
      } else {
        this.revenue = res.indicators_data.client_revenue
      }
      // this.stripe = 0;
      this.transfers = res.indicators_data.transfers


      this.cards = true;

      this.pieLoader.nativeElement.style.display = 'none';
      this.pieChartsShow = true;
      $(this.transactionTable.nativeElement).DataTable().clear();

      $(this.transactionTable.nativeElement).DataTable().rows.add(res.customer_data);
      $(this.transactionTable.nativeElement).DataTable().draw()
      //this.sortingButton('daily');
      this.sortingButton('1D');
    })
  }

  // 

  // methods to get and set data when page loaded first time and on year selection or promoter selection or venue selection
  private getFinancialReportData() {
    //this.service.getFinancialReportData(this.venue_id.join(),this.selectedEventIds,this.promoter_id.join(),this.selectedYear,this.startDate,this.endDate,this.userRole,this.user_id).subscribe((res: any) => {
      // window.addEventListener('storage',function (event:any){console.log('working')})
      /*this.pieChartsShow = false;

      this.genderPieData[0].data = [res.relational_data.male,res.relational_data.female];
      this.relationPieData[0].data = [res.relational_data.single,res.relational_data.dating,res.relational_data.complicated]
      this.agePieData[0].data = [res.relational_data.below_21,res.relational_data.from_21_25,res.relational_data.from_26_30,res.relational_data.above_30]
      // this.genderChart.chart!.config!.data!.datasets![0].data = this.genderPieData[0].data
      // this.relationChart.chart!.config!.data!.datasets![0].data = this.relationPieData[0].data
      // this.ageChart.chart!.config!.data!.datasets![0].data = this.agePieData[0].data
      this.tickets = res.indicators_data.total_tickets

      this.payouts = res.indicators_data.profit
      this.events = res.total_events
      this.upcoming_event_revenue = res.indicators_data.upcoming_events_revenue
      this.current_month_revenue = res.indicators_data.current_month_revenue
      if (this.userRole == 'Admin') {
        this.revenue = res.indicators_data.past_events_revenue
      } else {
        this.revenue = res.indicators_data.client_revenue
      }
      // this.stripe = 0
      this.transfers = res.indicators_data.transfers


      this.pieLoader.nativeElement.style.display = 'none';
      if (res.event_data && res.event_data != null) {
        this.eventTableData = res.event_data
        window.localStorage.setItem(`event_id_array`,JSON.stringify(res.event_data.map((res: any) => (res[0]))))
      }

      // console.log(this.eventTableData)
      this.pieChartsShow = true;
      // this.dataTableTrigger.unsubscribe();
      // this.dataTableTrigger.next();
      // this.dataTableElement.dtInstance.then((dtInstance: DataTables.Api) => {
      //   dtInstance.draw();
      // });
      let event_id_array: any = [];
      let event_id_array_duplicate: any = []
      if (res.event_data && res.event_data != null) {

        // Event DataTable initialize and set Data 
        $(this.eventTable.nativeElement).DataTable({
          "scrollX": true,
          "autoWidth": true,
          "data": this.eventTableData,


          "processing": false, //Feature control the processing indicator.
          "serverSide": false,
          'order': this.checkOrderForEventTable(), //Feature control DataTables' server-side processing mode.
          'columnDefs': [{
            'targets': 0,
            'searchable': false,
            'orderable': false,
            'className': 'td-check',

            'render': function (data,type,full,meta) {

              event_id_array.push(data);
              event_id_array_duplicate.push(data);
              event_id_array = event_id_array.filter(function (item: any,index: any,inputArray: any) {
                return inputArray.indexOf(item) == index;
              });
              //   console.log(event_id_array)
              event_id_array_duplicate = event_id_array_duplicate.filter(function (item: any,index: any,inputArray: any) {
                return inputArray.indexOf(item) == index;
              });
              // returning checkbox instead of ids when click ids will passes to the onChooseEventFunction
              return `<input type="checkbox" checked name="id[]" id="event_${data}" onclick='onChooseEvent(${data})' value="' + $('<div/>').text(${data}).html() + '">`;
            },

          },

          {
            // adding $ to the selected columns which we getting from checkColumnForEventTable method according to the user role
            "targets": this.checkColumnForEventTable(),
            "render": function (data,type,full,meta) {
              return `$ ${data}`;
            }

          },]

        }); 
      }
      this.selectedEventIds = event_id_array

      //this.sortingButton('daily');
      this.sortingButton('1D');
      this.setTransactionTable(res.customer_data)



    })*/

  }
  public onTimeFrameTabSelect(buttoncklic: any){
    $(this.eventTable.nativeElement).DataTable().destroy()
    $(this.transactionTable.nativeElement).DataTable().destroy()
    this.loader = true;
    this.pieChartsShow = false;
    this.getFinancialReportData1(buttoncklic);
  }

  // methods to get and set data when page loaded first time and on year selection or promoter selection or venue selection
  private getFinancialReportData1(buttonClicked: any) {
    //this.service.getFinancialReportData(this.venue_id.join(),this.selectedEventIds,this.promoter_id.join(),this.selectedYear,this.startDate,this.endDate,this.userRole,this.user_id).subscribe((res: any) => {
      // window.addEventListener('storage',function (event:any){console.log('working')})
    let getVenue = localStorage.getItem('venue_id_array')
    this.selectedVenueIds = JSON.parse(getVenue!)
    this.service.getFinancialReportData1(this.venue_id.join(),this.selectedEventIds,this.promoter_id.join(),this.selectedYear,this.startDate,this.endDate,this.userRole,this.user_id,this.currentTimeFrame,buttonClicked,this.next,this.previous).subscribe((res: any) => {
      this.pieChartsShow = false;

      this.genderPieData[0].data = [res.relational_data.male,res.relational_data.female];
      this.relationPieData[0].data = [res.relational_data.single,res.relational_data.dating,res.relational_data.complicated]
      this.agePieData[0].data = [res.relational_data.below_21,res.relational_data.from_21_25,res.relational_data.from_26_30,res.relational_data.above_30]
      // this.genderChart.chart!.config!.data!.datasets![0].data = this.genderPieData[0].data
      // this.relationChart.chart!.config!.data!.datasets![0].data = this.relationPieData[0].data
      // this.ageChart.chart!.config!.data!.datasets![0].data = this.agePieData[0].data
      this.tickets = res.indicators_data.total_tickets

      this.payouts = res.indicators_data.profit
      this.events = res.total_events
      this.upcoming_event_revenue = res.indicators_data.upcoming_events_revenue
      this.current_month_revenue = res.indicators_data.current_month_revenue
      if (this.userRole == 'Admin') {
        //this.revenue = res.indicators_data.past_events_revenue cmt by kamal
        this.revenue = res.indicators_data.client_revenue 
      } else {
        this.revenue = res.indicators_data.client_revenue
      }
      // this.stripe = 0
      this.transfers = res.indicators_data.transfers


      this.pieLoader.nativeElement.style.display = 'none';
      if (res.event_data && res.event_data != null) {
        this.eventTableData = res.event_data
        window.localStorage.setItem(`event_id_array`,JSON.stringify(res.event_data.map((res: any) => (res[0]))))
      }

      // console.log(this.eventTableData)
      this.pieChartsShow = true;
      // this.dataTableTrigger.unsubscribe();
      // this.dataTableTrigger.next();
      // this.dataTableElement.dtInstance.then((dtInstance: DataTables.Api) => {
      //   dtInstance.draw();
      // });
      let event_id_array: any = [];
      let event_id_array_duplicate: any = []
      if (res.event_data && res.event_data != null) {
        //console.log('event',this.eventTableData)
        // Event DataTable initialize and set Data 
        $(this.eventTable.nativeElement).DataTable({
          
          "scrollX": true,
          "autoWidth": true,
          "data": this.eventTableData,


          "processing": false, //Feature control the processing indicator.
          "serverSide": false,
          //"retrieve": true,
         // "paging": true,
          'order': this.checkOrderForEventTable(), //Feature control DataTables' server-side processing mode.
          'columnDefs': [/*{
            'targets': 0,
            'searchable': false,
            'orderable': false,
            //'className': 'td-check',

            'render': function (data,type,full,meta) {

              event_id_array.push(data);
              event_id_array_duplicate.push(data);
              event_id_array = event_id_array.filter(function (item: any,index: any,inputArray: any) {
                return inputArray.indexOf(item) == index;
              });
              //   console.log(event_id_array)
              event_id_array_duplicate = event_id_array_duplicate.filter(function (item: any,index: any,inputArray: any) {
                return inputArray.indexOf(item) == index;
              });
              // returning checkbox instead of ids when click ids will passes to the onChooseEventFunction
              return `<input type="checkbox" checked name="id[]" id="event_${data}" onclick='onChooseEvent(${data})' value="' + $('<div/>').text(${data}).html() + '">`;
            },

          },*/

          {
            // adding $ to the selected columns which we getting from checkColumnForEventTable method according to the user role
            "targets": this.checkColumnForEventTable(),
            "render": function (data,type,full,meta) {
              return `$ ${data}`;
            }

          },]

        });
      }
      this.selectedEventIds = event_id_array

      //this.sortingButton('daily');
      //console.log('fin report data sorting');
      //this.sortingButton('1D');
      this.setTransactionTable(res.customer_data)
      this.loader = false;


    })

  }


  private checkColumnForEventTable() {
    if (this.userRole == 'Admin') {
      return ([8,9,10,11,12,13,14,15,16,17,19,20,21,22,23,24,26,27,28,29,30])
    } else {
      return ([8,9,10,11,12,13,14,15,16])
    }
  }

  // returning order for selected column in event table
  private checkOrderForEventTable() {
    if (this.userRole == 'Admin') {
      return [[0,'desc']]
    } else {
      return [[0,'desc']]
    }
  }

  // initialize transaction table and setting data 
  private setTransactionTable(data: any) {


    $(this.transactionTable.nativeElement).DataTable({
      "scrollX": true,
      "autoWidth": true,
      "data": data,
      //"retrieve": true,
      //"paging": true,
      "order": [
        [2,'desc']
      ], //comment date

      "processing": false, //Feature control the processing indicator.
      "serverSide": false,
      "columnDefs": this.checkColumnForTransactionTable(),
    })
  }



  // returning buttons for refund according to the user role and setting $ and other things 
  public checkColumnForTransactionTable() {


    if (this.userRole == 'Admin') {
      return ([{
        "targets": [0],
        "render": function(data:any, type:any, full:any, meta:any) {

            return `${data.acquired_date} ${data.acquired_date_time}`;
        }
      },
      {
        "targets": [19],
        "render": function (data: any,type: any,full: any,meta: any) {
          let dat = data.split(',');
                    let stripe = dat[1];
                    let types = dat[2];
                    data = dat[0];
                    if (full[18] == 'Confirmed' && (types != 'RSVP' && types != 'Courtesy')) {
                        return `<button type="buttton" class="btn btn-primary btn-xs" id ="refundButton${data}" onclick="onRefundFee('${data}','${full[1]}','${full[7]}','${full[5]}','${full[6]}','${stripe}','${types}')" style="z-index:1 !important" >${checkButtonName(types)}</button>`;
                    } else if (full[18] == 'Confirmed' && (types == 'RSVP' || types ==
                            'Courtesy')) {
                        return `<button type="buttton" class="btn btn-primary btn-xs" id ="refundButton${data}" onclick="onRefundFee('${data}','${full[1]}','${full[7]}','${full[5]}','${full[6]}','${stripe}','${types}')" style="z-index:1 !important" >${checkButtonName(types)}</button>
                        `;
                    } else {
                        return '';
                    }

        }
      },
      {
        "targets": [18],
        "render": function (data: any,type: any,full: any,meta: any) {
          let dat = full[19].split(',');
                    let stripe = dat[1];
                    let types = dat[2];
                    if (full[18] == 'Confirmed') {
                        return `Confirmed`
                    } else if (full[18] != 'Confirmed' && (types == 'RSVP' || types ==
                            'Courtesy')) {
                        return `Invalidated`
                    }
                    // else if(full[18] != 'Confirmed' && full[7] != 'Courtesy' && full[18] !=''){
                    //     return 'Refunded';
                    // }
                    else {
                        return `${data}`;
                    }
        }
      },
      {
        "targets": [5,6],
        "render": function (data: any,type: any,full: any,meta: any) {
          return `$ ${data}`;
        }
      }])
    } else {
      return ([
        {
          "targets": [0],
          "render": function(data:any, type:any, full:any, meta:any) {
  
              return `${data.acquired_date} ${data.acquired_date_time}`;
          }
        },      
        {
        "targets": [5],
        "render": function (data: any,type: any,full: any,meta: any) {
          return `$ ${data}`;
        }
      },])
    }

   function checkButtonName(status:any) {
      if (status == 'Courtesy' || status == 'RSVP') {
          return 'Invalidate'
      } else {
          return 'Refund'
      }
  }
  }




  // public onRefundFee(transactionId: any,userName: any,checkType: any) {
  //   console.log('working')
  //   let title = 'Refund';
  //   let titleSmall = 'refund'
  //   if (checkType == 'Courtesy') {
  //     title = 'Invalidate'
  //     titleSmall = 'invalidate'
  //   }
  //   let refundButton = document.getElementById('refundButton' + transactionId) as HTMLButtonElement
  //   refundButton.disabled = true;
  //   Swal.fire({
  //     title: `${title}`,
  //     text: `Are you sure you wish to ${titleSmall} this transaction by ${userName}`,

  //     showCancelButton: true,
  //     confirmButtonColor: '#3085d6',
  //     cancelButtonColor: '#d33',
  //     confirmButtonText: 'Yes'
  //   }).then((result) => {
  //     if (result.isConfirmed) {


  //       $("#loader42nite").show()
  //       // let formdata = encodeFormData({transaction_id:transactionId,user_id:2})
  //       this.service.refundFee(transactionId,this.user_id).subscribe((res: any) => {
  //         if (res.status == 'Success' || res.status == 'success') {

  //           $("#loader42nite").hide()
  //           Swal.fire('','Transaction refunded successfully','success')
  //           this.getFinancialReportData(this.selectedIdsForData)

  //         }
  //         else {
  //           $("#loader42nite").hide()
  //           refundButton.disabled = false;
  //           Swal.fire('',res.message,'error')
  //         }
  //       })
  //     } else {
  //       refundButton.disabled = false;
  //     }
  //   })
  // }




  // methods to hide and show custom date menu

  public dateAllChecked() {
    this.custom_date_check = false;
  }
  public customDateChecked() {
    this.custom_date_check = true;
  }

  // selectAll and unselectAll process in eventTable 
  public onSelectAndUnselectAllCheckbox() {
    let checkAll = document.getElementById('example-select-all') as HTMLInputElement
    if (checkAll.checked) {
      let event_id: any = []
      this.eventTableData.map((res: any) => {
        event_id.push(res[0])
        let checkbox = document.getElementById('event_' + res[0]) as HTMLInputElement
        if (checkbox != null) {
          checkbox.checked = true;
        }

      })
      this.selectedEventIds = event_id
      window.localStorage.setItem(`event_id_array`,JSON.stringify(this.selectedEventIds))
      window.dispatchEvent(new Event('storage'))
    }
    else {
      this.selectedEventIds = [];
      window.localStorage.setItem(`event_id_array`,JSON.stringify(this.selectedEventIds))
      $(this.transactionTable.nativeElement).DataTable().clear()
      $(this.transactionTable.nativeElement).DataTable().rows.add([]);
      $(this.transactionTable.nativeElement).DataTable().draw()
      this.eventTableData.map((res: any) => {
        let checkbox = document.getElementById('event_' + res[0]) as HTMLInputElement
        if (checkbox != null) {
          checkbox.checked = false;
        }
      })
    }


  }

  //event table excel export
  public exportexcel()
    {
       /* table id is passed over here */   
       
       /*let element = document.getElementById('event'); 
       const ws: XLSX.WorkSheet =XLSX.utils.table_to_sheet(element);

       /* generate workbook and add the worksheet */
      //  const wb: XLSX.WorkBook = XLSX.utils.book_new();
      //  XLSX.utils.book_append_sheet(wb, ws, 'Sheet1');

      //  /* save to file */
      //  this.fileName= 'EventTable.Xlsx';
      //  XLSX.writeFile(wb, this.fileName);
      let getVenue = localStorage.getItem('venue_id_array')
    this.selectedVenueIds = JSON.parse(getVenue!)
    this.service.getExcelFinancialReportEventData(this.venue_id.join(),this.selectedEventIds,this.promoter_id.join(),this.selectedYear,this.startDate,this.endDate,this.userRole,this.user_id,this.currentTimeFrame,this.buttonClicked,this.next,this.previous).subscribe((res: any) => {
    });
			
    }

    //transaction table excel export
    transactionexportexcel(): void 
  {
     /* table id is passed over here */   
     let element = document.getElementById('transaction'); 
     const ws: XLSX.WorkSheet =XLSX.utils.table_to_sheet(element);

     /* generate workbook and add the worksheet */
     const wb: XLSX.WorkBook = XLSX.utils.book_new();
     XLSX.utils.book_append_sheet(wb, ws, 'Sheet1');

     /* save to file */
     this.fileName= 'TransactionTable.Xlsx';
     XLSX.writeFile(wb, this.fileName);
    
  }

}
