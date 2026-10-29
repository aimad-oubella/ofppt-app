import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api, { cleanParams, downloadFile, errorMessage } from '../api/client.js';
import { useAuth } from '../auth/AuthContext.jsx';
import { useToast } from '../components/Toast.jsx';
import Pagination from '../components/Pagination.jsx';
import useLookups, { Options } from '../hooks/useLookups.js';

const EMPTY = {
  q: '', establishment_id: '', category_id: '', level_id: '', filiere_id: '',
  group_id: '', promotion_id: '', training_year: '', archived: '', uncategorized: '',
};

export default function Trainees() {
  const { user } = useAuth();
  const toast = useToast();
  const lookups = useLookups();
  const [filters, setFilters] = useState(EMPTY);
  const [page, setPage] = useState(1);
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(false);
  const [reload, setReload] = useState(0);
  const canWrite = user.role !== 'consultation';

  useEffect(() => {
    const timer = setTimeout(async () => {
      setLoading(true);
      try {
        const res = await api.get('/trainees', { params: cleanParams({ ...filters, page, per_page: 25 }) });
        setResult(res.data);
      } catch (e) {
        toast.error(errorMessage(e));
      } finally {
        setLoading(false);
      }
    }, 250);
    return () => clearTimeout(timer);
  }, [filters, page, reload]);

  function change(name, value) {
    setFilters((f) => ({ ...f, [name]: value }));
    setPage(1);
  }

  async function archive(t) {
    if (!window.confirm(`Archiver le dossier de ${t.last_name} ${t.first_name} ? Il pourra être restauré.`)) return;
    try {
      await api.delete(`/trainees/${t.id}`);
      toast.success('Dossier archivé.');
      setReload((n) => n + 1);
    } catch (e) { toast.error(errorMessage(e)); }
  }

  async function restore(t) {
    try {
      await api.post(`/trainees/${t.id}/restore`);
      toast.success('Dossier restauré.');
      setReload((n) => n + 1);
    } catch (e) { toast.error(errorMessage(e)); }
  }

  async function exportXlsx() {
    try { await downloadFile('/trainees/export', filters, 'stagiaires.xlsx'); }
    catch (e) { toast.error(errorMessage(e)); }
  }

  const groups = lookups ? lookups.groups.filter((g) => !filters.filiere_id || String(g.filiere_id) === String(filters.filiere_id)) : [];

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 className="h3 mb-0">Stagiaires</h1>
        <div className="d-flex gap-2">
          <button className="btn btn-outline-secondary" onClick={exportXlsx}>Exporter en Excel</button>
          {canWrite && <Link className="btn btn-primary" to="/stagiaires/nouveau">Nouveau stagiaire</Link>}
        </div>
      </div>

      <div className="panel mb-3">
        <div className="row g-2">
          <div className="col-md-4">
            <input className="form-control" placeholder="Rechercher : nom, prénom, CEF, n° d'inscription, matricule, CIN"
              value={filters.q} onChange={(e) => change('q', e.target.value)} />
          </div>
          {lookups && (
            <>
              <div className="col-md-2">
                <select className="form-select" value={filters.establishment_id} onChange={(e) => change('establishment_id', e.target.value)}>
                  <option value="">Tous les établissements</option>
                  <Options items={lookups.establishments} />
                </select>
              </div>
              <div className="col-md-2">
                <select className="form-select" value={filters.category_id} onChange={(e) => change('category_id', e.target.value)}>
                  <option value="">Toutes les catégories</option>
                  <Options items={lookups.categories} label="label" />
                </select>
              </div>
              <div className="col-md-2">
                <select className="form-select" value={filters.level_id} onChange={(e) => change('level_id', e.target.value)}>
                  <option value="">Tous les niveaux</option>
                  <Options items={lookups.levels} />
                </select>
              </div>
              <div className="col-md-2">
                <select className="form-select" value={filters.filiere_id} onChange={(e) => change('filiere_id', e.target.value)}>
                  <option value="">Toutes les filières</option>
                  <Options items={lookups.filieres} />
                </select>
              </div>
              <div className="col-md-2">
                <select className="form-select" value={filters.group_id} onChange={(e) => change('group_id', e.target.value)}>
                  <option value="">Tous les groupes</option>
                  <Options items={groups} />
                </select>
              </div>
              <div className="col-md-2">
                <select className="form-select" value={filters.promotion_id} onChange={(e) => change('promotion_id', e.target.value)}>
                  <option value="">Toutes les promotions</option>
                  <Options items={lookups.promotions} label="label" />
                </select>
              </div>
              <div className="col-md-2">
                <select className="form-select" value={filters.training_year} onChange={(e) => change('training_year', e.target.value)}>
                  <option value="">Toute année de formation</option>
                  {[1, 2, 3].map((y) => <option key={y} value={y}>{y === 1 ? '1re année' : `${y}e année`}</option>)}
                </select>
              </div>
            </>
          )}
          <div className="col-md-2">
            <select className="form-select" value={filters.archived} onChange={(e) => change('archived', e.target.value)}>
              <option value="">Dossiers actifs</option>
              <option value="only">Dossiers archivés</option>
              <option value="with">Actifs et archivés</option>
            </select>
          </div>
          <div className="col-md-2 d-flex align-items-center gap-2">
            <div className="form-check">
              <input id="unc" className="form-check-input" type="checkbox" checked={!!filters.uncategorized}
                onChange={(e) => change('uncategorized', e.target.checked ? '1' : '')} />
              <label htmlFor="unc" className="form-check-label">Sans catégorie</label>
            </div>
            <button className="btn btn-link btn-sm" onClick={() => { setFilters(EMPTY); setPage(1); }}>Réinitialiser</button>
          </div>
        </div>
      </div>

      <div className="panel">
        <div className="table-responsive">
          <table className="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Nom et prénom</th><th>CEF</th><th>Catégorie</th><th>Niveau</th>
                <th>Filière</th><th>Groupe</th><th>Promotion</th><th />
              </tr>
            </thead>
            <tbody>
              {loading && !result && <tr><td colSpan="8">Chargement…</td></tr>}
              {result && result.data.length === 0 && (
                <tr><td colSpan="8" className="text-muted">
                  Aucun stagiaire ne correspond à ces critères. {canWrite && <>Ajoutez un stagiaire ou <Link to="/importation">importez un fichier Excel</Link>.</>}
                </td></tr>
              )}
              {result && result.data.map((t) => {
                const e = t.latest_enrollment;
                return (
                  <tr key={t.id} className={t.deleted_at ? 'table-secondary' : ''}>
                    <td><Link to={`/stagiaires/${t.id}`}>{t.last_name} {t.first_name}</Link>{t.deleted_at && <span className="badge text-bg-secondary ms-2">Archivé</span>}</td>
                    <td>{t.cef || <span className="text-muted">—</span>}</td>
                    <td>{t.category ? t.category.label : <span className="badge text-bg-warning">À confirmer</span>}</td>
                    <td>{e?.level?.name || '—'}</td>
                    <td>{e?.filiere?.name || '—'}</td>
                    <td>{e?.group?.name || '—'}</td>
                    <td>{e?.promotion?.label || '—'}</td>
                    <td className="text-end text-nowrap">
                      <Link className="btn btn-sm btn-outline-secondary me-1" to={`/stagiaires/${t.id}`}>Fiche</Link>
                      {canWrite && !t.deleted_at && <Link className="btn btn-sm btn-outline-primary me-1" to={`/stagiaires/${t.id}/modifier`}>Modifier</Link>}
                      {canWrite && !t.deleted_at && <button className="btn btn-sm btn-outline-danger" onClick={() => archive(t)}>Archiver</button>}
                      {canWrite && t.deleted_at && <button className="btn btn-sm btn-outline-success" onClick={() => restore(t)}>Restaurer</button>}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        {result && <Pagination page={result.current_page} lastPage={result.last_page} total={result.total} onChange={setPage} />}
      </div>
    </>
  );
}
