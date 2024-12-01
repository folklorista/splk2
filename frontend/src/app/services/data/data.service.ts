import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { API_CONFIG } from '../../config/config.local';
import { LocalStorageService } from './../local-storage/local-storage.service';
import { ApiResponse } from '../../models/api-response';

@Injectable({
  providedIn: 'root',
})
export class DataService {

  protected token: string | null = null;

  constructor(private http: HttpClient, localStorage: LocalStorageService) {
    this.token = localStorage.getItem('authToken');
  }

  // Získání všech záznamů
  getData(tableName: string, id?: number): Observable<ApiResponse<any>> {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    };
    return this.http.get<ApiResponse<any>>(`${API_CONFIG.apiUrl}/${tableName}${id ? '/' + id : ''}`, { headers });
  }

  // Vytvoření nového záznamu
  createData(tableName: string, data: any): Observable<ApiResponse<any>> {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    };
    return this.http.post<ApiResponse<any>>(`${API_CONFIG.apiUrl}/${tableName}`, data, { headers });
  }

  // Aktualizace záznamu
  updateData(tableName: string, id: number, data: any): Observable<ApiResponse<any>> {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    };
    return this.http.put<ApiResponse<any>>(`${API_CONFIG.apiUrl}/${tableName}/${id}`, data, { headers });
  }

  // Smazání záznamu
  deleteData(tableName: string, id: number): Observable<ApiResponse<any>> {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    };
    return this.http.delete<ApiResponse<any>>(`${API_CONFIG.apiUrl}/${tableName}/${id}`, { headers });
  }

  // Vyhledání záznamu
  search(tableName: string, search: string): Observable<ApiResponse<any>> {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    };
    return this.http.get<ApiResponse<any>>(`${API_CONFIG.apiUrl}/${tableName}/search/${search}`, { headers });
  }

}
