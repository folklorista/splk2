import { CanActivateFn, Router } from '@angular/router';

import { inject } from '@angular/core';

export const authGuard: CanActivateFn = (route, state) => {
  const router = inject(Router);
  if (typeof window !== 'undefined') {
    const token = localStorage.getItem('authToken');
    if (token) {
      return true;
    } else {
      router.navigate(['/login']); // Přejdeme na přihlašovací stránku
      return false;
    }
  } else {
    return false;
  }
};


