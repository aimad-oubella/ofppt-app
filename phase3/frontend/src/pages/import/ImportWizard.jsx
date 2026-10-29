import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import api, { errorMessage } from '../../api/client.js';
import { useToast } from '../../components/Toast.jsx';
import MappingStep from './MappingStep.jsx';
import ReviewStep from './ReviewStep.jsx';
import ReportStep from './ReportStep.jsx';

const STEPS = [['mapping', '1. Colonnes'], ['review', '2. Contrôle'], ['report', '3. Rapport']];

export default function ImportWizard() {
  const { id } = useParams();
  const toast = useToast();
  const [batch, setBatch] = useState(null);
  const [meta, setMeta] = useState(null);
  const [step, setStep] = useState(null);

  useEffect(() => {
    Promise.all([api.get(`/imports/${id}`), api.get('/import-meta')]).then(([b, m]) => {
      setBatch(b.data);
      setMeta(m.data);
      setStep(b.data.status === 'importe' ? 'report' : b.data.status === 'valide' ? 'review' : 'mapping');
    }).catch((e) => toast.error(errorMessage(e)));
  }, [id]);

  if (!batch || !meta) return <p>Chargement…</p>;
  const done = batch.status === 'importe';

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h1 className="h3 mb-0">Importation</h1>
          <div className="text-muted">{batch.original_name} — {batch.row_count} ligne(s) de données</div>
        </div>
        <Link className="btn btn-outline-secondary" to="/importation">Toutes les importations</Link>
      </div>

      <ul className="nav nav-pills mb-3">
        {STEPS.map(([key, label]) => {
          const disabled = done ? key !== 'report' : (key === 'report' || (key === 'review' && batch.status !== 'valide'));
          return (
            <li className="nav-item" key={key}>
              <button className={`nav-link ${step === key ? 'active' : ''}`} disabled={disabled} onClick={() => setStep(key)}>{label}</button>
            </li>
          );
        })}
      </ul>

      {step === 'mapping' && !done && (
        <MappingStep key={`${batch.mapping.sheet}-${batch.mapping.header_row}`} batch={batch} meta={meta}
          onBatch={setBatch} onValidated={(b) => { setBatch(b); setStep('review'); }} />
      )}
      {step === 'review' && !done && (
        <ReviewStep batch={batch} meta={meta} onBatch={setBatch} onBack={() => setStep('mapping')}
          onExecuted={(b) => { setBatch(b); setStep('report'); }} />
      )}
      {step === 'report' && <ReportStep batch={batch} />}
    </>
  );
}
