import { useEffect, useState } from 'react';
import api, { errorMessage } from '../api/client.js';
import { useToast } from '../components/Toast.jsx';
import Modal from '../components/Modal.jsx';

export default function References() {
  const toast = useToast();
  const [schema, setSchema] = useState([]);
  const [current, setCurrent] = useState(null);
  const [rows, setRows] = useState([]);
  const [options, setOptions] = useState({});
  const [editing, setEditing] = useState(null); // {id?, values}
  const [errors, setErrors] = useState({});

  useEffect(() => {
    api.get('/references').then((r) => { setSchema(r.data); setCurrent(r.data[0]?.key); }).catch((e) => toast.error(errorMessage(e)));
  }, []);

  const def = schema.find((s) => s.key === current);

  async function load() {
    if (!def) return;
    const list = await api.get(`/references/${def.key}`);
    setRows(list.data);
    const needed = [...new Set(def.columns.filter((c) => c.type === 'select').map((c) => c.options))];
    const loaded = {};
    for (const key of needed) loaded[key] = (await api.get(`/references/${key}`)).data;
    setOptions(loaded);
  }
  useEffect(() => { load().catch((e) => toast.error(errorMessage(e))); }, [current, schema]);

  function labelOf(col, value) {
    if (value === null || value === undefined) return '—';
    if (col.type === 'boolean') return value ? 'Oui' : 'Non';
    if (col.type === 'select') {
      const o = (options[col.options] || []).find((x) => x.id === value);
      return o ? o[col.option_label] : '—';
    }
    return value;
  }

  function openNew() {
    setErrors({});
    setEditing({ values: Object.fromEntries(def.columns.map((c) => [c.key, c.type === 'boolean' ? true : ''])) });
  }
  function openEdit(row) {
    setErrors({});
    setEditing({ id: row.id, values: Object.fromEntries(def.columns.map((c) => [c.key, row[c.key] ?? (c.type === 'boolean' ? false : '')])) });
  }

  async function save(e) {
    e.preventDefault();
    try {
      if (editing.id) await api.put(`/references/${def.key}/${editing.id}`, editing.values);
      else await api.post(`/references/${def.key}`, editing.values);
      toast.success('Enregistré.');
      setEditing(null);
      load();
    } catch (err) {
      if (err.response?.status === 422) setErrors(err.response.data.errors || {});
      toast.error(errorMessage(err));
    }
  }

  async function remove(row) {
    if (!window.confirm('Supprimer cet élément ?')) return;
    try { await api.delete(`/references/${def.key}/${row.id}`); toast.success('Supprimé.'); load(); }
    catch (err) { toast.error(errorMessage(err)); }
  }

  if (!def) return <p>Chargement…</p>;

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h1 className="h3 mb-0">Référentiels</h1>
        <button className="btn btn-primary" onClick={openNew}>Ajouter</button>
      </div>
      <ul className="nav nav-tabs mb-3">
        {schema.map((s) => (
          <li className="nav-item" key={s.key}>
            <button className={`nav-link ${s.key === current ? 'active' : ''}`} onClick={() => setCurrent(s.key)}>{s.label}</button>
          </li>
        ))}
      </ul>
      <div className="panel">
        <div className="table-responsive">
          <table className="table table-hover align-middle mb-0">
            <thead><tr>{def.columns.map((c) => <th key={c.key}>{c.label}</th>)}<th /></tr></thead>
            <tbody>
              {rows.length === 0 && <tr><td colSpan={def.columns.length + 1} className="text-muted">Aucun élément. Cliquez sur « Ajouter ».</td></tr>}
              {rows.map((r) => (
                <tr key={r.id}>
                  {def.columns.map((c) => <td key={c.key}>{labelOf(c, r[c.key])}</td>)}
                  <td className="text-end text-nowrap">
                    <button className="btn btn-sm btn-outline-primary me-1" onClick={() => openEdit(r)}>Modifier</button>
                    {def.deletable && <button className="btn btn-sm btn-outline-danger" onClick={() => remove(r)}>Supprimer</button>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {!def.deletable && <p className="text-muted small mt-2 mb-0">Cet élément ne se supprime pas : décochez « Actif » pour le désactiver.</p>}
      </div>

      {editing && (
        <Modal title={editing.id ? 'Modifier' : 'Ajouter'} onClose={() => setEditing(null)}
          footer={<><button className="btn btn-outline-secondary" onClick={() => setEditing(null)}>Annuler</button><button className="btn btn-primary" form="ref-form">Enregistrer</button></>}>
          <form id="ref-form" onSubmit={save}>
            {def.columns.map((c) => (
              <div className="mb-3" key={c.key}>
                {c.type === 'boolean' ? (
                  <div className="form-check">
                    <input id={c.key} type="checkbox" className="form-check-input" checked={!!editing.values[c.key]}
                      onChange={(e) => setEditing((s) => ({ ...s, values: { ...s.values, [c.key]: e.target.checked } }))} />
                    <label htmlFor={c.key} className="form-check-label">{c.label}</label>
                  </div>
                ) : (
                  <>
                    <label htmlFor={c.key} className="form-label">{c.label}{c.required && ' *'}</label>
                    {c.type === 'select' ? (
                      <select id={c.key} className={`form-select ${errors[c.key] ? 'is-invalid' : ''}`} value={editing.values[c.key]}
                        onChange={(e) => setEditing((s) => ({ ...s, values: { ...s.values, [c.key]: e.target.value } }))}>
                        <option value="">—</option>
                        {(options[c.options] || []).map((o) => <option key={o.id} value={o.id}>{o[c.option_label]}</option>)}
                      </select>
                    ) : (
                      <input id={c.key} type={c.type === 'number' ? 'number' : 'text'} className={`form-control ${errors[c.key] ? 'is-invalid' : ''}`}
                        value={editing.values[c.key]} onChange={(e) => setEditing((s) => ({ ...s, values: { ...s.values, [c.key]: e.target.value } }))} />
                    )}
                    {errors[c.key] && <div className="invalid-feedback">{errors[c.key][0]}</div>}
                  </>
                )}
              </div>
            ))}
          </form>
        </Modal>
      )}
    </>
  );
}
