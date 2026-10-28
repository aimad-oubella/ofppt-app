import { useEffect, useState } from 'react';
import api, { errorMessage } from '../api/client.js';

function StatBlock({ title, rows, empty = 'Aucune donnée pour le moment.' }) {
  return (
    <div className="panel h-100">
      <h2 className="h6 mb-3">{title}</h2>
      {rows.length === 0 ? (
        <p className="text-muted mb-0">{empty}</p>
      ) : (
        <table className="table table-sm mb-0">
          <tbody>
            {rows.map((r, i) => (
              <tr key={i}>
                <td>{r.label}</td>
                <td className="text-end fw-semibold">{r.total}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

export default function Dashboard() {
  const [data, setData] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get('/dashboard').then((r) => setData(r.data)).catch((e) => setError(errorMessage(e)));
  }, []);

  if (error) return <div className="alert alert-danger">{error}</div>;
  if (!data) return <p>Chargement…</p>;

  return (
    <>
      <h1 className="h3 mb-4">Tableau de bord</h1>
      <div className="row g-3">
        <div className="col-md-6 col-xl-3">
          <div className="panel h-100">
            <div className="text-muted">Stagiaires</div>
            <div className="stat-value">{data.total_trainees}</div>
          </div>
        </div>
        <div className="col-md-6 col-xl-3">
          <div className="panel h-100">
            <div className="text-muted">Documents générés</div>
            <div className="stat-value">{data.generated_documents}</div>
          </div>
        </div>
        <div className="col-md-6 col-xl-3">
          <StatBlock title="Par catégorie" rows={data.by_category} />
        </div>
        <div className="col-md-6 col-xl-3">
          <StatBlock title="Check-lists par statut" rows={data.checklists_by_status} />
        </div>
        <div className="col-md-6">
          <StatBlock title="Par niveau" rows={data.by_level} />
        </div>
        <div className="col-md-6">
          <StatBlock title="Par promotion" rows={data.by_promotion} />
        </div>
        <div className="col-12">
          <StatBlock
            title="Par filière et groupe"
            rows={data.by_filiere_group.map((g) => ({ label: `${g.filiere} — ${g.groupe}`, total: g.total }))}
          />
        </div>
      </div>
    </>
  );
}
