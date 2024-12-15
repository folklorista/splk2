import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

import { MenubarComponent } from '../menubar/menubar.component';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, MenubarComponent],
  template: `
    <app-menubar></app-menubar>
    <router-outlet></router-outlet>
  `,
  styles: ``,
})
export class AppComponent {

}
