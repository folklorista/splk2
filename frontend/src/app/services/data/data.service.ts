import { HttpClient, HttpHeaders } from '@angular/common/http';
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
  getData(
    tableName: string,
    searchQuery: string = '',
    searchColumns: string[] = [],
    sortBy: string = 'created_at',
    sortDirection: 'ASC' | 'DESC' = 'DESC',
    limit: number = 10,
    offset: number = 0
  ): Observable<any> {
    let headers = new HttpHeaders({
      'Authorization': `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    });

    if (searchQuery) {
      headers = headers.set('X-Search-Query', encodeURIComponent(searchQuery));
    }
    if (searchColumns.length > 0) {
      headers = headers.set('X-Search-Columns', searchColumns.join(','));
    }
    if (sortBy) {
      headers = headers.set('X-Sort-By', sortBy);
    }
    if (sortDirection) {
      headers = headers.set('X-Sort-Direction', sortDirection);
    }
    if (limit) {
      headers = headers.set('X-Pagination-Limit', limit.toString());
    }
    if (offset) {
      headers = headers.set('X-Pagination-Offset', offset.toString());
    }

    return this.http.get<ApiResponse<any>>(`${API_CONFIG.apiUrl}/${tableName}`, { headers });
  }

    // Získání jednoho záznamu
    getDataById(tableName: string, id: number): Observable<ApiResponse<any>> {
      const headers = {
        Authorization: `Bearer ${this.token}`,
        'Content-Type': 'application/json',
      };
      return this.http.get<ApiResponse<any>>(`${API_CONFIG.apiUrl}/${tableName}/${id}`, { headers });
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
    return this.http.get<ApiResponse<any>>(`${API_CONFIG.apiUrl}/${tableName}/search?search=${search}`, { headers });
  }

  // Získání možností pro cizí klíč z odkazované tabulky
  getForeignKeyOptions(referencedTable: string): Observable<any> {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    };
    return this.http.get<ApiResponse<any>>(`${API_CONFIG.apiUrl}/${referencedTable}/options`, { headers });
  }

}
