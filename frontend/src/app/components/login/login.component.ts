import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { CommonModule } from '@angular/common'; // Import CommonModule
import { Component } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [ReactiveFormsModule, CommonModule],
  templateUrl: './login.component.html',
  styleUrl: './login.component.scss'
})
export class LoginComponent {
  loginForm;

  constructor(
    private fb: FormBuilder, // Inicializace FormBuilderu v konstruktoru
    private http: HttpClient,
    private router: Router
  ) {
    this.loginForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required]]
    });


  }

  onSubmit(): void {
    const loginData = this.loginForm.value;

    this.http.post<{ token: string }>('/api/login', loginData).subscribe({
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
