import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api, { downloadFile, errorMessage } from '../../api/client.js';
import { useToast } from '../../components/Toast.jsx';
import { Messages } from './ReviewStep.jsx';

// Réutilise les styles « chiffres clés » du tableau de bord (dx-kpis / dx-kpi).
function Stat({ label, value, tone }) {
  return (
    <div className={`dx-kpi${tone ? ` dx-kpi--${tone}` : ''}`}>
      <div className="dx-kpi-label">{label}</div>
      <div className="dx-kpi-value">{value ?? 0}</div>
    </div>
  );
}

function RowList({ title, rows, tone }) {
  if (!rows.length) return null;
  return (
    <section className="panel dash-panel mb-3">
      <h2 className="dash-panel-title">
        {title} <span className={`imp-badge imp-badge--${tone}`}>{rows.length}</span>
      </h2>
      <div className="table-responsive rp-list">
        <table className="table table-sm mb-0">
          <thead><tr><th>Ligne</th><th>Nom et prénom</th><th>CEF</th><th>Motif</th></tr></thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id}><td>{r.row_number}</td><td className="fw-semibold">{r.values.last_name} {r.values.first_name}</td><td>{r.values.cef || '—'}</td><td><Messages messages={r.messages} /></td></tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}

export default function ReportStep({ batch }) {
  const toast = useToast();
  const [report, setReport] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get(`/imports/${batch.id}/report`).then((r) => setReport(r.data)).catch((e) => setError(errorMessage(e)));
  }, [batch.id]);

  if (error) return <div className="alert alert-danger">Impossible de charger le rapport : {error}</div>;
  if (!report) return <p className="text-muted">Chargement du rapport…</p>;
  const t = report.totals || {};

  return (
    <>
      <div className="rp-hero" role="status">
        <span className="rp-hero-icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="20 6 9 17 4 12" />
          </svg>
        </span>
        <div>
          <strong>Importation terminée</strong>
          <span>{t.importees ?? 0} dossier(s) importé(s) sur {t.total ?? 0} ligne(s) lue(s). Voici le bilan détaillé.</span>
        </div>
      </div>

      <div className="dx-kpis mb-3">
        <Stat label="Lignes lues" value={t.total} />
        <Stat label="Importées" value={t.importees} tone="ok" />
        <Stat label="Dossiers existants complétés" value={t.mises_a_jour} />
        <Stat label="Ignorées (dont doublons)" value={t.ignorees} />
        <Stat label="Rejetées" value={t.rejetees} tone={t.rejetees > 0 ? 'danger' : undefined} />
        <Stat label="Sans catégorie" value={report.without_category} tone={report.without_category > 0 ? 'warn' : undefined} />
      </div>

      {report.without_category > 0 && (
        <div className="alert alert-warning">{report.without_category} dossier(s) importé(s) n'ont pas de catégorie. Dans la liste des stagiaires, cochez « Sans catégorie » pour les confirmer un par un.</div>
      )}

      <div className="d-flex gap-2 flex-wrap mb-3">
        <Link className="btn btn-primary" to="/stagiaires">Voir les stagiaires</Link>
        <button type="button" className="btn btn-outline-secondary" onClick={() => downloadFile(`/imports/${batch.id}/report/export`, {}, `rapport-importation-${batch.id}.xlsx`).catch((e) => toast.error(errorMessage(e)))}>
          Télécharger le rapport (Excel)
        </button>
      </div>

      <RowList title="Lignes rejetées" rows={report.rejected} tone="danger" />
      <RowList title="Doublons détectés" rows={report.duplicates} tone="warn" />
    </>
  );
}
