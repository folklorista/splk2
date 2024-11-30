import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import * as bcrypt from 'bcryptjs'; // Import bcrypt knihovny

@Component({
  selector: 'app-register',
  standalone: true,
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

    // Hashování hesla pomocí bcrypt před odesláním na server
    const hashedPassword = bcrypt.hashSync(registerData.password || '', 10);  // Salt = 10

    // Vytvoření objektu pro odeslání na server
    const registerDataWithHashedPassword = {
      first_name: registerData.first_name,
      last_name: registerData.last_name,
      email: registerData.email,
      password: hashedPassword
    };

    // Odeslání dat na backend pro registraci
    this.http.post('/api/register', registerDataWithHashedPassword).subscribe({
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
