import { useEffect, useState } from 'react';
import api, { errorMessage } from '../../api/client.js';
import { useToast } from '../../components/Toast.jsx';
import Modal from '../../components/Modal.jsx';
import Pagination from '../../components/Pagination.jsx';

export const ROW_STATUS = {
  valide: ['Valide', 'success'], doublon: ['Doublon', 'warning'], rejetee: ['Rejetée', 'danger'],
  ignoree: ['Ignorée', 'secondary'], importee: ['Importée', 'success'], mise_a_jour: ['Complétée', 'info'], en_attente: ['En attente', 'secondary'],
};

export function Messages({ messages }) {
  const m = messages || {};
  return (
    <>
      {(m.errors || []).map((x, i) => <div key={`e${i}`} className="text-danger small">{x}</div>)}
      {m.duplicate && <div className="text-warning-emphasis small">{m.duplicate.reason}</div>}
      {(m.warnings || []).map((x, i) => <div key={`w${i}`} className="text-muted small">{x}</div>)}
    </>
  );
}

export function Totals({ totals, onPick, active }) {
  const cards = [
    ['valides', 'Prêtes à importer', 'valide'], ['doublons', 'Doublons potentiels', 'doublon'],
    ['rejetees', 'Rejetées', 'rejetee'], ['ignorees', 'Ignorées', 'ignoree'],
  ];
  return (
    <div className="row g-2 mb-3">
      {cards.map(([k, label, status]) => (
        <div className="col-6 col-md-3" key={k}>
          <div className={`panel h-100 ${active === status ? 'border-primary' : ''}`} style={{ cursor: onPick ? 'pointer' : 'default' }} onClick={() => onPick && onPick(active === status ? '' : status)}>
            <div className="text-muted small">{label}</div>
            <div className="stat-value">{totals?.[k] ?? 0}</div>
          </div>
        </div>
      ))}
    </div>
  );
}

