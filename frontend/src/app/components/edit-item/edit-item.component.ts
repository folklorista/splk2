import { CommonModule } from '@angular/common';
import { Component, Input, OnChanges, OnInit, SimpleChanges } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { ForeignKeyData, ItemData } from '../../models/data';
import { Schema, SchemaField } from '../../models/schema';
import { DataService } from '../../services/data/data.service';
import { SchemaService } from '../../services/schema/schema.service';

@Component({
    selector: 'app-edit-item',
    imports: [ReactiveFormsModule, RouterLink, CommonModule],
    templateUrl: './edit-item.component.html',
    styleUrl: './edit-item.component.scss'
})
export class EditItemComponent implements OnInit, OnChanges {
  @Input() tableName: string | undefined;
  @Input() recordId: number | undefined;
  @Input() action: 'add' | 'edit' | 'view' | 'remove' = 'edit';

  itemData: ItemData = {};
  schema: Schema | undefined;
  editForm: FormGroup;
  formLoaded = false;
  public foreignKeyData: ForeignKeyData = {} // Mapa cizích klíčů

  constructor(
    private fb: FormBuilder,
    private route: ActivatedRoute,
    private schemaService: SchemaService,
    private dataService: DataService,
    private router: Router,
  ) {
    this.editForm = this.fb.group({});
  }

  async ngOnInit() {
    this.route.params.subscribe(async params => {
      this.tableName = params['tableName'];
      this.recordId = parseInt(params['recordId']);
      await this.loadSchema();
      await this.loadItemData();
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['action']) {
      this.updateFormState();
    }
  }

  async loadSchema() {
    if (!this.tableName) {
      return;
    }
    try {
      const res = await firstValueFrom(this.schemaService.getSchema(this.tableName));
      this.schema = res.data;
      this.loadForeignKeyData();
    } catch (error) {
      console.error('Error loading schema:', error);
    }
  }

  async loadItemData() {
    if (!this.tableName) {
      return;
    }
    if (!this.recordId) {
      await this.createForm();
      return;
    }
    try {
      const res = await firstValueFrom(this.dataService.getData(this.tableName, this.recordId));
      this.itemData = res.data;
      await this.createForm();
    } catch (error) {
      console.error('Error loading item data:', error);
    }
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

  async createForm() {
    if (!this.schema) {
      console.error('Schema not loaded');
      return;
    }
    if (!this.recordId) {
      console.warn('No record ID');
      for (const column of this.schema?.columns) {
        this.itemData[column.name] = column.default;
      }
    }
    for (const column of this.schema?.columns) {
      this.editForm.addControl(column.name, this.fb.control(this.itemData[column.name]));
    }

    this.updateFormState();

    this.formLoaded = true;
  }

  onSubmit() {
    if (this.editForm.valid) {
      if (this.recordId) {
        this.updateItem(this.editForm.value);
      } else {
        this.createItem(this.editForm.value);
      }
    }
  }

  createItem(data: any) {
    if (!this.tableName) {
      return;
    }
    this.dataService.createData(this.tableName, data).subscribe({
      next: (response) => {
        if (response.error) {
          console.debug(response.error);
        }
        if (response.message) {
          console.debug(response.message);
        }

        if (response.status === 201) {
          this.router.navigate(['/', this.tableName, response.data.id]);
        }
      },
      error: (error) => {
        console.error('Error creating item:', error);
      },
    });
  }

  updateItem(data: any) {
    if (!this.tableName || !this.recordId) {
      return;
    }
    this.dataService.updateData(this.tableName, this.recordId, data).subscribe({
      next: (response) => {
        if (response.error) {
          console.debug(response.error);
        }
        if (response.message) {
          console.debug(response.message);
        }
        if (response.status === 200) {
          this.router.navigate(['/', this.tableName, this.recordId]);
        }
      },
      error: (error) => {
        console.error('Error updating item:', error);
      },
    });
  }

  isSystemColumn(column: SchemaField, systemColumns: any = ['id', 'created_at', 'updated_at']) {
    return systemColumns.includes(column.name);
  }

  deleteItem() {
    if (!this.tableName || !this.recordId) {
      return;
    }
    this.dataService.deleteData(this.tableName, this.recordId).subscribe({
      next: (response) => {
        if (response.error) {
          console.debug(response.error);
        }
        if (response.message) {
          console.debug(response.message);
        }
        if (response.status === 200) {
          this.router.navigate(['/', this.tableName]);
        }
      },
      error: (error) => {
        console.error('Error deleting item:', error);
      },
    });
  }

  updateFormState() {
    // Dynamically disable/enable form controls based on action
    if (this.action === 'view' || this.action === 'remove') {
      for (const control in this.editForm.controls) {
        if (this.editForm.controls.hasOwnProperty(control)) {
          this.editForm.controls[control].disable();
        }
      }
    } else if (this.action === 'edit' || this.action === 'add') {
      for (const control in this.editForm.controls) {
        if (this.editForm.controls.hasOwnProperty(control)) {
          this.editForm.controls[control].enable();
        }
      }
    }
  }

  public setAction(action: 'add' | 'edit' | 'view' | 'remove') {
    this.action = action;
    this.updateFormState();
  }
}
