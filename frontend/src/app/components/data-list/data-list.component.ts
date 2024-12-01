import { Component, Input, OnInit } from '@angular/core';

import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { Schema, SchemaField } from '../../models/schema';
import { DataService } from '../../services/data/data.service';
import { SchemaService } from '../../services/schema/schema.service';

@Component({
  selector: 'app-data-list',
  templateUrl: './data-list.component.html',
  styleUrls: ['./data-list.component.scss'],
  standalone: true,
  imports: [RouterLink, CommonModule],
})
export class DataListComponent implements OnInit {
  @Input() tableName: string | undefined;

  public data: any[] = [];
  public keys: string[] = []; // proměnná pro uložení klíčů
  public schema: Schema | undefined;

  constructor(
    private dataService: DataService,
    private schemaService: SchemaService,
    private route: ActivatedRoute,
  ) { }

  async ngOnInit() {
    this.route.params.subscribe(async params => {
      this.tableName = params['tableName'];
      await this.loadSchema();
      await this.loadData();
    });
  }

  async loadSchema() {
    if (!this.tableName) {
      return;
    }
    try {
      this.schemaService.getSchema(this.tableName).subscribe(async res => {
        this.schema = res.data;
      });
    } catch (error) {
      console.error('Error loading schema:', error);
    }
  }

  async loadData() {
    if (!this.tableName) {
      this.data = [];
      this.keys = [];
      return;
    }

    try {
      this.dataService.getData(this.tableName).subscribe(async res => {
        this.data = res.data;
        if (this.data.length > 0) {
          this.keys = Object.keys(this.data[0]);
        }
      });
    } catch (error) {
      console.error('Error fetching data:', error);
    };
  }

  isSystemColumn(column: SchemaField, systemColumns: any = ['id', 'created_at', 'updated_at']) {
    return systemColumns.includes(column.name);
  }
}
