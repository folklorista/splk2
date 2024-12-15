import { Component, Input, Output, EventEmitter } from '@angular/core';

@Component({
  selector: 'app-category-node',
  templateUrl: './category-node.component.html',
  styleUrls: ['./category-node.component.scss'],
  standalone: true,
  imports: []
})
export class CategoryNodeComponent {
  @Input() category!: { name: string; children: any[] };
  @Input() parent!: any; // Rodič kategorie
  @Input() index!: number | null; // Index v rámci rodiče
  @Output() onEdit = new EventEmitter<any>();
  @Output() onDelete = new EventEmitter<any>();
  @Output() onAdd = new EventEmitter<any>();
  @Output() onMove = new EventEmitter<{ source: any; target: any }>();
  @Output() onSwap = new EventEmitter<{ parent: any; indexA: number; indexB: number }>();


  editCategory() {
    const newName = prompt('Zadejte nový název pro kategorii:', this.category.name);
    if (newName) {
      this.category.name = newName;
      this.onEdit.emit(this.category);
    }
  }

  deleteCategory() {
    if (confirm(`Opravdu chcete smazat kategorii "${this.category.name}"?`)) {
      this.onDelete.emit(this.category);
    }
  }

  addSubcategory() {
    const newCategoryName = prompt('Zadejte název nové podkategorie:');
    if (newCategoryName) {
      const newCategory = { name: newCategoryName, children: [] }; // Pole children je prázdné
      this.category.children.push(newCategory);
      console.debug(`Přidána nová podkategorie: ${newCategoryName}`, newCategory);
    } else {
      console.debug('Přidání podkategorie bylo zrušeno.');
    }
  }



  onCategoryClick() {
    alert(`Kategorie vybrána: ${this.category.name}`);
  }

  moveCategory() {
    console.log('Tlačítko "Přesunout" bylo stisknuto pro kategorii:', this.category);

    const targetName = prompt('Zadejte název cílové kategorie:');
    if (targetName) {
      console.log('Hledám cílovou kategorii s názvem:', targetName);
      const target = this.findCategoryByName(this.parent, targetName); // Najdeme cílovou kategorii
      if (target) {
        console.log('Cílová kategorie nalezena:', target);
        this.onMove.emit({ source: this.category, target });
      } else {
        console.error('Kategorie s tímto názvem nebyla nalezena:', targetName);
        alert(`Kategorie "${targetName}" nebyla nalezena.`);
      }
    }
  }

  findCategoryByName(categories: any, name: string): any {
    if (!categories) return null;

    for (const category of categories) {
      if (category.name === name) {
        console.debug('Kategorie nalezena:', category);
        return category;
      }

      const result = this.findCategoryByName(category.children, name);
      if (result) {
        return result;
      }
    }
    return null;
  }

  moveUp() {
    console.debug(`Zkouším posunout kategorii "${this.category.name}" nahoru.`);
    if (this.parent && this.index !== null && this.index > 0) {
      console.debug(
        `Emitování události "onSwap" pro posunutí nahoru: { parent: ${this.parent.name}, indexA: ${this.index}, indexB: ${this.index - 1} }`
      );
      this.onSwap.emit({ parent: this.parent, indexA: this.index, indexB: this.index - 1 });
    } else {
      console.debug(`Kategorie "${this.category.name}" nelze posunout nahoru.`);
    }
  }

  moveDown() {
    console.debug(`Zkouším posunout kategorii "${this.category.name}" dolů.`);
    if (this.parent && this.index !== null && this.index < this.parent.children.length - 1) {
      console.debug(
        `Emitování události "onSwap" pro posunutí dolů: { parent: ${this.parent.name}, indexA: ${this.index}, indexB: ${this.index + 1} }`
      );
      this.onSwap.emit({ parent: this.parent, indexA: this.index, indexB: this.index + 1 });
    } else {
      console.debug(`Kategorie "${this.category.name}" nelze posunout dolů.`);
    }
  }

}
