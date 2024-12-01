
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { LocalStorageService } from '../local-storage/local-storage.service';
import { API_CONFIG } from '../../config/config.local';
import { ApiResponse } from '../../models/api-response';

@Injectable({
  providedIn: 'root'
})
export class SchemaService {

  protected token: string | null = null;

  constructor(private http: HttpClient, localStorage: LocalStorageService) {
    this.token = localStorage.getItem('authToken');
  }

  getSchema(tableName: string): Observable<ApiResponse<any>> {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    };
    return this.http.get<ApiResponse<any>>(`${API_CONFIG.apiUrl}/schema/${tableName}`, { headers });
  }
}
