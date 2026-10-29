import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api, { downloadFile, errorMessage } from '../../api/client.js';
import { useToast } from '../../components/Toast.jsx';
import { Messages } from './ReviewStep.jsx';

function Stat({ label, value }) {
  return (
    <div className="col-6 col-md-3">
      <div className="panel h-100"><div className="text-muted small">{label}</div><div className="stat-value">{value ?? 0}</div></div>
    </div>
  );
}

function RowList({ title, rows }) {
  if (!rows.length) return null;
  return (
    <div className="panel mb-3">
      <h2 className="h6">{title} ({rows.length})</h2>
      <div className="table-responsive">
        <table className="table table-sm mb-0">
          <thead><tr><th>Ligne</th><th>Nom et prénom</th><th>CEF</th><th>Motif</th></tr></thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id}><td>{r.row_number}</td><td>{r.values.last_name} {r.values.first_name}</td><td>{r.values.cef || '—'}</td><td><Messages messages={r.messages} /></td></tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default function ReportStep({ batch }) {
  const toast = useToast();
  const [report, setReport] = useState(null);

  useEffect(() => {
    api.get(`/imports/${batch.id}/report`).then((r) => setReport(r.data)).catch((e) => toast.error(errorMessage(e)));
  }, [batch.id]);

  if (!report) return <p>Chargement du rapport…</p>;
  const t = report.totals || {};

  return (
    <>
      <div className="alert alert-success">Importation terminée. Voici le bilan détaillé.</div>
      <div className="row g-2 mb-3">
        <Stat label="Lignes lues" value={t.total} />
        <Stat label="Importées" value={t.importees} />
        <Stat label="Dossiers existants complétés" value={t.mises_a_jour} />
        <Stat label="Ignorées (dont doublons)" value={t.ignorees} />
        <Stat label="Rejetées" value={t.rejetees} />
        <Stat label="Sans catégorie" value={report.without_category} />
      </div>
      {report.without_category > 0 && (
        <div className="alert alert-warning">{report.without_category} dossier(s) importé(s) n'ont pas de catégorie. Dans la liste des stagiaires, cochez « Sans catégorie » pour les confirmer un par un.</div>
      )}
      <div className="d-flex gap-2 mb-3">
        <button className="btn btn-outline-secondary" onClick={() => downloadFile(`/imports/${batch.id}/report/export`, {}, `rapport-importation-${batch.id}.xlsx`).catch((e) => toast.error(errorMessage(e)))}>
          Télécharger le rapport (Excel)
        </button>
        <Link className="btn btn-primary" to="/stagiaires">Voir les stagiaires</Link>
      </div>
      <RowList title="Lignes rejetées" rows={report.rejected} />
      <RowList title="Doublons détectés" rows={report.duplicates} />
    </>
  );
}