export default function ReviewStep({ batch, meta, onBatch, onBack, onExecuted }) {
  const toast = useToast();
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState(null);
  const [editing, setEditing] = useState(null);
  const [confirm, setConfirm] = useState(false);
  const [busy, setBusy] = useState(false);

  const fieldMap = batch.mapping.fields || {};
  const editable = meta.fields.filter((f) => fieldMap[f.key] !== null && fieldMap[f.key] !== undefined || meta.always_required.includes(f.key));
  const totals = batch.totals || {};

  async function load() {
    try {
      const res = await api.get(`/imports/${batch.id}/rows`, { params: { status: status || undefined, page, per_page: 25 } });
      setRows(res.data);
    } catch (e) { toast.error(errorMessage(e)); }
  }
  useEffect(() => { load(); }, [status, page, batch.totals]);

  async function refreshBatch() {
    const res = await api.get(`/imports/${batch.id}`);
    onBatch(res.data);
  }

  async function revalidate() {
    setBusy(true);
    try { const res = await api.post(`/imports/${batch.id}/validate`); onBatch(res.data); toast.success('Contrôle relancé.'); }
    catch (e) { toast.error(errorMessage(e)); } finally { setBusy(false); }
  }

  async function saveRow(e) {
    e.preventDefault();
    try {
      await api.patch(`/imports/${batch.id}/rows/${editing.id}`, { overrides: editing.values });
      toast.success('Ligne corrigée.');
      setEditing(null);
      await refreshBatch();
    } catch (err) { toast.error(errorMessage(err)); }
  }

  async function toggleIgnore(row) {
    try {
      await api.post(`/imports/${batch.id}/rows/${row.id}/ignore`, { ignore: row.status !== 'ignoree' });
      await refreshBatch();
    } catch (e) { toast.error(errorMessage(e)); }
  }

  async function execute() {
    setBusy(true);
    try {
      const res = await api.post(`/imports/${batch.id}/execute`, { confirm: true });
      toast.success('Importation terminée.');
      onExecuted(res.data.batch);
    } catch (e) { toast.error(errorMessage(e)); setBusy(false); }
  }

  const importable = (totals.valides || 0) + (batch.mapping.options?.on_duplicate === 'complete' ? totals.doublons || 0 : 0);

  return (
    <>
      <Totals totals={totals} active={status} onPick={(s) => { setStatus(s); setPage(1); }} />
      {totals.sans_categorie > 0 && (
        <div className="alert alert-warning">
          {totals.sans_categorie} ligne(s) sans catégorie (régulier ou libre). Retournez à l'étape « Colonnes » pour associer une colonne de catégorie ou choisir une catégorie par défaut, ou importez-les telles quelles et confirmez la catégorie ensuite (filtre « Sans catégorie » de la liste des stagiaires).
        </div>
      )}

      <div className="panel mb-3">
        <div className="table-responsive">
          <table className="table table-sm align-middle mb-0">
            <thead><tr><th>Ligne</th><th>État</th><th>Nom et prénom</th><th>CEF</th><th>Contrôles</th><th /></tr></thead>
            <tbody>
              {!rows && <tr><td colSpan="6">Chargement…</td></tr>}
              {rows && rows.data.length === 0 && <tr><td colSpan="6" className="text-muted">Aucune ligne dans cette catégorie.</td></tr>}
              {rows && rows.data.map((r) => {
                const [label, color] = ROW_STATUS[r.status] || [r.status, 'secondary'];
                return (
                  <tr key={r.id}>
                    <td>{r.row_number}</td>
                    <td><span className={`badge text-bg-${color}`}>{label}</span></td>
                    <td>{r.values.last_name || '—'} {r.values.first_name || ''}</td>
                    <td>{r.values.cef || '—'}</td>
                    <td><Messages messages={r.messages} /></td>
                    <td className="text-end text-nowrap">
                      {r.status !== 'importee' && <button className="btn btn-sm btn-outline-primary me-1" onClick={() => setEditing({ id: r.id, values: Object.fromEntries(editable.map((f) => [f.key, r.values[f.key] ?? ''])) })}>Corriger</button>}
                      {r.status !== 'importee' && <button className="btn btn-sm btn-outline-secondary" onClick={() => toggleIgnore(r)}>{r.status === 'ignoree' ? 'Rétablir' : 'Ignorer'}</button>}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        {rows && <Pagination page={rows.current_page} lastPage={rows.last_page} total={rows.total} onChange={setPage} />}
      </div>

      <div className="panel">
        <h2 className="h6">Importer</h2>
        <p className="mb-2">
          {importable} ligne(s) seront enregistrées. {totals.rejetees || 0} rejetée(s) et {totals.ignorees || 0} ignorée(s) ne le seront pas.
          {batch.mapping.options?.on_duplicate === 'skip' && (totals.doublons || 0) > 0 && ` Les ${totals.doublons} doublon(s) seront ignorés.`}
        </p>
        <div className="form-check mb-3">
          <input id="conf" type="checkbox" className="form-check-input" checked={confirm} onChange={(e) => setConfirm(e.target.checked)} />
          <label htmlFor="conf" className="form-check-label">Je confirme l'enregistrement de ces données dans la base.</label>
        </div>
        <div className="d-flex gap-2 flex-wrap">
          <button className="btn btn-outline-secondary" onClick={onBack}>Modifier l'association des colonnes</button>
          <button className="btn btn-outline-secondary" onClick={revalidate} disabled={busy}>Relancer le contrôle</button>
          <button className="btn btn-primary" onClick={execute} disabled={!confirm || busy || importable === 0}>{busy ? 'Importation…' : "Lancer l'importation"}</button>
        </div>
      </div>

      {editing && (
        <Modal title="Corriger la ligne" onClose={() => setEditing(null)}
          footer={<><button className="btn btn-outline-secondary" onClick={() => setEditing(null)}>Annuler</button><button className="btn btn-primary" form="row-form">Enregistrer et recontrôler</button></>}>
          <p className="text-muted small">Les valeurs saisies remplacent celles du fichier pour cette ligne. Le fichier d'origine n'est pas modifié. Dates : jj/mm/aaaa.</p>
          <form id="row-form" onSubmit={saveRow}>
            {editable.map((f) => (
              <div className="mb-2" key={f.key}>
                <label className="form-label small mb-1" htmlFor={`f-${f.key}`}>{f.label}</label>
                <input id={`f-${f.key}`} className="form-control form-control-sm" value={editing.values[f.key]}
                  onChange={(e) => setEditing((s) => ({ ...s, values: { ...s.values, [f.key]: e.target.value } }))} />
              </div>
            ))}
          </form>
        </Modal>
      )}
    </>
  );
}
