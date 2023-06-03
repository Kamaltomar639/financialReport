import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { FinancialReportComponent } from './financial-report/financial-report.component';

const routes: Routes = [
  {
    path: '', 
    redirectTo: '/financial-report', 
    pathMatch: 'full'
  },
  {
    path:'financial-report',
    component:FinancialReportComponent
  }
];

@NgModule({
  imports: [RouterModule.forRoot(routes)],
  exports: [RouterModule]
})
export class AppRoutingModule { }
