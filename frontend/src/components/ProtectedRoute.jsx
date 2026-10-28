import { Navigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext.jsx';

// Masque l'interface selon le rôle. La vraie protection reste côté serveur (middleware « role »).
export default function ProtectedRoute({ children, roles }) {
  const { user, loading } = useAuth();

  if (loading) return <div className="p-4">Chargement…</div>;
  if (!user) return <Navigate to="/connexion" replace />;
  if (roles && !roles.includes(user.role)) return <Navigate to="/" replace />;

  return children;
}
