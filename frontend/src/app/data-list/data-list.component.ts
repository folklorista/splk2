import { Component, OnInit } from '@angular/core';
import { DataService } from '../data.service';
import { provideHttpClient } from '@angular/common/http';

@Component({
  selector: 'app-data-list',
  templateUrl: './data-list.component.html',
  styleUrls: ['./data-list.component.scss'],
  standalone: true,
})
export class DataListComponent implements OnInit {
  public data: any[] = [];
  public keys: string[] = []; // proměnná pro uložení klíčů

  constructor(private dataService: DataService) {}

  ngOnInit(): void {
    this.dataService.getData().subscribe({
      next: (response: any[]) => {
        this.data = response;

        // Získáme klíče z prvního objektu, pokud nějaká data existují
        if (this.data.length > 0) {
          this.keys = Object.keys(this.data[0]);
        }
      },
      error: (error) => {
        console.error('Error fetching data:', error);
      },
    });
  }
}
