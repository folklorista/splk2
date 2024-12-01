import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { ApiResponse } from '../../models/api-response';

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

    this.http.post<ApiResponse<{ token: string }>>('/api/login', loginData).subscribe({
      next: response => {
        if (!response.data) {
          console.error('Login failed:', response);
          alert('Login failed. Please check your credentials.');
          return;
        }
        localStorage.setItem('authToken', response.data.token);
        this.router.navigate(['/dashboard']);
      },
      error: error => {
        console.error('Login error:', error);
        alert('Login failed. Please check your credentials.');
      }
    });
  }
}
