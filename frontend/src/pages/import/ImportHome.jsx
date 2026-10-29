import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api, { errorMessage } from '../../api/client.js';
import { useToast } from '../../components/Toast.jsx';

export const STATUS_LABELS = {
  televerse: 'Fichier chargé', mappe: 'Colonnes associées', valide: 'Validée', importe: 'Terminée',
};

export default function ImportHome() {
  const toast = useToast();
  const navigate = useNavigate();
  const input = useRef(null);
  const [history, setHistory] = useState(null);
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    api.get('/imports').then((r) => setHistory(r.data)).catch((e) => toast.error(errorMessage(e)));
  }, []);

  async function upload(e) {
    e.preventDefault();
    const file = input.current?.files?.[0];
    if (!file) { toast.error('Choisissez un fichier Excel (.xls ou .xlsx).'); return; }
    const body = new FormData();
    body.append('file', file);
    setUploading(true);
    try {
      const res = await api.post('/imports', body);
      navigate(`/importation/${res.data.id}`);
    } catch (err) {
      toast.error(errorMessage(err));
    } finally {
      setUploading(false);
    }
  }

  return (
    <>
      <h1 className="h3 mb-3">Importation Excel</h1>
      <div className="panel mb-3">
        <h2 className="h6">Nouvelle importation</h2>
        <p className="text-muted small">
          Le fichier peut avoir n'importe quelle structure : vous associerez ensuite chaque colonne à un champ.
          Rien n'est enregistré avant votre confirmation.
        </p>
        <form onSubmit={upload} className="d-flex gap-2 flex-wrap">
          <input ref={input} type="file" className="form-control" style={{ maxWidth: 420 }} accept=".xls,.xlsx" />
          <button className="btn btn-primary" disabled={uploading}>{uploading ? 'Lecture du fichier…' : 'Charger et afficher un aperçu'}</button>
        </form>
      </div>

      <div className="panel">
        <h2 className="h6 mb-3">Historique</h2>
        {!history ? <p>Chargement…</p> : history.length === 0 ? <p className="text-muted mb-0">Aucune importation pour le moment.</p> : (
          <table className="table table-sm mb-0">
            <thead><tr><th>Fichier</th><th>État</th><th>Résultat</th><th>Date</th><th /></tr></thead>
            <tbody>
              {history.map((h) => (
                <tr key={h.id}>
                  <td>{h.original_name}</td>
                  <td>{STATUS_LABELS[h.status] || h.status}</td>
                  <td>{h.status === 'importe' && h.totals ? `${h.totals.importees} importée(s), ${h.totals.rejetees} rejetée(s)` : '—'}</td>
                  <td>{new Date(h.created_at).toLocaleString('fr-FR')}</td>
                  <td className="text-end"><Link className="btn btn-sm btn-outline-secondary" to={`/importation/${h.id}`}>{h.status === 'importe' ? 'Voir le rapport' : 'Reprendre'}</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
