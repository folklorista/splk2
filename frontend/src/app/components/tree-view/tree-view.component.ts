import { debounce } from '@agentepsilon/decko';
import { CdkDrag, CdkDragMove, CdkDropList } from '@angular/cdk/drag-drop';
import { DOCUMENT, NgTemplateOutlet } from '@angular/common';
import { Component, EventEmitter, Inject, Input, OnInit, Output } from '@angular/core';
import { DropInfo, TreeNode } from '../../types';
@Component({
  selector: 'app-tree-view',
  imports: [
    CdkDrag,
    CdkDropList,
    NgTemplateOutlet,
  ],
  templateUrl: './tree-view.component.html',
  styleUrl: './tree-view.component.scss'

})
export class TreeViewComponent implements OnInit {

  @Input() nodes!: TreeNode[];
  @Output() onEdit = new EventEmitter<any>();
  @Output() onDelete = new EventEmitter<any>();
  @Output() onAdd = new EventEmitter<any>();
  @Output() onMove = new EventEmitter<{ source: any; target: any }>();
  @Output() onSwap = new EventEmitter<{ parent: any; indexA: number; indexB: number }>();

  dropTargetIds: string[] = [];
  nodeLookup: Record<string, TreeNode> = {};
  dropActionTodo: DropInfo | null = null;

  constructor(@Inject(DOCUMENT) private document: Document) {
  }

  ngOnInit(): void {
    this.prepareDragDrop(this.nodes);
  }

  prepareDragDrop(nodes: TreeNode[]) {
    nodes.forEach(node => {
      this.dropTargetIds.push(node.id);
      this.nodeLookup[node.id] = node;
      this.prepareDragDrop(node.children);
    });
  }

  @debounce(50)
  dragMoved(event: CdkDragMove) {
    const e = this.document.elementFromPoint(event.pointerPosition.x, event.pointerPosition.y);
    if (!e) {
      this.clearDragInfo();
      return;
    }

    const container = e.classList.contains('node-item') ? e : e.closest('.node-item');
    if (!container) {
      this.clearDragInfo();
      return;
    }

    this.dropActionTodo = { targetId: container.getAttribute('data-id') ?? ''};
    const targetRect = container.getBoundingClientRect();
    const oneThird = targetRect.height / 3;

    if (event.pointerPosition.y - targetRect.top < oneThird) {
      this.dropActionTodo['action'] = 'before';
    } else if (event.pointerPosition.y - targetRect.top > 2 * oneThird) {
      this.dropActionTodo['action'] = 'after';
    } else {
      this.dropActionTodo['action'] = 'inside';
    }
    this.showDragInfo();
  }

  drop(event: any) {
    if (!this.dropActionTodo) return;

    const draggedItemId = event.item.data;
    const parentItemId = event.previousContainer.id;
    const targetListId = this.getParentNodeId(this.dropActionTodo.targetId, this.nodes, 'main');

    const draggedItem = this.nodeLookup[draggedItemId];
    const oldContainer = parentItemId !== 'main' ? this.nodeLookup[parentItemId].children : this.nodes;
    const newContainer = targetListId !== 'main' ? this.nodeLookup[targetListId ?? ''].children : this.nodes;

    const index = oldContainer.findIndex(item => item.id === draggedItemId);
    oldContainer.splice(index, 1);

    switch (this.dropActionTodo.action) {
      case 'before':
      case 'after':
        const targetIndex = newContainer.findIndex(item => item.id === this.dropActionTodo?.targetId);
        if (this.dropActionTodo.action === 'before') {
          newContainer.splice(targetIndex, 0, draggedItem);
        } else {
          newContainer.splice(targetIndex + 1, 0, draggedItem);
        }
        break;
      case 'inside':
        this.nodeLookup[this.dropActionTodo.targetId].children.push(draggedItem);
        this.nodeLookup[this.dropActionTodo.targetId].isExpanded = true;
        break;
    }

    this.clearDragInfo(true);
  }

  getParentNodeId(id: string, nodesToSearch: TreeNode[], parentId: string): string | null {
    for (const node of nodesToSearch) {
      if (node.id === id) return parentId;
      const ret = this.getParentNodeId(id, node.children, node.id);
      if (ret) return ret;
    }
    return null;
  }

  showDragInfo() {
    this.clearDragInfo();
    if (this.dropActionTodo) {
      const element = this.document.getElementById('node-' + this.dropActionTodo.targetId);
      element?.classList.add('drop-' + this.dropActionTodo.action);
    }
  }

  clearDragInfo(dropped = false) {
    if (dropped) {
      this.dropActionTodo = null;
    }
    this.document.querySelectorAll('.drop-before, .drop-after, .drop-inside').forEach(element => {
      element.classList.remove('drop-before', 'drop-after', 'drop-inside');
    });
  }

  emitEditEvent(node: TreeNode): void {
    this.onEdit.emit(node);
  }
}
