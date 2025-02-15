import { Component, Input, OnInit } from '@angular/core';

import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { Schema, SchemaField } from '../../models/schema';
import { DataService } from '../../services/data/data.service';
import { SchemaService } from '../../services/schema/schema.service';
import { firstValueFrom } from 'rxjs';
import { ForeignKeyData } from '../../models/data';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-data-list',
  templateUrl: './data-list.component.html',
  styleUrls: ['./data-list.component.scss'],
  imports: [RouterLink, CommonModule, FormsModule]
})
export class DataListComponent implements OnInit {
  @Input() tableName: string | undefined;

  public data: any[] = [];
  public keys: string[] = []; // proměnná pro uložení klíčů
  public schema: Schema | undefined;
  public searchQuery: string = ''; // Vyhledávací dotaz
  public foreignKeyData: ForeignKeyData = {} // Mapa cizích klíčů

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
        this.schema = res?.data ?? [];
        await this.loadForeignKeyData(); // Načíst data pro cizí klíče
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
      // Pokud je aktivní hledání, použijeme vyhledávací endpoint
      this.dataService.getData(this.tableName, this.searchQuery).subscribe(async res => {
        this.data = res?.data ?? [];
        if (this.data.length > 0) {
          this.keys = Object.keys(this.data[0]);
        }
      });
    } catch (error) {
      console.error('Error fetching data:', error);
    };
  }


  // Načítání dat pro cizí klíče
  async loadForeignKeyData() {
    if (!this.schema) {
      return;
    }
    for (const column of this.schema?.columns) {
      if (column.foreign_key) {
        const options = await firstValueFrom(this.dataService.getForeignKeyOptions(column.foreign_key.referenced_table))
        this.foreignKeyData[column.name] = options;
      }
    }
  }

  // Metoda pro získání name podle ID pro cizí klíče
  getForeignKeyName(columnName: string, id: number, removePrefix = false): string {
    const foreignKeyArray = this.foreignKeyData[columnName];
    const option = foreignKeyArray ? foreignKeyArray.find(item => item.id === id) : undefined;
    let name = option ? option.name : `${id}`; // Vrátí name nebo ID, pokud nenalezeno

    if (removePrefix) {
      name = name.replace(/^—+\s/, ''); // Odstraní prefix u stromových struktur
    }

    return name;
  }

  isSystemColumn(column: SchemaField, systemColumns: any = ['id', 'position', 'created_at', 'updated_at']) {
    return systemColumns.includes(column.name);
  }

  // Metoda, která se volá při změně vyhledávacího textu
  onSearchChange() {
    this.loadData(); // Po změně textu provedeme opětovné načtení dat
  }
}
