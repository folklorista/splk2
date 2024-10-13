import { DashboardComponent } from './components/dashboard/dashboard.component';
import { DataListComponent } from './components/data-list/data-list.component';
import { LoginComponent } from './components/login/login.component';
import { PageNotFoundComponent } from './components/page-not-found/page-not-found.component';
import { Routes } from '@angular/router';
import { authGuard } from './auth.guard';
import { EditItemComponent } from './components/edit-item/edit-item.component';

// Add routes for all existing components
export const routes: Routes = [

  { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
  { path: 'login', title: 'Přihlášení', component: LoginComponent },
  { path: 'dashboard', title: 'Přehled', component: DashboardComponent, canActivate: [authGuard] },
  { path: ':tableName', title: 'List', component: DataListComponent, canActivate: [authGuard] },
  { path: ':tableName/:recordId', title: 'Edit Item', component: EditItemComponent, canActivate: [authGuard] },
  { path: ':tableName/:recordId/edit', title: 'Edit Item', component: EditItemComponent, canActivate: [authGuard] },
  { path: '**', component: PageNotFoundComponent }
];
