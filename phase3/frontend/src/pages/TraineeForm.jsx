import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import api, { errorMessage } from '../api/client.js';
import { useToast } from '../components/Toast.jsx';
import useLookups, { Options } from '../hooks/useLookups.js';

const EMPTY = {
  last_name: '', first_name: '', name_ar: '', cef: '', registration_number: '', matricule: '', cin: '',
  birth_date: '', birth_place: '', gender: '', admin_status: '', establishment_id: '', category_id: '',
};
const EMPTY_ENROLL = { level_id: '', filiere_id: '', training_group_id: '', promotion_id: '', training_year: '' };

export default function TraineeForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const toast = useToast();
  const lookups = useLookups();
  const [form, setForm] = useState(EMPTY);
  const [enroll, setEnroll] = useState(EMPTY_ENROLL);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [loaded, setLoaded] = useState(!id);

  useEffect(() => {
    if (!id) return;
    api.get(`/trainees/${id}`).then((r) => {
      const t = r.data;
      setForm(Object.fromEntries(Object.keys(EMPTY).map((k) => [k, t[k] ?? ''])));
      const e = t.enrollments?.[0];
      if (e) setEnroll(Object.fromEntries(Object.keys(EMPTY_ENROLL).map((k) => [k, e[k] ?? ''])));
      setLoaded(true);
    }).catch((e) => toast.error(errorMessage(e)));
  }, [id]);

  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));
  const setE = (k) => (e) => setEnroll((f) => ({ ...f, [k]: e.target.value }));
  const err = (k) => errors[k]?.[0];

  async function submit(ev) {
    ev.preventDefault();
    setSaving(true);
    setErrors({});
    const body = { ...form, enrollment: enroll };
    try {
      const res = id ? await api.put(`/trainees/${id}`, body) : await api.post('/trainees', body);
      toast.success(id ? 'Dossier mis à jour.' : 'Stagiaire créé.');
      navigate(`/stagiaires/${res.data.id}`);
    } catch (e) {
      if (e.response?.status === 422) setErrors(e.response.data.errors || {});
      toast.error(errorMessage(e));
    } finally {
      setSaving(false);
    }
  }

  if (!loaded || !lookups) return <p>Chargement…</p>;

  const filieres = lookups.filieres.filter((f) =>
    (!form.establishment_id || String(f.establishment_id) === String(form.establishment_id)) &&
    (!enroll.level_id || String(f.level_id) === String(enroll.level_id)));
  const promotions = lookups.promotions.filter((p) => !form.establishment_id || String(p.establishment_id) === String(form.establishment_id));
  const groups = lookups.groups.filter((g) =>
    (!enroll.filiere_id || String(g.filiere_id) === String(enroll.filiere_id)) &&
    (!enroll.promotion_id || String(g.promotion_id) === String(enroll.promotion_id)));

  const text = (label, name, type = 'text') => (
    <div className="col-md-4">
      <label className="form-label" htmlFor={name}>{label}</label>
      <input id={name} type={type} className={`form-control ${err(name) ? 'is-invalid' : ''}`} value={form[name]} onChange={set(name)} />
      {err(name) && <div className="invalid-feedback">{err(name)}</div>}
    </div>
  );

  return (
    <form onSubmit={submit}>
      <h1 className="h3 mb-3">{id ? 'Modifier le dossier' : 'Nouveau stagiaire'}</h1>

      <div className="panel mb-3">
        <h2 className="h6 mb-3">Identité</h2>
        <div className="row g-3">
          {text('Nom *', 'last_name')}
          {text('Prénom *', 'first_name')}
          {text('Nom en arabe', 'name_ar')}
          {text('Date de naissance', 'birth_date', 'date')}
          {text('Lieu de naissance', 'birth_place')}
          <div className="col-md-4">
            <label className="form-label" htmlFor="gender">Sexe</label>
            <select id="gender" className="form-select" value={form.gender} onChange={set('gender')}>
              <option value="">Non renseigné</option><option value="M">Masculin</option><option value="F">Féminin</option>
            </select>
          </div>
          {text('CIN', 'cin')}
        </div>
      </div>

      <div className="panel mb-3">
        <h2 className="h6 mb-3">Identifiants administratifs</h2>
        <div className="row g-3">
          {text('CEF', 'cef')}
          {text("Numéro d'inscription", 'registration_number')}
          {text('Matricule', 'matricule')}
          {text('Statut administratif', 'admin_status')}
        </div>
      </div>

      <div className="panel mb-3">
        <h2 className="h6 mb-3">Établissement et catégorie</h2>
        <div className="row g-3">
          <div className="col-md-4">
            <label className="form-label" htmlFor="establishment_id">Établissement *</label>
            <select id="establishment_id" className={`form-select ${err('establishment_id') ? 'is-invalid' : ''}`} value={form.establishment_id} onChange={set('establishment_id')} required>
              <option value="">Choisir…</option><Options items={lookups.establishments} />
            </select>
            {err('establishment_id') && <div className="invalid-feedback">{err('establishment_id')}</div>}
            {lookups.establishments.length === 0 && <div className="form-text text-danger">Aucun établissement : créez-en un dans « Référentiels ».</div>}
          </div>
          <div className="col-md-4">
            <label className="form-label" htmlFor="category_id">Catégorie du candidat</label>
            <select id="category_id" className="form-select" value={form.category_id} onChange={set('category_id')}>
              <option value="">À confirmer</option><Options items={lookups.categories} label="label" />
            </select>
          </div>
        </div>
      </div>

      <div className="panel mb-3">
        <h2 className="h6 mb-1">Formation</h2>
        <p className="text-muted small">Facultatif. Un candidat libre n'a pas forcément de groupe. Un changement crée une nouvelle inscription : l'historique est conservé.</p>
        <div className="row g-3">
          <div className="col-md-4">
            <label className="form-label">Niveau</label>
            <select className="form-select" value={enroll.level_id} onChange={setE('level_id')}><option value="">—</option><Options items={lookups.levels} /></select>
          </div>
          <div className="col-md-4">
            <label className="form-label">Filière</label>
            <select className="form-select" value={enroll.filiere_id} onChange={setE('filiere_id')}><option value="">—</option><Options items={filieres} /></select>
          </div>
          <div className="col-md-4">
            <label className="form-label">Promotion</label>
            <select className="form-select" value={enroll.promotion_id} onChange={setE('promotion_id')}><option value="">—</option><Options items={promotions} label="label" /></select>
          </div>
          <div className="col-md-4">
            <label className="form-label">Groupe</label>
            <select className="form-select" value={enroll.training_group_id} onChange={setE('training_group_id')}><option value="">—</option><Options items={groups} /></select>
          </div>
          <div className="col-md-4">
            <label className="form-label">Année de formation</label>
            <select className="form-select" value={enroll.training_year} onChange={setE('training_year')}>
              <option value="">—</option>{[1, 2, 3].map((y) => <option key={y} value={y}>{y === 1 ? '1re année' : `${y}e année`}</option>)}
            </select>
          </div>
        </div>
      </div>

      <div className="d-flex gap-2">
        <button className="btn btn-primary" disabled={saving}>{saving ? 'Enregistrement…' : 'Enregistrer'}</button>
        <Link className="btn btn-outline-secondary" to={id ? `/stagiaires/${id}` : '/stagiaires'}>Annuler</Link>
      </div>
    </form>
  );
}
