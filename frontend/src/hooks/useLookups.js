import { createElement, useEffect, useState } from 'react';
import api from '../api/client.js';

/** Charge les listes déroulantes (établissements, niveaux, filières, promotions, groupes, catégories). */
export default function useLookups() {
  const [data, setData] = useState(null);
  useEffect(() => {
    api.get('/lookups').then((r) => setData(r.data)).catch(() => setData(null));
  }, []);
  return data;
}

/** Liste d'<option>. Écrit sans JSX car ce fichier a l'extension .js (Vite n'accepte le JSX que dans .jsx). */
export function Options({ items, label = 'name' }) {
  return items.map((i) => createElement('option', { key: i.id, value: i.id }, i[label]));
}