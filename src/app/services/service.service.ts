import { Injectable } from '@angular/core';
import { HttpClient,HttpParams,HttpHeaders } from '@angular/common/http';
import { BehaviorSubject,Observable } from 'rxjs';
@Injectable({
  providedIn: 'root'
})
export class ServiceService {

  private preproduction = 'https://www.whats42nite.com/preproduction/apiv2/pro/';
  private pro = "https://www.whats42nite.com/pro/";
  private prodev = "https://www.whats42nite.com/prodev/";
  private prodevPro = 'https://www.whats42nite.com/prodev/pro/';
  private productionBackup = 'https://www.whats42nite.com/productionbackup/apiv2/';

  private production = 'https://www.whats42nite.com/production/apiv2/';

  private headers = {
    headers: new HttpHeaders().set(
      'Content-Type',
      'application/x-www-form-urlencoded'
    ),
  };

  private jsonHeaders = {
    headers: new HttpHeaders().set('Content-Type','form-data'),
  };
  
  constructor(private http: HttpClient) {
    
  }
  
  
  public getVenuesOrPromoters(user_id:any,user_role:any){
    let url = this.prodev+`report/getvenuepromoterdata?user_id=${user_id}&user_role=${user_role}`
    let body = new HttpParams()
    // .set('account_type',type)

    return this.http.get(url,this.headers);
  }

  
  /*public getChartData(currentTimeFrame:any,eventIds:any,chartType:any,user_role:any,buttonClicked:any,next:number,previous:number,user_id:any){
    let url = this.prodev+`report/chartdata-financial?user_role=${user_role}&report=v2&param1=${currentTimeFrame}&chart_type=${chartType}&event_id=${eventIds}&button_click=${buttonClicked}&nextCount=${next}&previousCount=${previous}&user_id=${user_id}`
    let body = new HttpParams()
    

    return this.http.get(url,this.headers);
  }*/
  

  public getChartData(currentTimeFrame:any,eventIds:any,chartType:any,user_role:any,buttonClicked:any,next:number,previous:number,user_id:any,start_date:any,end_date:any){
   // let url = this.prodev+`report/chartdata-financialv3?user_role=${user_role}&report=v2&param1=${currentTimeFrame}&chart_type=${chartType}&event_id=${eventIds}&button_click=${buttonClicked}&nextCount=${next}&previousCount=${previous}&user_id=${user_id}`
   //Report = 'v2';
   console.log('Kamal');
   let url = this.prodev+'report/chartdatafinancialv3'
   let body = new HttpParams()
    .set('user_role',user_role)
    .set('user_id',user_id)
    .set('param1',currentTimeFrame)
    .set('report','v2')
    .set('chart_type',chartType)
    .set('start_date',start_date)
    .set('end_date',end_date)
    .set('nextCount',next)
    .set('previousCount',previous)
    .set('button_click',buttonClicked)

    return this.http.post(url,body,this.headers);
  } 

  public getFinancialReportData1(venue_id:any,event_id:any,promoter_id:any,year:any,start_date:any,end_date:any,user_role:any,user_id:any,currentTimeFrame:any,buttonClicked: any, next: number,previous: number){
    let url = this.prodev+'report/getnewfinancialreportajaxv3'
    let body = new HttpParams()
    .set('venue_id',venue_id)
    .set('event_id',event_id)
    .set('promoter_id',promoter_id)
    .set('year',year)
    .set('start_date',start_date)
    .set('end_date', end_date)
    .set('user_role',user_role)
    .set('user_id',user_id)
    .set('param1',currentTimeFrame)
    .set('button_click',buttonClicked)
    .set('nextCount',next)
    .set('previousCount',previous)
    // .set('venue_user_id',created_by)

    return this.http.post(url,body,this.headers)
  }

  public getExcelFinancialReportEventData(venue_id:any,event_id:any,promoter_id:any,year:any,start_date:any,end_date:any,user_role:any,user_id:any,currentTimeFrame:any,buttonClicked: any, next: number,previous: number){
    let url = this.prodev+'report/getexcelexporteventstable'
    let body = new HttpParams()
    .set('venue_id',venue_id)
    .set('event_id',event_id)
    .set('promoter_id',promoter_id)
    .set('year',year)
    .set('start_date',start_date)
    .set('end_date', end_date)
    .set('user_role',user_role)
    .set('user_id',user_id)
    .set('param1',currentTimeFrame)
    .set('button_click',buttonClicked)
    .set('nextCount',next)
    .set('previousCount',previous)
    // .set('venue_user_id',created_by)

    return this.http.post(url,body,this.headers)
  }

