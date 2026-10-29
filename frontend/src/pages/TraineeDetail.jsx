import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import api, { errorMessage } from '../api/client.js';
import { useAuth } from '../auth/AuthContext.jsx';
import { useToast } from '../components/Toast.jsx';
import useLookups, { Options } from '../hooks/useLookups.js';

const GENDERS = { M: 'Masculin', F: 'Féminin' };

function Info({ label, value }) {
  return (
    <div className="col-md-4 mb-2">
      <div className="text-muted small">{label}</div>
      <div>{value || <span className="text-muted">Non renseigné</span>}</div>
    </div>
  );
}

export default function TraineeDetail() {
  const { id } = useParams();
  const { user } = useAuth();
  const toast = useToast();
  const lookups = useLookups();
  const [t, setT] = useState(null);
  const [grad, setGrad] = useState(null); // formulaire de déclaration de lauréat
  const canWrite = user.role !== 'consultation';

  const load = () => api.get(`/trainees/${id}`).then((r) => setT(r.data)).catch((e) => toast.error(errorMessage(e)));
  useEffect(() => { load(); }, [id]);

  if (!t || !lookups) return <p>Chargement…</p>;
  const last = t.enrollments?.[0];

  function openGrad() {
    setGrad({
      filiere_id: last?.filiere_id ?? '', promotion_id: last?.promotion_id ?? '',
      category_id: t.category_id ?? '', graduation_year: '', status: '', notes: '',
    });
  }

  async function saveGrad(e) {
    e.preventDefault();
    try {
      await api.post(`/trainees/${id}/graduations`, grad);
      toast.success('Lauréat enregistré.');
      setGrad(null);
      load();
    } catch (err) { toast.error(errorMessage(err)); }
  }

  async function removeGrad(g) {
    if (!window.confirm('Supprimer ce dossier de lauréat ?')) return;
    try { await api.delete(`/graduations/${g.id}`); toast.success('Supprimé.'); load(); }
    catch (err) { toast.error(errorMessage(err)); }
  }

  const setG = (k) => (e) => setGrad((g) => ({ ...g, [k]: e.target.value }));

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 className="h3 mb-0">
          {t.last_name} {t.first_name}
          {t.deleted_at && <span className="badge text-bg-secondary ms-2">Archivé</span>}
        </h1>
        <div className="d-flex gap-2">
          <Link className="btn btn-outline-secondary" to="/stagiaires">Retour à la liste</Link>
          {canWrite && !t.deleted_at && <Link className="btn btn-primary" to={`/stagiaires/${t.id}/modifier`}>Modifier</Link>}
        </div>
      </div>

      <div className="panel mb-3">
        <h2 className="h6 mb-3">Informations</h2>
        <div className="row">
          <Info label="CEF" value={t.cef} />
          <Info label="Numéro d'inscription" value={t.registration_number} />
          <Info label="Matricule" value={t.matricule} />
          <Info label="Nom en arabe" value={t.name_ar} />
          <Info label="Date de naissance" value={t.birth_date} />
          <Info label="Lieu de naissance" value={t.birth_place} />
          <Info label="Sexe" value={GENDERS[t.gender]} />
          <Info label="CIN" value={t.cin} />
          <Info label="Statut administratif" value={t.admin_status} />
          <Info label="Établissement" value={t.establishment?.name} />
          <Info label="Catégorie" value={t.category?.label} />
        </div>
        {!t.category && <div className="alert alert-warning mb-0 mt-2">La catégorie (régulier ou libre) n'est pas confirmée pour ce dossier.</div>}
      </div>

      <div className="panel mb-3">
        <h2 className="h6 mb-3">Historique des inscriptions</h2>
        {t.enrollments.length === 0 ? <p className="text-muted mb-0">Aucune inscription enregistrée.</p> : (
          <div className="table-responsive">
            <table className="table table-sm mb-0">
              <thead><tr><th>Niveau</th><th>Filière</th><th>Groupe</th><th>Promotion</th><th>Année</th><th>Enregistrée le</th></tr></thead>
              <tbody>
                {t.enrollments.map((e) => (
                  <tr key={e.id}>
                    <td>{e.level?.name || '—'}</td><td>{e.filiere?.name || '—'}</td><td>{e.group?.name || '—'}</td>
                    <td>{e.promotion?.label || '—'}</td><td>{e.training_year || '—'}</td>
                    <td>{new Date(e.created_at).toLocaleDateString('fr-FR')}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <div className="panel">
        <div className="d-flex justify-content-between align-items-center mb-3">
          <h2 className="h6 mb-0">Dossier de lauréat</h2>
          {canWrite && !t.deleted_at && !grad && <button className="btn btn-sm btn-outline-primary" onClick={openGrad}>Déclarer lauréat</button>}
        </div>

        {grad && (
          <form onSubmit={saveGrad} className="row g-2 mb-3 border rounded p-3">
            <div className="col-md-3">
              <label className="form-label">Catégorie *</label>
              <select className="form-select" value={grad.category_id} onChange={setG('category_id')} required>
                <option value="">À choisir…</option><Options items={lookups.categories} label="label" />
              </select>
            </div>
            <div className="col-md-3">
              <label className="form-label">Filière</label>
              <select className="form-select" value={grad.filiere_id} onChange={setG('filiere_id')}><option value="">—</option><Options items={lookups.filieres} /></select>
            </div>
            <div className="col-md-3">
              <label className="form-label">Promotion</label>
              <select className="form-select" value={grad.promotion_id} onChange={setG('promotion_id')}><option value="">—</option><Options items={lookups.promotions} label="label" /></select>
            </div>
            <div className="col-md-3">
              <label className="form-label">Année d'obtention</label>
              <input className="form-control" type="number" value={grad.graduation_year} onChange={setG('graduation_year')} />
            </div>
            <div className="col-12 d-flex gap-2">
              <button className="btn btn-primary btn-sm">Enregistrer</button>
              <button type="button" className="btn btn-outline-secondary btn-sm" onClick={() => setGrad(null)}>Annuler</button>
            </div>
          </form>
        )}

        {t.graduations.length === 0 ? <p className="text-muted mb-0">Ce stagiaire n'est pas déclaré lauréat.</p> : (
          <div className="table-responsive">
            <table className="table table-sm mb-0">
              <thead><tr><th>Catégorie</th><th>Filière</th><th>Promotion</th><th>Année</th><th /></tr></thead>
              <tbody>
                {t.graduations.map((g) => (
                  <tr key={g.id}>
                    <td>{g.category?.label || '—'}</td><td>{g.filiere?.name || '—'}</td>
                    <td>{g.promotion?.label || '—'}</td><td>{g.graduation_year || '—'}</td>
                    <td className="text-end">{canWrite && <button className="btn btn-sm btn-outline-danger" onClick={() => removeGrad(g)}>Supprimer</button>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </>
  );
}
