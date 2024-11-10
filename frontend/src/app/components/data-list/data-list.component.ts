import { Component, Input, OnInit } from '@angular/core';

import { DataService } from '../../services/data/data.service';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { SchemaService } from '../../services/schema/schema.service';
import { Schema, SchemaField } from '../../models/schema';

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
  schema: Schema | undefined;

  constructor(
    private dataService: DataService,
    private schemaService: SchemaService,
    private route: ActivatedRoute,
  ) { }

  ngOnInit(): void {
    this.route.params.subscribe(params => {
      this.tableName = params['tableName'];
      this.loadSchema();
      this.loadData();
    });
  }

  loadSchema() {
    if (!this.tableName) {
      return;
    }
    this.schemaService.getSchema(this.tableName).subscribe(schema => {
      this.schema = schema;
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

  isSystemColumn(column: SchemaField, systemColumns: any = ['id', 'created_at', 'updated_at']) {
    return systemColumns.includes(column.name);
  }
}
