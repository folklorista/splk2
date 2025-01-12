import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { DataService } from '../../services/data/data.service';
import { tableNames, TreeNode } from '../../types';
import { TreeViewComponent } from '../tree-view/tree-view.component';

@Component({
  selector: 'app-group-tree',
  templateUrl: './group-tree.component.html',
  styleUrls: ['./group-tree.component.scss'],
  imports: [
    TreeViewComponent,
  ],
  providers: [DataService]
})
export class GroupTreeComponent {
  nodes: TreeNode[] = [];

  constructor(
    private dataService: DataService,
    private router: Router,
  ) {
    this.loadGroups();
  }

  createGroup() {
    this.router.navigate(['groups/0/add']);
  }

  // Načtení dat
  loadGroups() {
    this.dataService.getData(tableNames.groups).subscribe((res) => {
      this.nodes = res.data;
      console.debug('Načtena stromová struktura:', this.nodes);
    });
  }

  // Uložení dat
  saveGroups() {
    if (this.nodes) {
      this.dataService.updateData(tableNames.groups, 0, this.nodes).subscribe(() => {
        console.debug('Stromová struktura byla úspěšně uložena.');
        alert('Změny byly uloženy!');
      });
    }
  }

  deleteGroup(group: TreeNode) {
    const deleteGroupRecursively = (groups: any[], target: any): boolean => {
      const index = groups.indexOf(target);
      if (index !== -1) {
        groups.splice(index, 1);
        return true;
      }
      for (const child of groups) {
        if (deleteGroupRecursively(child.children, target)) {
          return true;
        }
      }
      return false;
    };

    deleteGroupRecursively([this.nodes], group);
  }

  editGroup(group: TreeNode) {
    const tableName = 'groups'; // Název tabulky
    const recordId = group.id;  // ID uzlu
    const action = 'edit';      // Akce (editace)

    this.router.navigate([`${tableName}/${recordId}/${action}`]);
  }


  addSubgroup(group: TreeNode) {
    const newGroup: TreeNode = { id: '0', name: 'Nová skupina', children: [] };
    group.children.push(newGroup);
    alert(`Přidána podskupina k: ${group['name']}`);
  }

  moveGroup(event: { source: any; target: any }) {
    const source = event.source;
    const target = event.target;

    console.debug('Začínáme přesun skupiny:', { source, target });

    const removeGroup = (groups: any[], target: any): any => {
      for (let i = 0; i < groups.length; i++) {
        if (groups[i] === target) {
          console.debug('Odstraňuji skupinu:', groups[i]);
          return groups.splice(i, 1)[0];
        }

        // Rekurzivní volání pro podskupiny
        const result = removeGroup(groups[i].children, target);
        if (result) {
          return result;
        }
      }
      return null;
    };

    const groupToMove = removeGroup([this.nodes], source);
    if (!groupToMove) {
      console.error('Nepodařilo se najít skupinu k přesunutí:', source);
      return;
    }

    console.debug('Skupina byla úspěšně odstraněna:', groupToMove);

    if (!target.children) {
      console.error('Cílová skupina nemá pole "children":', target);
      target.children = [];
    }

    target.children.push(groupToMove);

    console.debug('Skupina byla úspěšně přesunuta pod cílovou skupinu:', { target });
    alert(`Skupina "${source.name}" byla přesunuta pod skupinu "${target.name}".`);
  }

  swapGroups(event: { parent: any; indexA: number; indexB: number }) {
    console.debug('Funkce "swapGroups" volána s událostí:', event);

    const { parent, indexA, indexB } = event;

    if (
      indexA >= 0 &&
      indexB >= 0 &&
      indexA < parent.children.length &&
      indexB < parent.children.length
    ) {
      console.debug('Prohazuji skupiny:', {
        indexA,
        groupA: parent.children[indexA],
        indexB,
        groupB: parent.children[indexB],
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
