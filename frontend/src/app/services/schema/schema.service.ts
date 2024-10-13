
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { LocalStorageService } from '../local-storage/local-storage.service';

@Injectable({
  providedIn: 'root'
})
export class SchemaService {
  private apiUrl = 'http://splk2.test/api/schema';

  protected token: string | null = null;

  constructor(private http: HttpClient, localStorage: LocalStorageService) {
    this.token = localStorage.getItem('authToken');
  }

  getSchema(tableName: string): Observable<any> {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
    };
    return this.http.get(`${this.apiUrl}/${tableName}`, { headers });
  }
}
