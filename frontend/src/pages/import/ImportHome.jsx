import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api, { errorMessage } from '../../api/client.js';
import { useToast } from '../../components/Toast.jsx';

// Export conservé : utilisé par d'autres pages de l'assistant d'importation.
export const STATUS_LABELS = {
  televerse: 'Fichier chargé', mappe: 'Colonnes associées', valide: 'Validée', importe: 'Terminée',
};

// Couleur du badge selon l'état de l'importation.
const STATUS_TONE = { televerse: 'neutral', mappe: 'info', valide: 'warn', importe: 'ok' };

const STEPS = [
  { title: 'Charger le fichier', text: 'Excel .xls ou .xlsx, sans structure imposée.' },
  { title: 'Associer les colonnes', text: 'Chaque colonne correspond à un champ.' },
  { title: 'Valider', text: 'Contrôle des lignes avant enregistrement.' },
  { title: 'Importer', text: 'Enregistrement après votre confirmation.' },
];

const isExcel = (name) => /\.xlsx?$/i.test(name);

function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} o`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} Ko`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
}

export default function ImportHome() {
  const toast = useToast();
  const navigate = useNavigate();
  const input = useRef(null);
  const [history, setHistory] = useState(null);
  const [file, setFile] = useState(null);
  const [dragOver, setDragOver] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    api.get('/imports').then((r) => setHistory(r.data)).catch((e) => toast.error(errorMessage(e)));
  }, []);

  function choose(f) {
    if (!f) return;
    if (!isExcel(f.name)) {
      toast.error('Format non pris en charge. Choisissez un fichier .xls ou .xlsx.');
      return;
    }
    setFile(f);
  }

  function openPicker() {
    if (!uploading) input.current?.click();
  }

  function onDrop(e) {
    e.preventDefault();
    setDragOver(false);
    if (!uploading) choose(e.dataTransfer.files?.[0]);
  }

  async function upload(e) {
    e.preventDefault();
    if (!file) {
      toast.error('Choisissez un fichier Excel (.xls ou .xlsx).');
      return;
    }
    const body = new FormData();
    body.append('file', file);
    setUploading(true);
    setProgress(0);
    try {
      const res = await api.post('/imports', body, {
        onUploadProgress: (ev) => {
          if (ev.total) setProgress(Math.round((ev.loaded / ev.total) * 100));
        },
      });
      navigate(`/importation/${res.data.id}`);
    } catch (err) {
      toast.error(errorMessage(err));
    } finally {
      setUploading(false);
    }
  }

  return (
    <>
      <header className="dash-header">
        <h1 className="dash-title">Importation Excel</h1>
        <p className="dash-subtitle">
          Chargez un fichier, associez ses colonnes aux champs, puis confirmez. Rien n’est enregistré avant votre confirmation.
        </p>
      </header>

      <ol className="imp-steps">
        {STEPS.map((s, i) => (
          <li key={s.title} className={i === 0 ? 'is-current' : undefined}>
            <span className="imp-step-num">{i + 1}</span>
            <div>
              <strong>{s.title}</strong>
              <small>{s.text}</small>
            </div>
          </li>
        ))}
      </ol>

      <form onSubmit={upload} className="panel imp-upload">
        <h2 className="dash-panel-title">Nouvelle importation</h2>

        <input
          ref={input}
          type="file"
          hidden
          accept=".xls,.xlsx"
          onChange={(e) => {
            choose(e.target.files?.[0]);
            e.target.value = '';
          }}
        />

        <div
          className={`imp-drop${dragOver ? ' is-over' : ''}${file ? ' has-file' : ''}`}
          role="button"
          tabIndex={0}
          aria-label="Choisir un fichier Excel"
          onClick={openPicker}
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              openPicker();
            }
          }}
          onDragOver={(e) => {
            e.preventDefault();
            setDragOver(true);
          }}
          onDragLeave={() => setDragOver(false)}
          onDrop={onDrop}
        >
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
            <polyline points="17 8 12 3 7 8" />
            <line x1="12" y1="3" x2="12" y2="15" />
          </svg>
          {file ? (
            <div>
              <strong className="imp-file-name">{file.name}</strong>
              <span className="imp-file-size">{formatSize(file.size)}</span>
            </div>
          ) : (
            <div>
              <strong>Déposez votre fichier ici</strong>
              <span className="imp-file-size">ou cliquez pour le choisir · .xls, .xlsx</span>
            </div>
          )}
        </div>

        {uploading && (
          <div className="imp-progress-wrap" aria-live="polite">
            <div
              className={`imp-progress${progress >= 100 ? ' is-indeterminate' : ''}`}
              role="progressbar"
              aria-valuemin={0}
              aria-valuemax={100}
              aria-valuenow={progress}
            >
              <span style={{ width: `${progress}%` }} />
            </div>
            <small>{progress >= 100 ? 'Lecture du fichier…' : `Envoi du fichier… ${progress}%`}</small>
          </div>
        )}

        <div className="imp-actions">
          <button type="submit" className="btn btn-primary" disabled={!file || uploading}>
            {uploading ? 'Lecture du fichier…' : 'Charger et afficher un aperçu'}
          </button>
          {file && !uploading && (
            <button type="button" className="btn btn-link imp-clear" onClick={() => setFile(null)}>
              Retirer le fichier
            </button>
          )}
        </div>
      </form>

      <section className="panel dash-panel">
        <h2 className="dash-panel-title">Historique</h2>
        {!history ? (
          <p className="dash-empty">Chargement…</p>
        ) : history.length === 0 ? (
          <p className="dash-empty">Aucune importation pour le moment. Chargez un premier fichier ci-dessus.</p>
        ) : (
          <div className="table-responsive">
            <table className="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Fichier</th>
                  <th>État</th>
                  <th>Résultat</th>
                  <th>Date</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {history.map((h) => (
                  <tr key={h.id}>
                    <td className="imp-cell-file">{h.original_name}</td>
                    <td>
                      <span className={`imp-badge imp-badge--${STATUS_TONE[h.status] || 'neutral'}`}>
                        {STATUS_LABELS[h.status] || h.status}
                      </span>
                    </td>
                    <td>
                      {h.status === 'importe' && h.totals
                        ? `${h.totals.importees} importée(s), ${h.totals.rejetees} rejetée(s)`
                        : '—'}
                    </td>
                    <td>{new Date(h.created_at).toLocaleString('fr-FR')}</td>
                    <td className="text-end">
                      <Link className="btn btn-sm btn-outline-secondary" to={`/importation/${h.id}`}>
                        {h.status === 'importe' ? 'Voir le rapport' : 'Reprendre'}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </>
  );
}
