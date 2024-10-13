import { Component, OnInit } from '@angular/core';
import { RouterLink, RouterOutlet } from '@angular/router';

import { LocalStorageService } from './../../services/local-storage/local-storage.service';
import { Router } from '@angular/router';
import { User } from '../../models/user.model';

type TokenData = {
  iss: string;
  sub: number;
  iat: number;
  exp: number;
  user: User;
}

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, RouterLink],
  templateUrl: './app.component.html',
  styleUrl: './app.component.scss'
})
export class AppComponent {

  public user: User | null = null;
  localStorage: LocalStorageService;

  constructor(private router: Router, localStorage: LocalStorageService) {
    this.localStorage = localStorage;
  }

  getTokenData(): TokenData | null {
    const token = this.localStorage.getItem('authToken');
    if (token !== null) {
      return JSON.parse(atob(token.split('.')[1]));
    }

    return null;
  }

  getUser(): User | null {
    return this.getTokenData()?.user || null;
  }

  logout(): void {
    // Vymazání tokenu z localStorage
    this.localStorage.removeItem('authToken');
    // Přesměrování na přihlašovací stránku
    this.router.navigate(['/login']);
  }

  isLoggedIn(): boolean {
    // Získání tokenu z localStorage
    const token = this.localStorage.getItem('authToken');
    if (token) {
      this.user = this.getUser();
    }
    // Pokud token existuje, uživatel je přihlášen
    return !!token;
  }
}
