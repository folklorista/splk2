import { Component, Input, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { SchemaService } from '../../services/schema/schema.service';
import { Schema, SchemaField } from '../../models/schema';
import { DataService } from '../../services/data/data.service';
import { ItemData } from '../../models/data';
import { Router } from '@angular/router';

@Component({
  selector: 'app-edit-item',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './edit-item.component.html',
  styleUrl: './edit-item.component.scss'
})
export class EditItemComponent implements OnInit {
  @Input() tableName: string | undefined;
  @Input() recordId: number | undefined;
  @Input() action: 'add' | 'edit' | 'view' | 'remove' = 'edit';

  itemData: ItemData = {};
  schema: Schema | undefined;
  editForm: FormGroup;
  formLoaded = false;

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

  async loadSchema() {
    if (!this.tableName) {
      return;
    }
    try {
      this.schema = await this.schemaService.getSchema(this.tableName).toPromise();
    } catch (error) {
      console.error('Error loading schema:', error);
    }
  }

  async loadItemData() {
    if (!this.tableName) {
      return;
    }
    if (!this.recordId) {
      this.createForm();
      return;
    }
    try {
      this.itemData = await this.dataService.getData(this.tableName, this.recordId).toPromise();
      this.createForm();
    } catch (error) {
      console.error('Error loading item data:', error);
    }
  }

  createForm() {
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

    // Pokud je action 'view' nebo 'remove', nastavit atributy disabled a readonly na true
    if (this.action === 'view' || this.action === 'remove' || !this.action) {
      for (const control in this.editForm.controls) {
        if (this.editForm.controls.hasOwnProperty(control)) {
          this.editForm.controls[control].disable();
        }
      }
    }


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
          console.debug(response.error.errorInfo[2]);
        }
        if (response.message) {
          console.debug(response.message);
        }
        if (response.success) {
          this.router.navigate(['/', this.tableName, response.id]);
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
          console.debug(response.error.errorInfo[2]);
        }
        if (response.message) {
          console.debug(response.message);
        }
        if (response.success) {
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
          console.debug(response.error.errorInfo[2]);
        }
        if (response.message) {
          console.debug(response.message);
        }
        if (response.success) {
          this.router.navigate(['/', this.tableName]);
        }
      },
      error: (error) => {
        console.error('Error deleting item:', error);
      },
    });
  }
}
