export default function Modal({ title, onClose, children, footer, size = '' }) {
  return (
    <>
      <div className="modal-backdrop fade show" />
      <div className="modal fade show d-block" tabIndex="-1" role="dialog" aria-modal="true">
        <div className={`modal-dialog modal-dialog-scrollable ${size}`}>
          <div className="modal-content">
            <div className="modal-header">
              <h2 className="modal-title h5">{title}</h2>
              <button type="button" className="btn-close" aria-label="Fermer" onClick={onClose} />
            </div>
            <div className="modal-body">{children}</div>
            {footer && <div className="modal-footer">{footer}</div>}
          </div>
        </div>
      </div>
    </>
  );
}
