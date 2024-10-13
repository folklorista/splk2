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

  // Dynamické získání dat
  getData(tableName: string, id?:number): Observable<any> {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    };
    return this.http.get<any>(`${this.apiUrl}/${tableName}${id ? '/' + id : ''}`, { headers });
  }
}
