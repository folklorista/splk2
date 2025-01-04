import { Component, Input, OnInit } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { CommonModule } from '@angular/common';
import { DataService } from '../../services/data/data.service';
import { tableNames } from '../../types';


@Component({
    selector: 'app-card-person',
    imports: [CommonModule],
    templateUrl: './card-person.component.html',
    styleUrl: './card-person.component.scss'
})
export class CardPersonComponent implements OnInit {
  @Input() personId!: number;

  person: any = {};
  loans: any[] = [];

  constructor(
    private dataService: DataService,
  ) { }

  async ngOnInit(): Promise<void> {
    await this.fetchPersonData();
    this.fetchLoans();
  }

  async fetchPersonData(): Promise<void> {
    try {
      const res = await firstValueFrom(this.dataService.getData(tableNames.persons, this.personId));
      this.person = res.data;
    } catch (error) {
      console.error('Error loading person data:', error);
    }
  }

  async fetchLoans(): Promise<void> {
    try {
      const res = await firstValueFrom(this.dataService.getData(tableNames.persons, this.personId));
      this.loans = res.data.map((loan: any) => ({
        ...loan,
        loan_items: loan.loan_items || []
      }));
    } catch (error) {
      console.error('Error loading person data:', error);
    }
  }
}
