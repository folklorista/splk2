import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Router } from '@angular/router';
import { User } from '../../models/user.model';
import { LocalStorageService } from './../../services/local-storage/local-storage.service';

type TokenData = {
  iss: string;
  sub: number;
  iat: number;
  exp: number;
  user: User;
}

@Component({
  selector: 'app-menubar',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './menubar.component.html',
  styleUrl: './menubar.component.scss'
})
export class MenubarComponent {

  public user: User | null = null;
  localStorage: LocalStorageService;

  constructor(private router: Router, localStorage: LocalStorageService) {
    this.localStorage = localStorage;
  }

  getTokenData(): TokenData | null {
    const token = this.localStorage.getItem('authToken');
    if (token !== null && token !== 'undefined') {
      try {
        return JSON.parse(atob(token.split('.')[1]));
      } catch (e) {
        console.error('Error parsing token data', e);
        return null;
      }
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
