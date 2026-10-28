import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext.jsx';

const ROLE_LABELS = {
  administrateur: 'Administrateur',
  gestionnaire: 'Gestionnaire',
  consultation: 'Consultation',
};

const MENU = [
  { to: '/', label: 'Tableau de bord', end: true },
  { to: '/stagiaires', label: 'Stagiaires' },
  { to: '/laureats', label: 'Lauréats' },
  { to: '/importation', label: 'Importation Excel', roles: ['administrateur', 'gestionnaire'] },
  { to: '/attestations', label: 'Attestations' },
  { to: '/checklists', label: 'Check-lists diplômes' },
  { to: '/emargement', label: "Listes d'émargement" },
  { to: '/parametres', label: 'Utilisateurs et paramètres', roles: ['administrateur'] },
];

export default function Layout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  async function handleLogout() {
    await logout();
    navigate('/connexion');
  }

  return (
    <div className="app-shell">
      <aside className="app-sidebar">
        <div className="brand">
          Gestion des stagiaires
          <small>Établissement OFPPT</small>
        </div>
        <nav>
          {MENU.filter((item) => !item.roles || item.roles.includes(user.role)).map((item) => (
            <NavLink key={item.to} to={item.to} end={item.end} className="nav-item">
              {item.label}
            </NavLink>
          ))}
        </nav>
        <div className="user-box">
          <div>{user.name}</div>
          <div className="opacity-75 mb-2">{ROLE_LABELS[user.role] ?? user.role}</div>
          <button className="btn btn-sm btn-outline-light" onClick={handleLogout}>
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
