import { Routes } from '@angular/router';
import { authGuard } from './auth.guard';
import { CardPersonComponent } from './components/card-person/card-person.component';
import { CategoryTreeComponent } from './components/category-tree/category-tree.component';
import { DashboardComponent } from './components/dashboard/dashboard.component';
import { DataListComponent } from './components/data-list/data-list.component';
import { EditItemComponent } from './components/edit-item/edit-item.component';
import { GroupTreeComponent } from './components/group-tree/group-tree.component';
import { LoginComponent } from './components/login/login.component';
import { PageNotFoundComponent } from './components/page-not-found/page-not-found.component';
import { RegisterComponent } from './components/register/register.component';

// Add routes for all existing components
export const routes: Routes = [

  { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
  { path: 'login', title: 'Přihlášení', component: LoginComponent },
  { path: 'register', title: 'Registrace', component: RegisterComponent },
  { path: 'dashboard', title: 'Přehled', component: DashboardComponent, canActivate: [authGuard] },
  { path: 'persons/:personId', title: 'Karta osoby', component: CardPersonComponent, canActivate: [authGuard] },
  { path: 'categories', title: 'Kategorie', component: CategoryTreeComponent, canActivate: [authGuard] },
  { path: 'groups', title: 'Skupiny', component: GroupTreeComponent, canActivate: [authGuard] },
  { path: ':tableName', title: 'List', component: DataListComponent, canActivate: [authGuard] },
  { path: ':tableName/add', title: 'Add Item', component: EditItemComponent, canActivate: [authGuard], data: { action: 'add' } },
  { path: ':tableName/:recordId', title: 'View Item', component: EditItemComponent, canActivate: [authGuard], data: { action: 'view' } },
  { path: ':tableName/:recordId/:action', title: 'Edit Item', component: EditItemComponent, canActivate: [authGuard] },
  { path: '**', component: PageNotFoundComponent }
];
