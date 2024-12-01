export interface Schema {
  name: string;
  comment: string;
  columns: SchemaField[];
}

export type SchemaFieldType = 'number' | 'string' | 'enum' | 'Date' | 'boolean' | 'text';

export interface SchemaField {
  [x: string]: any;
  name: string;                          // Název sloupce
  type: SchemaFieldType;                  // Typ sloupce (např. string, number, Date, atd.)
  options: string[];                      // Možnosti pro ENUM typy
  null: boolean;                          // Informace, zda sloupec může obsahovat NULL hodnoty
  key: "PRI" | "UNI" | "MUL" | "";        // Typ klíče (primární klíč, unikátní klíč, cizí klíč nebo žádný)
  default: number | string | null;        // Typ defaultní hodnoty (může být číslo, string nebo null)
  extra: string;                          // Další informace (např. auto-increment, atd.)
  comment: string;                        // Komentář k sloupci
  foreign_key?: {                        // Informace o cizím klíči
    constraint: string;                   // Název cizího klíče
    referenced_table: string;             // Název tabulky, na kterou cizí klíč odkazuje
    referenced_column: string;            // Název sloupce v referencované tabulce
  };
  references?: {                          // Informace o referencích na tento sloupec
    constraint: string;                   // Název cizího klíče
    table: string;                        // Tabulka, která na tento sloupec odkazuje
    column: string;                       // Sloupec, který na tento sloupec odkazuje
  }[];
}
