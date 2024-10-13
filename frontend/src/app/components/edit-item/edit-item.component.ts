import { Component, Input, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { SchemaService } from '../../services/schema/schema.service';
import { HttpClient } from '@angular/common/http';
import { Schema } from '../../models/schema';

@Component({
  selector: 'app-edit-item',
  standalone: true,
  imports: [ReactiveFormsModule],
  templateUrl: './edit-item.component.html',
  styleUrl: './edit-item.component.scss'
})
export class EditItemComponent implements OnInit {
  @Input() tableName: string | undefined;
  @Input() recordId: string | undefined;

  itemData: any; // Data položky
  schema: Schema | undefined; // Metainformace o sloupcích
  editForm: FormGroup;

  constructor(
    private fb: FormBuilder,
    private route: ActivatedRoute,
    private schemaService: SchemaService,
    private http: HttpClient
  ) {
    this.editForm = this.fb.group({});
  }

  ngOnInit() {
    this.route.params.subscribe(params => {
      this.tableName = params['tableName'];
      this.recordId = params['recordId'];
      this.loadSchema();
      this.loadItemData();
    });
  }

  loadSchema() {
    if (!this.tableName) {
      return;
    }
    this.schemaService.getSchema(this.tableName).subscribe(schema => {
      this.schema = schema;
      this.createForm();
    });
  }

  loadItemData() {
    this.http.get(`/api/${this.tableName}/${this.recordId}`).subscribe(data => {
      this.itemData = data;
      this.createForm();
    });
  }

  createForm() {
    if (!this.schema || !this.itemData) {
      return;
    }
    for (const column of this.schema) {
      console.log(column)
      this.editForm.addControl(column.name, this.fb.control(this.itemData[column.name]));
    }
  }

  onSubmit() {
    if (this.editForm.valid) {
      // Odeslat data na server
      console.log(this.editForm.value);
      // Zde použijte HTTP klienta pro odeslání dat
    }
  }
}
