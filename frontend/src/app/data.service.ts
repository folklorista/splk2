import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root',
})
export class DataService {
  private apiUrl = 'http://splk2.test/api/event';  // nahraďte svou URL API

  constructor(private http: HttpClient) {}

  // Dynamické získání dat
  getData(): Observable<any> {
    return this.http.get<any>(this.apiUrl);
  }
}
