import { Component } from '@angular/core';
import { Router } from '@angular/router';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.css']
})
export class AppComponent {
  title = 'reports';
  window:any;
  user:any;
  
  constructor (public router:Router){
    // let user_role = window.location.href.split('?')[1].split('&')[0].split('=')[1]
    // let user_id = window.location.href.split('?')[1].split('&')[1].split('=')[1]
    // this.user = {
    //   user_id:user_id,
    //   user_role:user_role
    // }
  }
}
