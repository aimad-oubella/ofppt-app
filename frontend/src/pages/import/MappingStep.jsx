import { useState } from 'react';
import api, { errorMessage } from '../../api/client.js';
import { useToast } from '../../components/Toast.jsx';
import useLookups, { Options } from '../../hooks/useLookups.js';

const DEFAULT_SELECTS = {
  establishment: ['establishments', 'name'], level: ['levels', 'name'], filiere: ['filieres', 'name'],
  group: ['groups', 'name'], promotion: ['promotions', 'label'], category: ['categories', 'label'],
};
const DEFAULT_NUMBERS = ['training_year', 'graduation_year'];

export default function MappingStep({ batch, meta, onBatch, onValidated }) {
  const toast = useToast();
  const lookups = useLookups();
  const m = batch.mapping;
  const [sheet, setSheet] = useState(m.sheet);
  const [headerRow, setHeaderRow] = useState(m.header_row);
  const [fields, setFields] = useState(m.fields || {});
  const [options, setOptions] = useState({ ...meta.default_options, ...(m.options || {}), defaults: { ...(m.options?.defaults || {}) } });
  const [busy, setBusy] = useState(false);

  const setDefault = (key, value) => setOptions((o) => ({ ...o, defaults: { ...o.defaults, [key]: value } }));
  const toggleRequired = (key) => setOptions((o) => ({
    ...o,
    required_fields: o.required_fields.includes(key) ? o.required_fields.filter((k) => k !== key) : [...o.required_fields, key],
  }));

  async function reread() {
    setBusy(true);
    try {
      const res = await api.put(`/imports/${batch.id}/source`, { sheet: Number(sheet), header_row: Number(headerRow) });
      onBatch(res.data);
      toast.success('Fichier relu.');
    } catch (e) { toast.error(errorMessage(e)); } finally { setBusy(false); }
  }

  async function saveAndValidate() {
    setBusy(true);
    try {
      const body = {
        fields: Object.fromEntries(Object.entries(fields).map(([k, v]) => [k, v === '' || v === null ? null : Number(v)])),
        options,
      };
      await api.put(`/imports/${batch.id}/mapping`, body);
      const res = await api.post(`/imports/${batch.id}/validate`);
      toast.success('Contrôle terminé.');
      onValidated(res.data);
    } catch (e) { toast.error(errorMessage(e)); } finally { setBusy(false); }
  }

  const headers = m.headers;
  const groups = [...new Set(meta.fields.map((f) => f.group))];
  const requiredLocked = (key) => meta.always_required.includes(key);

  return (
    <>
      <div className="panel mb-3">
        <h2 className="h6">Aperçu du fichier</h2>
        <div className="row g-2 align-items-end mb-3">
          <div className="col-md-4">
            <label className="form-label small mb-1">Feuille</label>
            <select className="form-select" value={sheet} onChange={(e) => setSheet(e.target.value)}>
              {m.sheets.map((s, i) => <option key={i} value={i}>{s}</option>)}
            </select>
          </div>
          <div className="col-md-3">
            <label className="form-label small mb-1">Ligne des en-têtes</label>
            <input type="number" min="1" className="form-control" value={headerRow} onChange={(e) => setHeaderRow(e.target.value)} />
          </div>
          <div className="col-md-3"><button className="btn btn-outline-secondary" onClick={reread} disabled={busy}>Relire le fichier</button></div>
        </div>
        <div className="table-responsive" style={{ maxHeight: 320 }}>
          <table className="table table-sm table-bordered mb-0">
            <thead className="table-light"><tr><th>Ligne</th>{headers.map((h, i) => <th key={i}>{h}</th>)}</tr></thead>
            <tbody>
              {batch.preview.map((r) => (
                <tr key={r.row_number}><td className="text-muted">{r.row_number}</td>{headers.map((_, i) => <td key={i}>{String(r.cells[i] ?? '')}</td>)}</tr>
              ))}
            </tbody>
          </table>
        </div>
        <p className="text-muted small mt-2 mb-0">Les {batch.preview.length} premières lignes sur {batch.row_count}. Les dates enregistrées au format Excel s'affichent ici sous forme de nombres : elles seront converties à l'importation.</p>
      </div>

      <div className="panel mb-3">
        <h2 className="h6">Association des colonnes</h2>
        <p className="text-muted small">Pour chaque champ, choisissez la colonne du fichier. Les colonnes pré-sélectionnées ne sont que des suggestions. Aucun champ n'est obligatoire sauf ceux que vous cochez (nom et prénom sont imposés par la base).</p>
        {groups.map((g) => (
          <div key={g} className="mb-3">
            <h3 className="h6 text-muted">{g}</h3>
            <div className="table-responsive">
              <table className="table table-sm align-middle mb-0">
                <thead><tr><th style={{ width: '24%' }}>Champ</th><th style={{ width: '34%' }}>Colonne du fichier</th><th style={{ width: '12%' }}>Obligatoire</th><th>Valeur par défaut (si colonne absente ou cellule vide)</th></tr></thead>
                <tbody>
                  {meta.fields.filter((f) => f.group === g).map((f) => (
                    <tr key={f.key}>
                      <td>{f.label}</td>
                      <td>
                        <select className="form-select form-select-sm" value={fields[f.key] ?? ''} onChange={(e) => setFields((s) => ({ ...s, [f.key]: e.target.value }))}>
                          <option value="">— Ne pas importer —</option>
                          {headers.map((h, i) => <option key={i} value={i}>{h}</option>)}
                        </select>
                      </td>
                      <td>
                        <input type="checkbox" className="form-check-input" checked={requiredLocked(f.key) || options.required_fields.includes(f.key)}
                          disabled={requiredLocked(f.key)} onChange={() => toggleRequired(f.key)} aria-label={`${f.label} obligatoire`} />
                      </td>
                      <td>
                        {DEFAULT_SELECTS[f.key] && lookups && (
                          <select className="form-select form-select-sm" value={options.defaults[f.key] ?? ''} onChange={(e) => setDefault(f.key, e.target.value)}>
                            <option value="">Aucune</option>
                            <Options items={lookups[DEFAULT_SELECTS[f.key][0]]} label={DEFAULT_SELECTS[f.key][1]} />
                          </select>
                        )}
                        {DEFAULT_NUMBERS.includes(f.key) && (
                          <input type="number" className="form-control form-control-sm" value={options.defaults[f.key] ?? ''} onChange={(e) => setDefault(f.key, e.target.value)} />
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        ))}
        {lookups && lookups.establishments.length === 0 && (
          <div className="alert alert-warning mb-0">Aucun établissement n'existe encore : créez-en un dans « Référentiels » avant d'importer.</div>
        )}
      </div>

      <div className="panel mb-3">
        <h2 className="h6">Règles d'importation</h2>
        <div className="mb-3">
          <label className="form-label">Si un doublon potentiel est détecté</label>
          <select className="form-select" style={{ maxWidth: 520 }} value={options.on_duplicate} onChange={(e) => setOptions((o) => ({ ...o, on_duplicate: e.target.value }))}>
            <option value="skip">Ignorer la ligne (le dossier existant n'est pas touché)</option>
            <option value="complete">Compléter uniquement les champs vides du dossier existant</option>
          </select>
          <div className="form-text">Dans les deux cas, aucune valeur existante n'est écrasée.</div>
        </div>
        <div className="form-check mb-2">
          <input id="cm" type="checkbox" className="form-check-input" checked={options.create_missing} onChange={(e) => setOptions((o) => ({ ...o, create_missing: e.target.checked }))} />
          <label htmlFor="cm" className="form-check-label">Créer automatiquement les filières, promotions et groupes introuvables (jamais les établissements ni les niveaux)</label>
        </div>
        <div className="form-check">
          <input id="al" type="checkbox" className="form-check-input" checked={options.as_laureates} onChange={(e) => setOptions((o) => ({ ...o, as_laureates: e.target.checked }))} />
          <label htmlFor="al" className="form-check-label">Enregistrer aussi ces personnes comme lauréats</label>
        </div>
      </div>

      <button className="btn btn-primary" onClick={saveAndValidate} disabled={busy}>{busy ? 'Contrôle en cours…' : 'Enregistrer et contrôler les données'}</button>
    </>
  );
}
