import { createContext, useCallback, useContext, useState } from 'react';

const ToastContext = createContext(null);

export function ToastProvider({ children }) {
  const [items, setItems] = useState([]);

  const push = useCallback((type, message) => {
    const id = Date.now() + Math.random();
    setItems((list) => [...list, { id, type, message }]);
    setTimeout(() => setItems((list) => list.filter((t) => t.id !== id)), 6000);
  }, []);

  const api = {
    success: (m) => push('success', m),
    error: (m) => push('danger', m),
  };

  return (
    <ToastContext.Provider value={api}>
      {children}
      <div style={{ position: 'fixed', top: 16, right: 16, zIndex: 2000, maxWidth: 380 }}>
        {items.map((t) => (
          <div key={t.id} className={`alert alert-${t.type} shadow-sm mb-2`} role="alert">{t.message}</div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}

export function useToast() {
  return useContext(ToastContext);
}
