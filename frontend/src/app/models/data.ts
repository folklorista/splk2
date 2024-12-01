export interface ItemData {
  [key: string]: any;
  id?: number;
  created_at?: string;
  updated_at?: string;
}

export interface ForeignKeyData {
  [key: string]: {
    id: number,
    name: string
  }[]
}
