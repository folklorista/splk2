import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';

@Component({
  selector: 'app-pagination',
  imports: [CommonModule],
  templateUrl: './pagination.component.html',
  styleUrl: './pagination.component.scss'
})
export class PaginationComponent {
  @Input() offset!: number;
  @Input() limit!: number;
  @Input() totalRecords!: number;
  @Input() pageCount!: number;

  @Output() pageChange = new EventEmitter<number>();
  @Output() limitChange = new EventEmitter<number>();

  changePage(pageNumber: number) {
    this.pageChange.emit(pageNumber);
  }

  changeLimit(event: Event) {
    const newLimit = +(event.target as HTMLSelectElement).value;
    this.limitChange.emit(newLimit);
  }
}
