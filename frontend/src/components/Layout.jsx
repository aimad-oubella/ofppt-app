import { useEffect, useState } from 'react';
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext.jsx';

const ROLE_LABELS = {
  administrateur: 'Administrateur',
  gestionnaire: 'Gestionnaire',
  consultation: 'Consultation',
};

/* Icônes (tracés SVG simples, aucune dépendance) */
const ICONS = {
  dashboard: (
    <>
      <rect x="3" y="3" width="7" height="9" />
      <rect x="14" y="3" width="7" height="5" />
      <rect x="14" y="12" width="7" height="9" />
      <rect x="3" y="16" width="7" height="5" />
    </>
  ),
  users: (
    <>
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
    </>
  ),
  award: (
    <>
      <circle cx="12" cy="8" r="7" />
      <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88" />
    </>
  ),
  upload: (
    <>
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
      <polyline points="17 8 12 3 7 8" />
      <line x1="12" y1="3" x2="12" y2="15" />
    </>
  ),
  file: (
    <>
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
      <polyline points="14 2 14 8 20 8" />
      <line x1="16" y1="13" x2="8" y2="13" />
      <line x1="16" y1="17" x2="8" y2="17" />
    </>
  ),
  check: (
    <>
      <polyline points="9 11 12 14 22 4" />
      <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
    </>
  ),
  clipboard: (
    <>
      <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
      <rect x="8" y="2" width="8" height="4" rx="1" />
    </>
  ),
  book: (
    <>
      <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
      <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
    </>
  ),
  sliders: (
    <>
      <line x1="4" y1="21" x2="4" y2="14" />
      <line x1="4" y1="10" x2="4" y2="3" />
      <line x1="12" y1="21" x2="12" y2="12" />
      <line x1="12" y1="8" x2="12" y2="3" />
      <line x1="20" y1="21" x2="20" y2="16" />
      <line x1="20" y1="12" x2="20" y2="3" />
      <line x1="1" y1="14" x2="7" y2="14" />
      <line x1="9" y1="8" x2="15" y2="8" />
      <line x1="17" y1="16" x2="23" y2="16" />
    </>
  ),
  logout: (
    <>
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
      <polyline points="16 17 21 12 16 7" />
      <line x1="21" y1="12" x2="9" y2="12" />
    </>
  ),
  menu: (
    <>
      <line x1="3" y1="6" x2="21" y2="6" />
      <line x1="3" y1="12" x2="21" y2="12" />
      <line x1="3" y1="18" x2="21" y2="18" />
    </>
  ),
  close: (
    <>
      <line x1="18" y1="6" x2="6" y2="18" />
      <line x1="6" y1="6" x2="18" y2="18" />
    </>
  ),
};

function Icon({ name, size = 18 }) {
  return (
    <svg
      className="nav-icon"
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      {ICONS[name]}
    </svg>
  );
}

const MENU = [
  { to: '/', label: 'Tableau de bord', icon: 'dashboard', end: true },
  { to: '/stagiaires', label: 'Stagiaires', icon: 'users' },
  { to: '/laureats', label: 'Lauréats', icon: 'award' },
  { to: '/importation', label: 'Importation Excel', icon: 'upload', roles: ['administrateur', 'gestionnaire'] },
  { to: '/attestations', label: 'Attestations', icon: 'file' },
  { to: '/checklists', label: 'Check-lists diplômes', icon: 'check' },
  { to: '/emargement', label: "Listes d'émargement", icon: 'clipboard' },
  { to: '/referentiels', label: 'Référentiels', icon: 'book', roles: ['administrateur'] },
  { to: '/parametres', label: 'Utilisateurs et paramètres', icon: 'sliders', roles: ['administrateur'] },
];

const initials = (name = '') =>
  name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase() || '?';

export default function Layout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [open, setOpen] = useState(false);

  // Referme le menu mobile après chaque navigation.
  useEffect(() => {
    setOpen(false);
  }, [location.pathname]);

  async function handleLogout() {
    await logout();
    navigate('/connexion');
  }

  const roleLabel = ROLE_LABELS[user.role] ?? user.role;
  // Évite d'afficher deux fois « Administrateur » quand le nom du compte est identique au rôle.
  const sameAsRole = (user.name || '').trim().toLowerCase() === String(roleLabel).toLowerCase();

  return (
    <div className="app-shell">
      <aside className={`app-sidebar${open ? ' is-open' : ''}`}>
        <div className="brand">
          <div>
            Gestion des stagiaires
            <small>Établissement OFPPT</small>
          </div>
          <button
            type="button"
            className="sb-toggle"
            onClick={() => setOpen((o) => !o)}
            aria-expanded={open}
            aria-controls="main-nav"
            aria-label={open ? 'Fermer le menu' : 'Ouvrir le menu'}
          >
            <Icon name={open ? 'close' : 'menu'} size={22} />
          </button>
        </div>

        <nav id="main-nav" aria-label="Navigation principale">
          {MENU.filter((item) => !item.roles || item.roles.includes(user.role)).map((item) => (
            <NavLink key={item.to} to={item.to} end={item.end} className="nav-item">
              <Icon name={item.icon} />
              <span>{item.label}</span>
            </NavLink>
          ))}
        </nav>

        <div className="user-box">
          <div className="user-id">
            <span className="user-avatar" aria-hidden="true">
              {initials(user.name)}
            </span>
            <div className="user-text">
              <div className="user-name">{sameAsRole ? roleLabel : user.name}</div>
              {!sameAsRole && <div className="user-role">{roleLabel}</div>}
            </div>
          </div>
          <button type="button" className="sb-logout" onClick={handleLogout}>
            <Icon name="logout" size={16} />
            Se déconnecter
          </button>
        </div>
      </aside>
      <main className="app-main">
        <Outlet />
      </main>
    </div>
  );
}
