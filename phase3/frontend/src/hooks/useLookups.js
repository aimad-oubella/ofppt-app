import { useEffect, useState } from 'react';
import api from '../api/client.js';

/** Charge les listes déroulantes (établissements, niveaux, filières, promotions, groupes, catégories). */
export default function useLookups() {
  const [data, setData] = useState(null);
  useEffect(() => {
    api.get('/lookups').then((r) => setData(r.data)).catch(() => setData(null));
  }, []);
  return data;
}

export function Options({ items, label = 'name' }) {
  return (
    <>
      {items.map((i) => (
        <option key={i.id} value={i.id}>{i[label]}</option>
      ))}
    </>
  );
}