  public getFinancialReportData(venue_id:any,event_id:any,promoter_id:any,year:any,start_date:any,end_date:any,user_role:any,user_id:any,currentTimeFrame:any){
    let url = this.prodev+'report/getnewfinancialreportajax'
    let body = new HttpParams()
    .set('venue_id',venue_id)
    .set('event_id',event_id)
    .set('promoter_id',promoter_id)
    .set('year',year)
    .set('start_date',start_date)
    .set('end_date', end_date)
    .set('user_role',user_role)
    .set('user_id',user_id)
    .set('param1',currentTimeFrame)
    // .set('venue_user_id',created_by)

    return this.http.post(url,body,this.headers)
  }

  public refundFee(transaction_id:any,user_id:any){
    let url = this.preproduction+'CancelTransactionV2'
    let body = new HttpParams()
    .set('transaction_id',transaction_id)
    .set('user_id',user_id)
    return this.http.post(url,body,this.headers)
  }

  // public getTransectionTableData(){
  //   let url = this.prodev+'report/getfinancialreportajax?id=813,811,740,465,361,342,21,381,365,9,326,343,256,385,341,382,364,478,86,706,704,705,298,812,768,769,730,414,401,28,323,324,316,315,506,249,395,437,402,422,789,393,392,32,339,307,270,335,266,410,702,372,421,293,334,820,36,728,143,52,34,49,141,60,142,679,209,210,460,692,736,482,752,780,821,39,676,761,763,764,264,493,727,411,725,732,320,26,808,165,291,223,272,271,461,296,292,269,273,337,318,302,64,797,38,352,267,795,691,278,313,301,369,807,810,47,30,309,312,803,652,35,162,163,746,446,445,751,16,390,325,798,711,490,389,693,696,63,809,773,295,294,601,164,157,734,479,772,338,484,221,367,405,420,418,399,806,41,383,25,33,297,508,686,311,423,819,794,793,68,84,804,82,215,57,285,76,81,83,67,757,555,384,10,300,805,760,788,790,822,37,600,682,598,599,305,747,698,499,495,674,620,394,376,684,739,758,799,779,408,317,224,65,655,695,51,814,658,677,43,331,69,464,681,161,88,70,87,344,153,673,354,801,802,375,770,483,544,543,58,55,654,656,59,488,659,61,77,89,792,796,451,303,505,19,308,321,332,753,304,463,776,485,689,733,353,7,13,497,477,222,409,766,265,754,330,396,665,78,14,327,333,765,319,183,666,336,404,403,683,491,329,371,306,738,816,314,355,211,417,24,701,699,784,783,781,786,787,785,774,322,42,667,664,474,755,45,268,690,782,824,722,748,328,791,680,407,379,380,257,214,213,212,759,756,724,719,721,720,718,717,710,709,708,707,48,377,486,675,159,668,775,453,274,430,91,700,771,657,729,462,778,53,275,31,17,160,29,688,255,56,685,40,50,744,749,750,487,253,54,254,745,716,715,714,713,712,494,737,362,363,391,489,467,468,469,471,470,472,473,413,762,777,800,818,623,310,368,386,279,90,687,815,723,340,416,425,726,438,426,459,817,671,366,697,66,62,71,73,44,46,767,374,397,447,158,398,225&chart_type=amount&event_ids=709,701,710,702,707,708,686,706,703,705,685,704,699,697,684,700,696,698,683,681,695,693,694,687,692,690,682,678,691,688,680,675,679,674,673,676,672,671,670,667,665,663,661,658,659,657,656,655,653,652,650,651,649,648,647,645,646,642,641,639,638,637,634,635,632,631,630,628,627,625,624,626&report=v2'
  //   let body = new HttpParams()
  //   return this.http.post(url,body,this.headers)
  // }

  public getStripeAccountData(){
    let url = this.productionBackup+'pro/getStripeBalance'
    return this.http.get(url,this.headers)
  }

}
