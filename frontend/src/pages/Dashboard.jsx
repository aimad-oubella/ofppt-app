import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  LabelList,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import api, { errorMessage } from '../api/client.js';

/* ------------------------------------------------------------------ */
/* Réglages                                                            */
/* ------------------------------------------------------------------ */

// Route de la page « Importation Excel » : adaptez-la à votre fichier de routes.
const IMPORT_PATH = '/importation';
// Actualisation automatique (en millisecondes).
const REFRESH_MS = 60000;

const TEAL = '#0f7b6c';
const NAVY = '#17324d';
const PALETTE = [TEAL, NAVY, '#5fd0bd', '#3b82c4', '#d9962b', '#8a97a5'];

// Ordre et couleur des statuts de check-list (libellés exacts renvoyés par l'API).
const STATUS_ORDER = ['À préparer', 'En cours de vérification', 'Incomplet', 'Rejeté', 'Conforme', 'Validé'];
const STATUS_COLOR = {
  'À préparer': '#8a97a5',
  'En cours de vérification': '#3b82c4',
  Incomplet: '#d9962b',
  Rejeté: '#c9433b',
  Conforme: '#5fd0bd',
  Validé: '#2f9e5b',
};
const TO_PROCESS = ['À préparer', 'En cours de vérification', 'Incomplet'];

const REDUCE_MOTION =
  typeof window !== 'undefined' && !!window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/* ------------------------------------------------------------------ */
/* Utilitaires                                                         */
/* ------------------------------------------------------------------ */

const clean = (rows = []) =>
  rows.map((r) => ({
    label: r.label && String(r.label).trim() ? String(r.label) : 'Non renseigné',
    total: Number(r.total) || 0,
  }));

const short = (s, n = 24) => (String(s).length > n ? `${String(s).slice(0, n - 1)}…` : String(s));
const hasData = (rows) => rows.some((r) => r.total > 0);

