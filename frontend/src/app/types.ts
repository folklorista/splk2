export enum tableNames {
  persons = 'persons',
  loans = 'loans',
  loanItems = 'loan_items',
  categories = 'categories',
}

export interface TreeNode {
  id: string;
  children: TreeNode[];
  isExpanded?:boolean;
  [prop: string]: any;
}

export interface DropInfo {
    targetId: string;
    action?: string;
}
