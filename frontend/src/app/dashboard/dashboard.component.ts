import { Component } from '@angular/core';
import { Router } from '@angular/router';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.scss'
})
export class DashboardComponent {

  constructor(private router: Router) {}

  logout(): void {
    // Vymazání tokenu z localStorage
    localStorage.removeItem('authToken');
    // Přesměrování na přihlašovací stránku
    this.router.navigate(['/login']);
  }
}
