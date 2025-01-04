import { Component } from '@angular/core';
import { CategoryNodeComponent } from '../category-node/category-node.component';
import { DataService } from '../../services/data/data.service';
import { tableNames } from '../../types';

@Component({
    selector: 'app-category-tree',
    templateUrl: './category-tree.component.html',
    styleUrls: ['./category-tree.component.scss'],
    imports: [CategoryNodeComponent],
    providers: [DataService]
})
export class CategoryTreeComponent {
  rootCategory: any;

  constructor(private dataService: DataService) {
    this.loadCategories();
  }

  // Načtení dat
  loadCategories() {
    this.dataService.getData(tableNames.categories).subscribe((res) => {
      this.rootCategory = res.data[0];
      console.debug('Načtena stromová struktura:', this.rootCategory);
    });
  }

  // Uložení dat
  saveCategories() {
    if (this.rootCategory) {
      this.dataService.createData(tableNames.categories, this.rootCategory).subscribe(() => {
        console.debug('Stromová struktura byla úspěšně uložena.');
        alert('Změny byly uloženy!');
      });
    }
  }

  deleteCategory(category: any) {
    const deleteCategoryRecursively = (categories: any[], target: any): boolean => {
      const index = categories.indexOf(target);
      if (index !== -1) {
        categories.splice(index, 1);
        return true;
      }
      for (const child of categories) {
        if (deleteCategoryRecursively(child.children, target)) {
          return true;
        }
      }
      return false;
    };

    deleteCategoryRecursively([this.rootCategory], category);
  }

  editCategory(category: any) {
    alert(`Kategorie "${category.name}" byla upravena.`);
  }


  addSubcategory(category: any) {
    const newCategory = { name: 'Nová kategorie', children: [] };
    category.children.push(newCategory);
    alert(`Přidána podkategorie k: ${category.name}`);
  }

  moveCategory(event: { source: any; target: any }) {
    const source = event.source;
    const target = event.target;

    console.debug('Začínáme přesun kategorie:', { source, target });

    const removeCategory = (categories: any[], target: any): any => {
      for (let i = 0; i < categories.length; i++) {
        if (categories[i] === target) {
          console.debug('Odstraňuji kategorii:', categories[i]);
          return categories.splice(i, 1)[0];
        }

        // Rekurzivní volání pro podkategorie
        const result = removeCategory(categories[i].children, target);
        if (result) {
          return result;
        }
      }
      return null;
    };

    const categoryToMove = removeCategory([this.rootCategory], source);
    if (!categoryToMove) {
      console.error('Nepodařilo se najít kategorii k přesunutí:', source);
      return;
    }

    console.debug('Kategorie byla úspěšně odstraněna:', categoryToMove);

    if (!target.children) {
      console.error('Cílová kategorie nemá pole "children":', target);
      target.children = [];
    }

    target.children.push(categoryToMove);

    console.debug('Kategorie byla úspěšně přesunuta pod cílovou kategorii:', { target });
    alert(`Kategorie "${source.name}" byla přesunuta pod kategorii "${target.name}".`);
  }

  swapCategories(event: { parent: any; indexA: number; indexB: number }) {
    console.debug('Funkce "swapCategories" volána s událostí:', event);

    const { parent, indexA, indexB } = event;

    if (
      indexA >= 0 &&
      indexB >= 0 &&
      indexA < parent.children.length &&
      indexB < parent.children.length
    ) {
      console.debug('Prohazuji kategorie:', {
        indexA,
        categoryA: parent.children[indexA],
        indexB,
        categoryB: parent.children[indexB],
      });

      const temp = parent.children[indexA];
      parent.children[indexA] = parent.children[indexB];
      parent.children[indexB] = temp;

      console.debug('Prohození úspěšné. Nové pořadí:', parent.children);
    } else {
      console.error('Indexy jsou mimo rozsah. Prohození neprovedeno:', { indexA, indexB });
    }
  }
}
