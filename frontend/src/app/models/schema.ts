export type SchemaFieldType = 'number' | 'string' | 'enum' | 'Date' | 'boolean' | 'text';

export interface SchemaField {
  name: string;
  type: SchemaFieldType;
  options: string[];
  null: boolean;
  key: string; // Můžete také použít unii jako "key: 'PRI' | '' | ... " pokud znáte přesné hodnoty
  default: number | string | null; // Typ defaultní hodnoty
  extra: string;
}

export type Schema = SchemaField[];
