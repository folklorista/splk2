import { Component, Input, OnInit } from '@angular/core';

import { DataService } from '../../services/data/data.service';
import { ActivatedRoute, RouterLink } from '@angular/router';

@Component({
  selector: 'app-data-list',
  templateUrl: './data-list.component.html',
  styleUrls: ['./data-list.component.scss'],
  standalone: true,
  imports: [RouterLink],
})
export class DataListComponent implements OnInit {
  @Input() tableName: string | undefined;

  public data: any[] = [];
  public keys: string[] = []; // proměnná pro uložení klíčů

  constructor(
    private dataService: DataService,
    private route: ActivatedRoute,
  ) { }

  ngOnInit(): void {
    this.route.params.subscribe(params => {
      this.tableName = params['tableName'];
      this.loadData();
    });
  }

  loadData() {
    if (!this.tableName) {
      this.data = [];
      this.keys = [];
      return;
    }
    this.dataService.getData(this.tableName).subscribe({
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
