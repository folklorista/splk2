import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import * as bcrypt from 'bcryptjs'; // Import bcrypt knihovny

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [ReactiveFormsModule, CommonModule, RouterLink],
  templateUrl: './login.component.html',
  styleUrl: './login.component.scss'
})
export class LoginComponent {
  loginForm;

  constructor(
    private fb: FormBuilder,
    private http: HttpClient,
    private router: Router
  ) {
    this.loginForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required, Validators.minLength(8)]]
    });
  }

  onSubmit(): void {
    const loginData = this.loginForm.value;

    // Hashování hesla pomocí bcrypt před odesláním
    const hashedPassword = bcrypt.hashSync(loginData.password || '', 10);

    // Nahradíme původní heslo hashem
    const loginDataWithHashedPassword = {
      email: loginData.email,
      password: hashedPassword
    };

    // Odeslání požadavku s hashem hesla
    this.http.post<{ token: string }>('/api/login', loginDataWithHashedPassword).subscribe({
      next: response => {
        localStorage.setItem('authToken', response.token);
        this.router.navigate(['/dashboard']);
      },
      error: error => {
        console.error('Login error:', error);
        alert('Login failed. Please check your credentials.');
      }
    });
  }
}
