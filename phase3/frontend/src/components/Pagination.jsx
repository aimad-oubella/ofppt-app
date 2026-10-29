export default function Pagination({ page, lastPage, total, onChange }) {
  if (!lastPage || lastPage <= 1) {
    return total !== undefined ? <div className="text-muted small mt-2">{total} résultat(s)</div> : null;
  }
  return (
    <div className="d-flex justify-content-between align-items-center mt-3">
      <div className="text-muted small">{total} résultat(s) — page {page} sur {lastPage}</div>
      <div className="btn-group">
        <button className="btn btn-outline-secondary btn-sm" disabled={page <= 1} onClick={() => onChange(page - 1)}>Précédent</button>
        <button className="btn btn-outline-secondary btn-sm" disabled={page >= lastPage} onClick={() => onChange(page + 1)}>Suivant</button>
      </div>
    </div>
  );
}
