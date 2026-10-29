import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api, { cleanParams, errorMessage } from '../api/client.js';
import { useToast } from '../components/Toast.jsx';
import Pagination from '../components/Pagination.jsx';
import useLookups, { Options } from '../hooks/useLookups.js';

const EMPTY = { q: '', category_id: '', promotion_id: '', filiere_id: '', graduation_year: '' };

export default function Laureats() {
  const toast = useToast();
  const lookups = useLookups();
  const [filters, setFilters] = useState(EMPTY);
  const [page, setPage] = useState(1);
  const [result, setResult] = useState(null);

  useEffect(() => {
    const timer = setTimeout(() => {
      api.get('/graduations', { params: cleanParams({ ...filters, page, per_page: 25 }) })
        .then((r) => setResult(r.data))
        .catch((e) => toast.error(errorMessage(e)));
    }, 250);
    return () => clearTimeout(timer);
  }, [filters, page]);

  const change = (k, v) => { setFilters((f) => ({ ...f, [k]: v })); setPage(1); };

  return (
    <>
      <h1 className="h3 mb-3">Lauréats</h1>
      <div className="panel mb-3">
        <div className="row g-2">
          <div className="col-md-4"><input className="form-control" placeholder="Nom, prénom ou CEF" value={filters.q} onChange={(e) => change('q', e.target.value)} /></div>
          {lookups && (
            <>
              <div className="col-md-2"><select className="form-select" value={filters.category_id} onChange={(e) => change('category_id', e.target.value)}><option value="">Toutes catégories</option><Options items={lookups.categories} label="label" /></select></div>
              <div className="col-md-2"><select className="form-select" value={filters.filiere_id} onChange={(e) => change('filiere_id', e.target.value)}><option value="">Toutes filières</option><Options items={lookups.filieres} /></select></div>
              <div className="col-md-2"><select className="form-select" value={filters.promotion_id} onChange={(e) => change('promotion_id', e.target.value)}><option value="">Toutes promotions</option><Options items={lookups.promotions} label="label" /></select></div>
            </>
          )}
          <div className="col-md-2"><input className="form-control" type="number" placeholder="Année" value={filters.graduation_year} onChange={(e) => change('graduation_year', e.target.value)} /></div>
        </div>
      </div>
      <div className="panel">
        <div className="table-responsive">
          <table className="table table-hover mb-0">
            <thead><tr><th>Nom et prénom</th><th>CEF</th><th>Catégorie</th><th>Filière</th><th>Promotion</th><th>Année</th></tr></thead>
            <tbody>
              {!result && <tr><td colSpan="6">Chargement…</td></tr>}
              {result && result.data.length === 0 && <tr><td colSpan="6" className="text-muted">Aucun lauréat. Déclarez un lauréat depuis la fiche d'un stagiaire, ou importez un fichier avec l'option « enregistrer comme lauréats ».</td></tr>}
              {result && result.data.map((g) => (
                <tr key={g.id}>
                  <td><Link to={`/stagiaires/${g.trainee_id}`}>{g.trainee?.last_name} {g.trainee?.first_name}</Link></td>
                  <td>{g.trainee?.cef || '—'}</td><td>{g.category?.label || '—'}</td>
                  <td>{g.filiere?.name || '—'}</td><td>{g.promotion?.label || '—'}</td><td>{g.graduation_year || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {result && <Pagination page={result.current_page} lastPage={result.last_page} total={result.total} onChange={setPage} />}
      </div>
    </>
  );
}
