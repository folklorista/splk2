export interface Schema {
  name: string;
  comment: string;
  columns: SchemaField[];
}

export type SchemaFieldType = 'number' | 'string' | 'enum' | 'Date' | 'boolean' | 'text';

export interface SchemaField {
  name: string;
  type: SchemaFieldType;
  options: string[];
  null: boolean;
  key:  "PRI" | "UNI" | "MUL" | "";
  default: number | string | null; // Typ defaultní hodnoty
  extra: string;
  comment: string;
}
