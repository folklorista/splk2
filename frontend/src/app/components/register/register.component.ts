import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

@Component({
    selector: 'app-register',
    imports: [ReactiveFormsModule, CommonModule, RouterLink],
    templateUrl: './register.component.html',
    styleUrls: ['./register.component.scss']
})
export class RegisterComponent {
  registerForm;

  constructor(
    private fb: FormBuilder,
    private http: HttpClient,
    private router: Router
  ) {
    // Inicializace formuláře
    this.registerForm = this.fb.group({
      first_name: ['', [Validators.required]],
      last_name: ['', [Validators.required]],
      email: ['', [Validators.required, Validators.email]],  // Validace emailu
      password: ['', [Validators.required, Validators.minLength(8)]]  // Validace hesla
    });
  }

  // Odeslání formuláře
  onSubmit(): void {
    if (this.registerForm.invalid) {
      return; // Pokud je formulář nevalidní, neodesíláme
    }

    const registerData = this.registerForm.value;

    // Odeslání dat na backend pro registraci
    this.http.post('/api/register', registerData).subscribe({
      next: response => {
        console.log('Registrace proběhla úspěšně, nyní se můžete přihlásit', response);
        this.router.navigate(['/login']);  // Přesměrování na přihlašovací stránku po registraci
      },
      error: error => {
        console.error('Chyba při registraci:', error);
        alert('Registrace se nezdařila, zkuste to prosím znovu.');
      }
    });
  }
}
