import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { LocalStorageService } from './../local-storage/local-storage.service';
import { Observable } from 'rxjs';
import { table } from 'console';

@Injectable({
  providedIn: 'root',
})
export class DataService {
  private apiUrl = 'http://splk2.test/api';

  protected token: string | null = null;

  constructor(private http: HttpClient, localStorage: LocalStorageService) {
    this.token = localStorage.getItem('authToken');
  }

  // Získání všech záznamů
  getData(tableName: string, id?:number): Observable<any> {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    };
    return this.http.get<any>(`${this.apiUrl}/${tableName}${id ? '/' + id : ''}`, { headers });
  }

  // Vytvoření nového záznamu
  createData(tableName: string, data: any): Observable<any> {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    };
    return this.http.post<any>(`${this.apiUrl}/${tableName}`, data, { headers });
  }

  // Aktualizace záznamu
  updateData(tableName: string, id: number, data: any): Observable<any> {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    };
    return this.http.put<any>(`${this.apiUrl}/${tableName}/${id}`, data, { headers });
  }

  // Smazání záznamu
  deleteData(tableName: string, id: number): Observable<any> {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    };
    return this.http.delete<any>(`${this.apiUrl}/${tableName}/${id}`, { headers });
  }

  // Vyhledání záznamu
  search(tableName: string, search: string): Observable<any> {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    };
    return this.http.get<any>(`${this.apiUrl}/${tableName}/search/${search}`, { headers });
  }

}