function useCountUp(target, duration = 700) {
  const [value, setValue] = useState(0);
  const shown = useRef(0);
  useEffect(() => {
    if (REDUCE_MOTION) {
      shown.current = target;
      setValue(target);
      return undefined;
    }
    const from = shown.current;
    const t0 = performance.now();
    let raf;
    const tick = (now) => {
      const p = Math.min(1, (now - t0) / duration);
      const v = Math.round(from + (target - from) * (1 - (1 - p) ** 3));
      shown.current = v;
      setValue(v);
      if (p < 1) raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
  }, [target, duration]);
  return value;
}

/* ------------------------------------------------------------------ */
/* Petits composants                                                   */
/* ------------------------------------------------------------------ */

function ChartTooltip({ active, payload, label, unit = 'stagiaires' }) {
  if (!active || !payload?.length) return null;
  const p = payload[0];
  return (
    <div className="dx-tooltip">
      <div className="dx-tooltip-label">{label ?? p.name}</div>
      <div className="dx-tooltip-value">
        {p.value} {unit}
      </div>
    </div>
  );
}

function Kpi({ label, value, suffix = '', hint }) {
  const shown = useCountUp(value);
  return (
    <div className="dx-kpi">
      <div className="dx-kpi-label">{label}</div>
      <div className="dx-kpi-value">
        {shown}
        {suffix}
      </div>
      {hint && <div className="dx-kpi-hint">{hint}</div>}
    </div>
  );
}

function ChartCard({ title, subtitle, action, empty, children }) {
  return (
    <section className="panel dash-panel dx-card h-100">
      <div className="dx-card-head">
        <div>
          <h2 className="dash-panel-title">{title}</h2>
          {subtitle && <p className="dx-card-sub">{subtitle}</p>}
        </div>
        {action}
      </div>
      {empty ? <p className="dash-empty">{empty}</p> : children}
    </section>
  );
}

/* Barre de progression segmentée : le point fort visuel de la page. */
function Pipeline({ steps, total, validated, rate }) {
  const [active, setActive] = useState(null);
  const visible = steps.filter((s) => s.total > 0);
  return (
    <section className="panel dx-hero">
      <div className="dx-hero-head">
        <div>
          <h2 className="dash-panel-title">Avancement des check-lists</h2>
          <p className="dx-card-sub">
            {total === 0 ? 'Aucune check-list pour le moment.' : `${validated} validée${validated > 1 ? 's' : ''} sur ${total}`}
          </p>
        </div>
        <div className="dx-rate">
          <strong>{rate}%</strong>
          <span>validées</span>
        </div>
      </div>

      <div
        className="dx-segbar"
        role="img"
        aria-label={`Répartition des check-lists : ${steps.map((s) => `${s.label} ${s.total}`).join(', ')}`}
      >
        {total === 0 ? (
          <span className="dx-seg dx-seg--empty" style={{ flex: 1 }} />
        ) : (
          visible.map((s) => (
            <span
              key={s.label}
              className={`dx-seg${active && active !== s.label ? ' is-dim' : ''}`}
              style={{ flex: s.total, background: s.color }}
              title={`${s.label} : ${s.total}`}
              onMouseEnter={() => setActive(s.label)}
              onMouseLeave={() => setActive(null)}
            />
          ))
        )}
      </div>

      <ul className="dx-legend">
        {steps.map((s) => (
          <li key={s.label}>
            <button
              type="button"
              className={`dx-legend-item${active === s.label ? ' is-active' : ''}`}
              onMouseEnter={() => setActive(s.label)}
              onMouseLeave={() => setActive(null)}
              onFocus={() => setActive(s.label)}
              onBlur={() => setActive(null)}
            >
              <i style={{ background: s.color }} aria-hidden="true" />
              <span>{s.label}</span>
              <b>{s.total}</b>
            </button>
          </li>
        ))}
      </ul>
    </section>
  );
}

function DonutCard({ title, rows }) {
  const total = rows.reduce((a, r) => a + r.total, 0);
  return (
    <ChartCard title={title} empty={hasData(rows) ? null : 'Aucune donnée pour le moment.'}>
      <div className="dx-donut">
        <ResponsiveContainer width="100%" height={210}>
          <PieChart>
            <Pie
              data={rows}
              dataKey="total"
              nameKey="label"
              innerRadius={64}
              outerRadius={92}
              paddingAngle={rows.length > 1 ? 3 : 0}
              stroke="none"
              isAnimationActive={!REDUCE_MOTION}
            >
              {rows.map((r, i) => (
                <Cell key={r.label} fill={PALETTE[i % PALETTE.length]} />
              ))}
            </Pie>
            <Tooltip content={<ChartTooltip />} />
          </PieChart>
        </ResponsiveContainer>
        <div className="dx-donut-center">
          <strong>{total}</strong>
          <span>stagiaires</span>
        </div>
      </div>
      <ul className="dx-donut-legend">
        {rows.map((r, i) => (
          <li key={r.label}>
            <span>
              <i style={{ background: PALETTE[i % PALETTE.length] }} aria-hidden="true" />
              {r.label}
            </span>
            <b>
              {r.total}
              <small>{total ? ` · ${Math.round((r.total / total) * 100)}%` : ''}</small>
            </b>
          </li>
        ))}
      </ul>
    </ChartCard>
  );
}

function ColumnCard({ title, subtitle, rows, color = TEAL, empty }) {
  return (
    <ChartCard title={title} subtitle={subtitle} empty={hasData(rows) ? null : empty}>
      <ResponsiveContainer width="100%" height={260}>
        <BarChart data={rows} margin={{ top: 22, right: 8, left: -18, bottom: 0 }}>
          <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e6ebf0" />
          <XAxis dataKey="label" tickLine={false} axisLine={false} tick={{ fontSize: 12, fill: '#5d6b7a' }} />
          <YAxis allowDecimals={false} tickLine={false} axisLine={false} tick={{ fontSize: 12, fill: '#5d6b7a' }} />
          <Tooltip cursor={{ fill: 'rgba(15,123,108,.08)' }} content={<ChartTooltip />} />
          <Bar dataKey="total" fill={color} radius={[4, 4, 0, 0]} maxBarSize={46} isAnimationActive={!REDUCE_MOTION}>
            <LabelList dataKey="total" position="top" fontSize={12} fontWeight={600} fill="#1d2733" />
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </ChartCard>
  );
}

function RefreshIcon({ spinning }) {
  return (
    <svg
      className={spinning ? 'dx-spin' : undefined}
      width="15"
      height="15"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M21 12a9 9 0 1 1-2.64-6.36" />
      <path d="M21 3v6h-6" />
    </svg>
  );
}

/* ------------------------------------------------------------------ */
/* Page                                                                */
/* ------------------------------------------------------------------ */

export default function Dashboard() {
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [updatedAt, setUpdatedAt] = useState(null);
  const [filiere, setFiliere] = useState('all');

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const r = await api.get('/dashboard');
      setData(r.data);
      setError('');
      setUpdatedAt(new Date());
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
    const id = setInterval(() => {
      if (!document.hidden) load();
    }, REFRESH_MS);
    return () => clearInterval(id);
  }, [load]);

  const view = useMemo(() => {
    if (!data) return null;
    const byStatus = clean(data.checklists_by_status);
    const known = STATUS_ORDER.map((label) => ({
      label,
      total: byStatus.find((s) => s.label === label)?.total || 0,
      color: STATUS_COLOR[label],
    }));
    const extra = byStatus
      .filter((s) => !STATUS_ORDER.includes(s.label))
      .map((s) => ({ ...s, color: STATUS_COLOR['À préparer'] }));
    const steps = [...known, ...extra];
    const totalLists = steps.reduce((a, s) => a + s.total, 0);
    const validated = steps.find((s) => s.label === 'Validé')?.total || 0;
    const toProcess = steps.filter((s) => TO_PROCESS.includes(s.label)).reduce((a, s) => a + s.total, 0);
    const groups = (data.by_filiere_group || []).map((g) => ({
      filiere: g.filiere || 'Non renseignée',
      groupe: g.groupe || 'Non renseigné',
      total: Number(g.total) || 0,
    }));
    return {
      steps,
      totalLists,
      validated,
      toProcess,
      rate: totalLists ? Math.round((validated / totalLists) * 100) : 0,
      category: clean(data.by_category),
      level: clean(data.by_level),
      promotion: clean(data.by_promotion),
      groups,
      filieres: [...new Set(groups.map((g) => g.filiere))].sort(),
    };
  }, [data]);

  const activeFiliere = view && view.filieres.includes(filiere) ? filiere : 'all';

  const groupRows = useMemo(() => {
    if (!view) return [];
    return view.groups
      .filter((g) => activeFiliere === 'all' || g.filiere === activeFiliere)
      .map((g) => ({ label: activeFiliere === 'all' ? `${g.filiere} – ${g.groupe}` : g.groupe, total: g.total }))
      .sort((a, b) => b.total - a.total);
  }, [view, activeFiliere]);

  if (!data && error) return <div className="alert alert-danger">{error}</div>;
  if (!data) return <p className="text-muted">Chargement…</p>;

  const isEmpty = Number(data.total_trainees) === 0;
  const groupHeight = Math.max(220, groupRows.length * 34 + 30);

  return (
    <>
      <header className="dx-header">
        <div>
          <h1 className="dash-title">Tableau de bord</h1>
          <p className="dash-subtitle">Vue d’ensemble des stagiaires et des dossiers de l’établissement.</p>
        </div>
        <div className="dx-refresh">
          {updatedAt && (
            <span className="dx-updated">
              Mis à jour à {updatedAt.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
            </span>
          )}
          <button type="button" className="btn btn-sm dx-btn" onClick={load} disabled={loading} aria-busy={loading}>
            <RefreshIcon spinning={loading} />
            {loading ? 'Actualisation…' : 'Actualiser'}
          </button>
        </div>
      </header>

      {error && (
        <div className="alert alert-warning py-2" role="alert">
          Actualisation impossible : {error}. Les chiffres affichés peuvent être anciens.
        </div>
      )}

      {isEmpty && (
        <div className="dash-callout" role="status">
          <div>
            <strong>Aucun stagiaire enregistré.</strong>
            <span> Importez votre fichier Excel pour alimenter le tableau de bord.</span>
          </div>
          <Link to={IMPORT_PATH} className="btn btn-sm dash-cta">
            Importer un fichier Excel
          </Link>
        </div>
      )}

      <div className="dx-kpis">
        <Kpi label="Stagiaires" value={Number(data.total_trainees) || 0} />
        <Kpi label="Documents générés" value={Number(data.generated_documents) || 0} />
        <Kpi
          label="Taux de validation"
          value={view.rate}
          suffix="%"
          hint={`${view.validated} sur ${view.totalLists} check-lists`}
        />
        <Kpi label="À traiter" value={view.toProcess} hint="À préparer, en vérification ou incomplètes" />
      </div>

      <Pipeline steps={view.steps} total={view.totalLists} validated={view.validated} rate={view.rate} />

      <div className="row g-3 mt-0">
        <div className="col-lg-5">
          <DonutCard title="Par catégorie" rows={view.category} />
        </div>
        <div className="col-lg-7">
          <ColumnCard title="Par niveau" rows={view.level} color={TEAL} empty="Aucun niveau renseigné pour le moment." />
        </div>
        <div className="col-lg-5">
          <ColumnCard
            title="Par promotion"
            rows={view.promotion}
            color={NAVY}
            empty="Les promotions apparaîtront après l’importation des stagiaires."
          />
        </div>
        <div className="col-lg-7">
          <ChartCard
            title="Par filière et groupe"
            subtitle={activeFiliere === 'all' ? 'Toutes les filières' : activeFiliere}
            empty={hasData(groupRows) ? null : 'Les filières et groupes apparaîtront après l’importation des stagiaires.'}
            action={
              view.filieres.length > 1 && (
                <select
                  className="form-select form-select-sm dx-select"
                  value={activeFiliere}
                  onChange={(e) => setFiliere(e.target.value)}
                  aria-label="Filtrer par filière"
                >
                  <option value="all">Toutes les filières</option>
                  {view.filieres.map((f) => (
                    <option key={f} value={f}>
                      {f}
                    </option>
                  ))}
                </select>
              )
            }
          >
            <ResponsiveContainer width="100%" height={groupHeight}>
              <BarChart data={groupRows} layout="vertical" margin={{ top: 0, right: 32, left: 0, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke="#e6ebf0" />
                <XAxis type="number" allowDecimals={false} tickLine={false} axisLine={false} tick={{ fontSize: 12, fill: '#5d6b7a' }} />
                <YAxis
                  type="category"
                  dataKey="label"
                  width={160}
                  tickLine={false}
                  axisLine={false}
                  tickFormatter={(v) => short(v)}
                  tick={{ fontSize: 12, fill: '#1d2733' }}
                />
                <Tooltip cursor={{ fill: 'rgba(23,50,77,.06)' }} content={<ChartTooltip />} />
                <Bar dataKey="total" fill={NAVY} radius={[0, 4, 4, 0]} barSize={16} isAnimationActive={!REDUCE_MOTION}>
                  <LabelList dataKey="total" position="right" fontSize={12} fontWeight={600} fill="#1d2733" />
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </ChartCard>
        </div>
      </div>
    </>
  );
}
